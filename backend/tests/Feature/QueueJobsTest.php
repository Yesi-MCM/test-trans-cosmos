<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Models\RealtimeEvent;
use App\Jobs\SendAssignmentEmailJob;
use App\Jobs\BulkTaskStatusUpdateJob;
use App\Jobs\ProcessAttachmentJob;
use App\Jobs\ExportTasksReportJob;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QueueJobsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();

        $this->user = User::create([
            'name' => 'Jane Admin',
            'email' => 'jane.admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $jwtService = $this->app->make(JwtService::class);
        $this->token = $jwtService->encode([
            'sub' => $this->user->id,
            'email' => $this->user->email,
            'role' => $this->user->role,
        ]);
    }

    protected function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
        ];
    }

    public function test_task_creation_dispatches_assignment_email_job()
    {
        Queue::fake();

        $payload = [
            'title' => 'Assigned Task',
            'assigned_user_id' => $this->user->id,
        ];

        $response = $this->postJson('/api/tasks', $payload, $this->getAuthHeaders());
        $response->assertStatus(201);

        Queue::assertPushed(SendAssignmentEmailJob::class);
    }

    public function test_send_assignment_email_job_sends_mail()
    {
        $task = Task::create([
            'title' => 'Email Test Task',
            'assigned_user_id' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $job = new SendAssignmentEmailJob($task);
        $job->handle();

        Mail::assertSent(\App\Mail\TaskAssignedMail::class, function ($mail) use ($task) {
            return $mail->hasTo($this->user->email) && $mail->task->id === $task->id;
        });
    }

    public function test_bulk_status_update_dispatches_job()
    {
        Queue::fake();

        $t1 = Task::create(['title' => 'T1', 'created_by' => $this->user->id]);

        $payload = [
            'task_ids' => [$t1->id],
            'status' => 'review',
        ];

        $response = $this->postJson('/api/tasks/bulk-status', $payload, $this->getAuthHeaders());
        $response->assertStatus(200);

        Queue::assertPushed(BulkTaskStatusUpdateJob::class);
    }

    public function test_bulk_task_status_update_job_updates_tasks()
    {
        $t1 = Task::create(['title' => 'T1', 'status' => 'todo', 'created_by' => $this->user->id]);
        $t2 = Task::create(['title' => 'T2', 'status' => 'todo', 'created_by' => $this->user->id]);

        $job = new BulkTaskStatusUpdateJob([$t1->id, $t2->id], 'completed', $this->user->id);
        $job->handle();

        $this->assertDatabaseHas('tasks', ['id' => $t1->id, 'status' => 'completed']);
        $this->assertDatabaseHas('tasks', ['id' => $t2->id, 'status' => 'completed']);

        // Assert SSE events are dispatched
        $this->assertDatabaseHas('realtime_events', [
            'event_type' => 'task_updated',
        ]);
    }

    public function test_file_upload_dispatches_process_attachment_job()
    {
        Queue::fake();

        $task = Task::create(['title' => 'Upload Task', 'created_by' => $this->user->id]);
        $file = UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        $response = $this->postJson("/api/tasks/{$task->id}/attachments", [
            'file' => $file,
        ], $this->getAuthHeaders());
        $response->assertStatus(201);

        Queue::assertPushed(ProcessAttachmentJob::class);
    }

    public function test_process_attachment_job_handles_infected_files()
    {
        $task = Task::create(['title' => 'Infected Task', 'created_by' => $this->user->id]);
        
        // malware signature test
        $eicarString = 'malware_signature_test';
        $filePath = 'attachments/test_infected.txt';
        Storage::disk('local')->put($filePath, $eicarString);

        $attachment = TaskAttachment::create([
            'task_id' => $task->id,
            'file_name' => 'infected_file.txt',
            'file_path' => $filePath,
            'file_size' => strlen($eicarString),
            'mime_type' => 'text/plain',
            'version' => 1,
            'status' => 'processing',
        ]);

        $job = new ProcessAttachmentJob($attachment->id);
        $job->handle();

        $attachment->refresh();
        $this->assertEquals('infected', $attachment->status);

        // Try downloading infected attachment
        $response = $this->getJson("/api/attachments/{$attachment->id}/download", $this->getAuthHeaders());
        $response->assertStatus(403);
    }

    public function test_process_attachment_job_generates_thumbnails_for_images()
    {
        // Skip this test if GD extension is not loaded
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        $task = Task::create(['title' => 'Image Task', 'created_by' => $this->user->id]);
        
        // Create actual dummy image using GD
        $img = imagecreatetruecolor(300, 300);
        $tempPath = tempnam(sys_get_temp_dir(), 'test_img');
        imagejpeg($img, $tempPath);
        imagedestroy($img);

        $filePath = 'attachments/test_image.jpg';
        Storage::disk('local')->put($filePath, file_get_contents($tempPath));
        unlink($tempPath);

        $attachment = TaskAttachment::create([
            'task_id' => $task->id,
            'file_name' => 'picture.jpg',
            'file_path' => $filePath,
            'file_size' => Storage::disk('local')->size($filePath),
            'mime_type' => 'image/jpeg',
            'version' => 1,
            'status' => 'processing',
        ]);

        $job = new ProcessAttachmentJob($attachment->id);
        $job->handle();

        $attachment->refresh();
        $this->assertEquals('ready', $attachment->status);

        // Verify thumbnail was created
        $thumbnailPath = "thumbnails/{$task->id}/" . basename($filePath);
        Storage::disk('local')->assertExists($thumbnailPath);
    }

    public function test_task_export_dispatches_job_and_generates_csv()
    {
        Queue::fake();

        $response = $this->postJson('/api/tasks/export', [], $this->getAuthHeaders());
        $response->assertStatus(200);

        Queue::assertPushed(ExportTasksReportJob::class);
    }

    public function test_export_tasks_report_job_generates_file_and_sends_email()
    {
        Task::create(['title' => 'Task A', 'created_by' => $this->user->id]);
        Task::create(['title' => 'Task B', 'created_by' => $this->user->id]);

        $job = new ExportTasksReportJob($this->user->id);
        $job->handle();

        Mail::assertSent(\App\Mail\ReportExportReadyMail::class, function ($mail) {
            return $mail->hasTo($this->user->email);
        });

        $this->assertDatabaseHas('realtime_events', [
            'event_type' => 'export_completed',
        ]);

        // Fetch event to get filename
        $event = RealtimeEvent::where('event_type', 'export_completed')->first();
        $fileName = $event->payload['file_name'];
        $downloadUrl = $event->payload['download_url'];

        // Assert file exists in storage
        Storage::disk('local')->assertExists("exports/{$fileName}");

        // Test secure download endpoint (with auth token as header)
        $response = $this->getJson($downloadUrl, $this->getAuthHeaders());
        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('Content-Disposition'), 'attachment'));

        // Test secure download endpoint (with auth token as query parameter)
        $responseQuery = $this->getJson("{$downloadUrl}?token={$this->token}");
        $responseQuery->assertStatus(200);
    }
}
