<?php

namespace App\Jobs;

use App\Models\TaskAttachment;
use App\Models\RealtimeEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessAttachmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $attachmentId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $attachmentId)
    {
        $this->attachmentId = $attachmentId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $attachment = TaskAttachment::find($this->attachmentId);

        if (!$attachment) {
            Log::warning("ProcessAttachmentJob: Attachment #{$this->attachmentId} not found.");
            return;
        }

        Log::info("Processing Attachment #{$attachment->id} (File: {$attachment->file_name}, Mime: {$attachment->mime_type})");

        // 1. Simulated Virus Scanning
        if (!Storage::disk('local')->exists($attachment->file_path)) {
            Log::error("ProcessAttachmentJob: File not found at path '{$attachment->file_path}'");
            return;
        }

        $fileContents = Storage::disk('local')->get($attachment->file_path);
        
        // Check for simulation signature (e.g. EICAR or containing 'infected' or 'virus')
        if (
            str_contains($fileContents, 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*') || 
            str_contains(strtolower($attachment->file_name), 'infected') || 
            str_contains(strtolower($fileContents), 'malware_signature_test')
        ) {
            Log::warning("ProcessAttachmentJob: Attachment #{$attachment->id} failed virus scan. Flagging as infected.");
            $attachment->update(['status' => 'infected']);

            // Notify clients of failure
            RealtimeEvent::create([
                'event_type' => 'attachment_updated',
                'payload' => $attachment,
            ]);
            return;
        }

        // 2. Image Thumbnail Generation (GD)
        if (str_starts_with($attachment->mime_type, 'image/') && extension_loaded('gd')) {
            try {
                $sourcePath = Storage::disk('local')->path($attachment->file_path);
                $thumbnailDir = "thumbnails/" . $attachment->task_id;
                $thumbnailName = basename($attachment->file_path);
                $thumbnailPath = "{$thumbnailDir}/{$thumbnailName}";
                $destPath = Storage::disk('local')->path($thumbnailPath);

                // Ensure directories exist
                if (!file_exists(dirname($destPath))) {
                    mkdir(dirname($destPath), 0755, true);
                }

                $img = null;
                switch ($attachment->mime_type) {
                    case 'image/jpeg':
                    case 'image/jpg':
                        $img = @imagecreatefromjpeg($sourcePath);
                        break;
                    case 'image/png':
                        $img = @imagecreatefrompng($sourcePath);
                        break;
                    case 'image/gif':
                        $img = @imagecreatefromgif($sourcePath);
                        break;
                    case 'image/webp':
                        $img = @imagecreatefromwebp($sourcePath);
                        break;
                }

                if ($img) {
                    $width = imagesx($img);
                    $height = imagesy($img);

                    // Create 150x150 thumbnail
                    $thumb = imagecreatetruecolor(150, 150);

                    // Maintain alpha transparency for PNG/GIF/WEBP
                    if (in_array($attachment->mime_type, ['image/png', 'image/gif', 'image/webp'])) {
                        imagealphablending($thumb, false);
                        imagesavealpha($thumb, true);
                    }

                    imagecopyresampled($thumb, $img, 0, 0, 0, 0, 150, 150, $width, $height);

                    // Save thumbnail
                    switch ($attachment->mime_type) {
                        case 'image/jpeg':
                        case 'image/jpg':
                            imagejpeg($thumb, $destPath, 85);
                            break;
                        case 'image/png':
                            imagepng($thumb, $destPath);
                            break;
                        case 'image/gif':
                            imagegif($thumb, $destPath);
                            break;
                        case 'image/webp':
                            imagewebp($thumb, $destPath, 85);
                            break;
                    }

                    imagedestroy($img);
                    imagedestroy($thumb);
                    Log::info("ProcessAttachmentJob: Thumbnail generated successfully at {$thumbnailPath}");
                }
            } catch (\Exception $e) {
                Log::error("ProcessAttachmentJob: Failed to generate thumbnail: " . $e->getMessage());
            }
        }

        // 3. Video Adaptive Streaming Simulation
        if (str_starts_with($attachment->mime_type, 'video/')) {
            Log::info("ProcessAttachmentJob: Generating adaptive bitrate streams (480p, 720p, 1080p) for Video #{$attachment->id}");
            // Log HLS manifest simulation
            Log::info("ProcessAttachmentJob: [Adaptive Video] Encoded stream 480p (Target: 800kbps)");
            Log::info("ProcessAttachmentJob: [Adaptive Video] Encoded stream 720p (Target: 2000kbps)");
            Log::info("ProcessAttachmentJob: [Adaptive Video] Encoded stream 1080p (Target: 4500kbps)");
            Log::info("ProcessAttachmentJob: [Adaptive Video] HLS Playlist generated: playlist.m3u8");
        }

        // Update attachment status to ready
        $attachment->update(['status' => 'ready']);
        Log::info("ProcessAttachmentJob: Attachment #{$attachment->id} processed successfully.");

        // Dispatch RealtimeEvent for SSE
        RealtimeEvent::create([
            'event_type' => 'attachment_updated',
            'payload' => $attachment,
        ]);
    }
}
