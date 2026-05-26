<?php

namespace App\Http\Controllers;

use App\Models\RealtimeEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RealtimeController extends Controller
{
    /**
     * Establish a Server-Sent Events (SSE) connection.
     */
    public function stream(Request $request)
    {
        $response = new StreamedResponse(function () use ($request) {
            // Disable PHP limit limits
            set_time_limit(0);
            
            // Get the last event ID the client processed
            $lastEventId = (int) $request->header('Last-Event-ID', $request->query('last_event_id', 0));

            // If no last event ID was provided, default to the maximum event ID currently in the DB
            // to avoid sending all historical events on connection start.
            if ($lastEventId === 0) {
                $lastEventId = RealtimeEvent::max('id') ?: 0;
            }

            $heartbeatInterval = 5; // seconds
            $lastHeartbeat = time();
            $lastPresenceCheck = 0;
            $lastOnlineUserIds = [];

            while (true) {
                // Check if client disconnected
                if (connection_aborted()) {
                    break;
                }

                // 1. Fetch new events from the database
                $events = RealtimeEvent::where('id', '>', $lastEventId)
                    ->orderBy('id', 'asc')
                    ->get();

                if ($events->isNotEmpty()) {
                    foreach ($events as $event) {
                        echo "id: {$event->id}\n";
                        echo "event: {$event->event_type}\n";
                        echo "data: " . json_encode($event->payload) . "\n\n";
                        
                        $lastEventId = $event->id;
                    }
                    ob_flush();
                    flush();
                }

                // 2. Periodically check and stream active users (Presence)
                if (time() - $lastPresenceCheck >= 5) {
                    $lastPresenceCheck = time();
                    
                    // Fetch online users from cache
                    $onlineUsersMap = Cache::get('online_users', []);
                    $now = time();
                    
                    // Filter out users who haven't updated in 15 seconds
                    $onlineUsersMap = array_filter($onlineUsersMap, function ($timestamp) use ($now) {
                        return $timestamp > $now - 15;
                    });
                    
                    $currentOnlineUserIds = array_keys($onlineUsersMap);
                    
                    // Sort to compare arrays easily
                    sort($currentOnlineUserIds);
                    sort($lastOnlineUserIds);

                    // If the list of online users has changed, stream the update
                    if ($currentOnlineUserIds !== $lastOnlineUserIds) {
                        $lastOnlineUserIds = $currentOnlineUserIds;
                        
                        // Fetch user profiles for the online IDs
                        $onlineUsers = User::whereIn('id', $currentOnlineUserIds)
                            ->select('id', 'name', 'email', 'role')
                            ->get();

                        echo "event: presence_updated\n";
                        echo "data: " . json_encode($onlineUsers) . "\n\n";
                        ob_flush();
                        flush();
                    }
                }

                // 3. Heartbeat / Keep-Alive to prevent connection drop
                if (time() - $lastHeartbeat >= $heartbeatInterval) {
                    $lastHeartbeat = time();
                    echo ": keep-alive\n\n";
                    ob_flush();
                    flush();
                }

                // Sleep to throttle CPU
                sleep(1);
            }
        });

        // Set appropriate SSE headers
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, private');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no'); // Disable buffering in Nginx

        return $response;
    }

    /**
     * Receive a heartbeat to signal user presence.
     */
    public function presence(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $onlineUsersMap = Cache::get('online_users', []);
        $onlineUsersMap[$user->id] = time();

        // Keep cache valid for 60 seconds
        Cache::put('online_users', $onlineUsersMap, 60);

        return response()->json(['status' => 'acknowledged']);
    }

    /**
     * Broadcast a typing indicator event.
     */
    public function typing(Request $request)
    {
        $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'is_typing' => 'required|boolean',
        ]);

        $user = Auth::user();
        $taskId = (int) $request->input('task_id');
        $isTyping = (bool) $request->input('is_typing');

        // Store typing event in database so the SSE stream broadcasts it
        RealtimeEvent::create([
            'event_type' => 'user_typing',
            'payload' => [
                'task_id' => $taskId,
                'is_typing' => $isTyping,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ]
            ],
        ]);

        return response()->json(['status' => 'broadcasted']);
    }
}
