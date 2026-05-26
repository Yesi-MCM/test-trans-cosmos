<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\RealtimeEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BulkTaskStatusUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $taskIds;
    protected string $status;
    protected int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $taskIds, string $status, int $userId)
    {
        $this->taskIds = $taskIds;
        $this->status = $status;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting bulk task status update to '{$this->status}' for tasks: " . implode(', ', $this->taskIds) . " by User #{$this->userId}");

        // Perform update
        Task::whereIn('id', $this->taskIds)->update(['status' => $this->status]);

        // Retrieve updated tasks to dispatch SSE events
        $tasks = Task::whereIn('id', $this->taskIds)->get();

        foreach ($tasks as $task) {
            Log::info("Task #{$task->id} status updated to '{$this->status}' in bulk update.");

            // Dispatch RealtimeEvent for SSE (fleshed out in Phase 5)
            RealtimeEvent::create([
                'event_type' => 'task_updated',
                'payload' => $task->load(['assignedUser', 'creator']),
            ]);
        }

        Log::info("Bulk task status update finished successfully.");
    }
}
