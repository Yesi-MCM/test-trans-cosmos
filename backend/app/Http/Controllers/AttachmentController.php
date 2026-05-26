<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AttachmentController extends Controller
{
    /**
     * Upload an attachment for a task (Standard Upload).
     */
    public function upload(Request $request, $taskId)
    {
        $task = Task::find($taskId);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:20480', // Limit standard upload to 20MB
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();
        $mimeType = $uploadedFile->getClientMimeType();
        $fileSize = $uploadedFile->getSize();

        // Implement File Versioning
        // Check if file with same name already exists for this task
        $existing = TaskAttachment::where('task_id', $taskId)
            ->where('file_name', $originalName)
            ->orderBy('version', 'desc')
            ->first();

        $version = $existing ? $existing->version + 1 : 1;

        // Generate a unique path on disk
        $extension = $uploadedFile->getClientOriginalExtension();
        $diskName = Str::uuid()->toString() . "_v{$version}" . ($extension ? ".{$extension}" : "");
        $filePath = $uploadedFile->storeAs("attachments/{$taskId}", $diskName, 'local');

        $attachment = TaskAttachment::create([
            'task_id' => $taskId,
            'file_name' => $originalName,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'version' => $version,
            'status' => 'processing', // Will be processed by background job
        ]);

        // Dispatch background processing job (created in Phase 4)
        if (class_exists(\App\Jobs\ProcessAttachmentJob::class)) {
            \App\Jobs\ProcessAttachmentJob::dispatch($attachment->id);
        } else {
            // Direct mock fallback for Phase 3 testing
            $attachment->update(['status' => 'ready']);
        }

        // Trigger SSE Event (To be fleshed out in Phase 5)
        $this->triggerSSEEvent('attachment_added', $attachment);

        return response()->json($attachment, 201);
    }

    /**
     * Handle chunked file uploads for files > 50MB.
     */
    public function uploadChunk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'task_id' => 'required|exists:tasks,id',
            'upload_id' => 'required|string', // Unique ID for this file upload session
            'chunk_index' => 'required|integer',
            'total_chunks' => 'required|integer',
            'file_name' => 'required|string',
            'file' => 'required|file',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $taskId = $request->input('task_id');
        $uploadId = $request->input('upload_id');
        $chunkIndex = (int) $request->input('chunk_index');
        $totalChunks = (int) $request->input('total_chunks');
        $fileName = $request->input('file_name');
        $file = $request->file('file');

        // Store chunk in temp directory
        $tempPath = "chunks/{$uploadId}";
        $chunkName = "chunk_{$chunkIndex}";
        $file->storeAs($tempPath, $chunkName, 'local');

        // Check if all chunks have been uploaded
        $files = Storage::disk('local')->files($tempPath);
        
        if (count($files) === $totalChunks) {
            // All chunks are present, assemble the file
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            
            // Check versioning
            $existing = TaskAttachment::where('task_id', $taskId)
                ->where('file_name', $fileName)
                ->orderBy('version', 'desc')
                ->first();

            $version = $existing ? $existing->version + 1 : 1;
            
            $diskName = Str::uuid()->toString() . "_v{$version}" . ($extension ? ".{$extension}" : "");
            $finalPath = "attachments/{$taskId}/{$diskName}";
            
            $finalFullPath = Storage::disk('local')->path($finalPath);
            
            // Ensure destination directory exists
            if (!file_exists(dirname($finalFullPath))) {
                mkdir(dirname($finalFullPath), 0755, true);
            }

            // Merge chunks
            $out = fopen($finalFullPath, 'wb');
            if ($out === false) {
                return response()->json(['message' => 'Failed to open output stream'], 500);
            }

            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkFullPath = Storage::disk('local')->path("{$tempPath}/chunk_{$i}");
                $in = fopen($chunkFullPath, 'rb');
                if ($in === false) {
                    fclose($out);
                    return response()->json(['message' => "Failed to open chunk {$i} stream"], 500);
                }
                while ($buff = fread($in, 4096)) {
                    fwrite($out, $buff);
                }
                fclose($in);
            }
            fclose($out);

            // Get assembled file size & mime type
            $fileSize = Storage::disk('local')->size($finalPath);
            $mimeType = Storage::disk('local')->mimeType($finalPath) ?: 'application/octet-stream';

            // Delete temporary chunks directory
            Storage::disk('local')->deleteDirectory($tempPath);

            // Create task attachment record
            $attachment = TaskAttachment::create([
                'task_id' => $taskId,
                'file_name' => $fileName,
                'file_path' => $finalPath,
                'file_size' => $fileSize,
                'mime_type' => $mimeType,
                'version' => $version,
                'status' => 'processing',
            ]);

            // Dispatch background processing job (created in Phase 4)
            if (class_exists(\App\Jobs\ProcessAttachmentJob::class)) {
                \App\Jobs\ProcessAttachmentJob::dispatch($attachment->id);
            } else {
                // Direct mock fallback for Phase 3 testing
                $attachment->update(['status' => 'ready']);
            }

            // Trigger SSE Event (To be fleshed out in Phase 5)
            $this->triggerSSEEvent('attachment_added', $attachment);

            return response()->json([
                'message' => 'File assembled successfully',
                'attachment' => $attachment,
            ], 201);
        }

        return response()->json([
            'message' => "Chunk {$chunkIndex} uploaded successfully",
            'progress' => round((($chunkIndex + 1) / $totalChunks) * 100, 2) . '%'
        ]);
    }

    /**
     * Download the specified attachment securely.
     */
    public function download($id)
    {
        $attachment = TaskAttachment::find($id);

        if (!$attachment) {
            return response()->json(['message' => 'Attachment not found'], 404);
        }

        // Handle thumbnail serving
        if (request()->query('thumbnail') && str_starts_with($attachment->mime_type, 'image/')) {
            $thumbnailPath = str_replace('attachments/', 'thumbnails/', $attachment->file_path);
            if (Storage::disk('local')->exists($thumbnailPath)) {
                return Storage::disk('local')->download($thumbnailPath, 'thumb_' . $attachment->file_name);
            }
        }

        // Check if file exists in disk
        if (!Storage::disk('local')->exists($attachment->file_path)) {
            return response()->json(['message' => 'File not found on server'], 404);
        }

        // Verify if file is infected
        if ($attachment->status === 'infected') {
            return response()->json(['message' => 'This file has been flagged as infected and cannot be downloaded.'], 403);
        }

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }

    /**
     * Delete the specified attachment.
     */
    public function destroy($id)
    {
        $attachment = TaskAttachment::find($id);

        if (!$attachment) {
            return response()->json(['message' => 'Attachment not found'], 404);
        }

        // Delete physical file
        if (Storage::disk('local')->exists($attachment->file_path)) {
            Storage::disk('local')->delete($attachment->file_path);
        }

        // Delete thumbnail if it exists
        $thumbnailPath = str_replace('attachments/', 'thumbnails/', $attachment->file_path);
        if (Storage::disk('local')->exists($thumbnailPath)) {
            Storage::disk('local')->delete($thumbnailPath);
        }

        // Delete DB record
        $attachment->delete();

        // Trigger SSE Event (To be fleshed out in Phase 5)
        $this->triggerSSEEvent('attachment_deleted', ['id' => (int) $id]);

        return response()->json(['message' => 'Attachment deleted successfully']);
    }

    /**
     * Helper to trigger SSE events.
     */
    protected function triggerSSEEvent(string $type, $payload)
    {
        if (class_exists(\App\Models\RealtimeEvent::class)) {
            \App\Models\RealtimeEvent::create([
                'event_type' => $type,
                'payload' => $payload,
            ]);
        }
    }
}
