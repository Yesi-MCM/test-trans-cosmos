<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\User;
use App\Models\RealtimeEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ExportTasksReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            Log::error("ExportTasksReportJob: User #{$this->userId} not found.");
            return;
        }

        Log::info("Starting task export for User #{$user->id}");

        // Fetch all tasks with relationships
        $tasks = Task::with(['assignedUser', 'creator'])->orderBy('created_at', 'desc')->get();

        // Generate CSV in memory
        $temp = fopen('php://temp', 'r+');

        // CSV Headers
        fputcsv($temp, [
            'ID', 
            'Title', 
            'Description', 
            'Status', 
            'Priority', 
            'Assigned User', 
            'Created By', 
            'Due Date', 
            'Created At', 
            'Updated At'
        ]);

        foreach ($tasks as $task) {
            fputcsv($temp, [
                $task->id,
                $task->title,
                $task->description,
                $task->status,
                $task->priority,
                $task->assignedUser ? $task->assignedUser->name : 'Unassigned',
                $task->creator ? $task->creator->name : 'N/A',
                $task->due_date ? $task->due_date->toIso8601String() : 'N/A',
                $task->created_at ? $task->created_at->toIso8601String() : 'N/A',
                $task->updated_at ? $task->updated_at->toIso8601String() : 'N/A',
            ]);
        }

        rewind($temp);
        $csvContent = stream_get_contents($temp);
        fclose($temp);

        // Store file
        $timestamp = time();
        $fileName = "tasks_report_{$timestamp}.csv";
        $filePath = "exports/{$fileName}";

        Storage::disk('local')->put($filePath, $csvContent);
        Log::info("ExportTasksReportJob: CSV saved successfully at {$filePath}");

        // Generate dynamic download URL
        $downloadUrl = "/api/exports/download/{$fileName}";

        // Send simulated email with download link
        Mail::to($user->email)->send(new \App\Mail\ReportExportReadyMail($user, $downloadUrl, $fileName));

        // Trigger SSE Event (so frontend can show a download link or toast notification!)
        RealtimeEvent::create([
            'event_type' => 'export_completed',
            'payload' => [
                'user_id' => $user->id,
                'download_url' => $downloadUrl,
                'file_name' => $fileName,
            ],
        ]);

        Log::info("ExportTasksReportJob completed. Sent export ready email to {$user->email}");
    }
}
