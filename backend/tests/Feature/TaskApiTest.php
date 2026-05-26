<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password123'),
            'role' => 'member',
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

    public function test_can_list_tasks_with_pagination()
    {
        Task::factory()->count(15)->create([
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/tasks?per_page=10', $this->getAuthHeaders());

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'current_page',
                'data',
                'first_page_url',
                'from',
                'last_page',
                'per_page',
                'to',
                'total',
            ]);
    }

    public function test_can_filter_tasks_by_status_and_priority()
    {
        Task::create([
            'title' => 'Task One',
            'status' => 'todo',
            'priority' => 'high',
            'created_by' => $this->user->id,
        ]);

        Task::create([
            'title' => 'Task Two',
            'status' => 'in_progress',
            'priority' => 'low',
            'created_by' => $this->user->id,
        ]);

        // Filter status
        $response = $this->getJson('/api/tasks?status=todo', $this->getAuthHeaders());
        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertEquals('Task One', $response->json('data.0.title'));

        // Filter priority
        $response = $this->getJson('/api/tasks?priority=low', $this->getAuthHeaders());
        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertEquals('Task Two', $response->json('data.0.title'));
    }

    public function test_can_search_tasks_by_text()
    {
        Task::create([
            'title' => 'Unbelievable task title',
            'description' => 'Normal description',
            'created_by' => $this->user->id,
        ]);

        Task::create([
            'title' => 'Another task',
            'description' => 'Unbelievable task details here',
            'created_by' => $this->user->id,
        ]);

        Task::create([
            'title' => 'Regular task',
            'description' => 'Regular text',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/tasks?search=Unbelievable', $this->getAuthHeaders());
        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_can_create_task()
    {
        $payload = [
            'title' => 'New Test Task',
            'description' => 'Description test',
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => '2026-06-01',
        ];

        $response = $this->postJson('/api/tasks', $payload, $this->getAuthHeaders());

        $response->assertStatus(201)
            ->assertJsonFragment([
                'title' => 'New Test Task',
                'description' => 'Description test',
                'status' => 'in_progress',
                'priority' => 'high',
                'created_by' => $this->user->id,
            ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'New Test Task',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_can_update_task()
    {
        $task = Task::create([
            'title' => 'Initial Title',
            'status' => 'todo',
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'title' => 'Updated Title',
            'status' => 'in_progress',
        ];

        $response = $this->putJson("/api/tasks/{$task->id}", $payload, $this->getAuthHeaders());

        $response->assertStatus(200)
            ->assertJsonFragment([
                'title' => 'Updated Title',
                'status' => 'in_progress',
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated Title',
            'status' => 'in_progress',
        ]);
    }

    public function test_can_delete_task()
    {
        $task = Task::create([
            'title' => 'Task to delete',
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/tasks/{$task->id}", [], $this->getAuthHeaders());

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_can_bulk_status_update()
    {
        $t1 = Task::create(['title' => 'T1', 'status' => 'todo', 'created_by' => $this->user->id]);
        $t2 = Task::create(['title' => 'T2', 'status' => 'todo', 'created_by' => $this->user->id]);

        $payload = [
            'task_ids' => [$t1->id, $t2->id],
            'status' => 'completed',
        ];

        $response = $this->postJson('/api/tasks/bulk-status', $payload, $this->getAuthHeaders());
        $response->assertStatus(200);

        $this->assertDatabaseHas('tasks', ['id' => $t1->id, 'status' => 'completed']);
        $this->assertDatabaseHas('tasks', ['id' => $t2->id, 'status' => 'completed']);
    }

    public function test_can_add_and_list_comments()
    {
        $task = Task::create(['title' => 'Comment Task', 'created_by' => $this->user->id]);

        // Add comment
        $response = $this->postJson("/api/tasks/{$task->id}/comments", [
            'comment' => 'This is a test comment',
        ], $this->getAuthHeaders());

        $response->assertStatus(201)
            ->assertJsonFragment([
                'task_id' => $task->id,
                'user_id' => $this->user->id,
                'comment' => 'This is a test comment',
            ]);

        // List comments
        $response = $this->getJson("/api/tasks/{$task->id}/comments", $this->getAuthHeaders());
        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'comment' => 'This is a test comment',
            ]);
    }

    public function test_can_upload_attachment_with_versioning()
    {
        $task = Task::create(['title' => 'Attachment Task', 'created_by' => $this->user->id]);

        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        // Upload Version 1
        $response = $this->postJson("/api/tasks/{$task->id}/attachments", [
            'file' => $file,
        ], $this->getAuthHeaders());

        $response->assertStatus(201)
            ->assertJsonFragment([
                'file_name' => 'document.pdf',
                'version' => 1,
            ]);

        $path1 = $response->json('file_path');
        Storage::disk('local')->assertExists($path1);

        // Upload Version 2 (Same filename)
        $response = $this->postJson("/api/tasks/{$task->id}/attachments", [
            'file' => $file,
        ], $this->getAuthHeaders());

        $response->assertStatus(201)
            ->assertJsonFragment([
                'file_name' => 'document.pdf',
                'version' => 2,
            ]);

        $path2 = $response->json('file_path');
        Storage::disk('local')->assertExists($path2);
        $this->assertNotEquals($path1, $path2);
    }

    public function test_can_upload_large_file_via_chunks()
    {
        $task = Task::create(['title' => 'Chunk Task', 'created_by' => $this->user->id]);
        $uploadId = 'test-upload-uuid-12345';
        $fileName = 'huge-video.mp4';
        
        $chunk1 = UploadedFile::fake()->create('chunk1.bin', 100);
        $chunk2 = UploadedFile::fake()->create('chunk2.bin', 100);

        // Upload chunk 0
        $response = $this->postJson('/api/attachments/chunk', [
            'task_id' => $task->id,
            'upload_id' => $uploadId,
            'chunk_index' => 0,
            'total_chunks' => 2,
            'file_name' => $fileName,
            'file' => $chunk1,
        ], $this->getAuthHeaders());

        $response->assertStatus(200)
            ->assertJsonFragment(['progress' => '50%']);

        // Upload chunk 1 (final chunk)
        $response = $this->postJson('/api/attachments/chunk', [
            'task_id' => $task->id,
            'upload_id' => $uploadId,
            'chunk_index' => 1,
            'total_chunks' => 2,
            'file_name' => $fileName,
            'file' => $chunk2,
        ], $this->getAuthHeaders());

        $response->assertStatus(201)
            ->assertJsonFragment([
                'file_name' => 'huge-video.mp4',
                'version' => 1,
            ]);

        $path = $response->json('attachment.file_path');
        Storage::disk('local')->assertExists($path);
    }

    public function test_can_download_and_delete_attachment()
    {
        $task = Task::create(['title' => 'Download Task', 'created_by' => $this->user->id]);
        $file = UploadedFile::fake()->create('test.txt', 10, 'text/plain');

        $response = $this->postJson("/api/tasks/{$task->id}/attachments", [
            'file' => $file,
        ], $this->getAuthHeaders());

        $attachmentId = $response->json('id');
        $path = $response->json('file_path');

        // Download file
        $response = $this->getJson("/api/attachments/{$attachmentId}/download", $this->getAuthHeaders());
        $response->assertStatus(200);

        // Delete file
        $response = $this->deleteJson("/api/attachments/{$attachmentId}", [], $this->getAuthHeaders());
        $response->assertStatus(200);

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('task_attachments', ['id' => $attachmentId]);
    }
}
