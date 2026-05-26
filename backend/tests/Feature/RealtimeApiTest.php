<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Models\RealtimeEvent;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RealtimeApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Charlie Realtime',
            'email' => 'charlie@example.com',
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

    public function test_user_can_send_presence_heartbeat()
    {
        Cache::forget('online_users');

        $response = $this->postJson('/api/realtime/presence', [], $this->getAuthHeaders());

        $response->assertStatus(200)
            ->assertJson(['status' => 'acknowledged']);

        $onlineUsers = Cache::get('online_users');
        $this->assertIsArray($onlineUsers);
        $this->assertArrayHasKey($this->user->id, $onlineUsers);
        $this->assertTrue($onlineUsers[$this->user->id] <= time());
    }

    public function test_user_can_broadcast_typing_indicator()
    {
        $task = Task::create([
            'title' => 'Realtime Task',
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'task_id' => $task->id,
            'is_typing' => true,
        ];

        $response = $this->postJson('/api/realtime/typing', $payload, $this->getAuthHeaders());

        $response->assertStatus(200)
            ->assertJson(['status' => 'broadcasted']);

        $this->assertDatabaseHas('realtime_events', [
            'event_type' => 'user_typing',
        ]);

        $event = RealtimeEvent::where('event_type', 'user_typing')->first();
        $this->assertEquals($task->id, $event->payload['task_id']);
        $this->assertTrue($event->payload['is_typing']);
        $this->assertEquals($this->user->name, $event->payload['user']['name']);
    }

    public function test_stream_returns_correct_sse_headers()
    {
        // Test with token parameter to simulate EventSource client connection
        $response = $this->get("/api/realtime/stream?token={$this->token}");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertEquals('no-cache, private', $response->headers->get('Cache-Control'));
        $this->assertEquals('keep-alive', $response->headers->get('Connection'));
        $this->assertEquals('no', $response->headers->get('X-Accel-Buffering'));
    }
}
