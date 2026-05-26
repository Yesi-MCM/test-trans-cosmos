<?php

namespace App\Jobs;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendAssignmentEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Task $task;

    /**
     * Create a new job instance.
     */
    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Load assigned user if not loaded
        $user = $this->task->assignedUser;

        if (!$user) {
            Log::warning("SendAssignmentEmailJob: Task #{$this->task->id} does not have an assigned user.");
            return;
        }

        $email = $user->email;
        $title = $this->task->title;
        $priority = $this->task->priority;

        Log::info("Queueing assignment email for Task #{$this->task->id} to {$email}");

        Mail::to($email)->send(new \App\Mail\TaskAssignedMail($this->task));

        Log::info("Assignment email sent successfully for Task #{$this->task->id} to {$email}");
    }
}
