<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Users
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@taskmanager.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $john = User::create([
            'name' => 'John Member',
            'email' => 'john@taskmanager.com',
            'password' => Hash::make('password'),
            'role' => 'member',
        ]);

        $jane = User::create([
            'name' => 'Jane Member',
            'email' => 'jane@taskmanager.com',
            'password' => Hash::make('password'),
            'role' => 'member',
        ]);

        $alex = User::create([
            'name' => 'Alex Member',
            'email' => 'alex@taskmanager.com',
            'password' => Hash::make('password'),
            'role' => 'member',
        ]);

        $sarah = User::create([
            'name' => 'Sarah Member',
            'email' => 'sarah@taskmanager.com',
            'password' => Hash::make('password'),
            'role' => 'member',
        ]);

        $members = [$john, $jane, $alex, $sarah];

        // 2. Create 15 Tasks
        $taskData = [
            [
                'title' => 'Design System Architecture',
                'description' => 'Create a detailed design document for the task management system backend and frontend connection.',
                'status' => 'completed',
                'priority' => 'high',
                'assigned_user_id' => $john->id,
                'due_date' => Carbon::now()->addDays(2),
            ],
            [
                'title' => 'Setup MySQL Database Schema',
                'description' => 'Install migrations and define table schemas with appropriate foreign keys and indexes.',
                'status' => 'completed',
                'priority' => 'high',
                'assigned_user_id' => $jane->id,
                'due_date' => Carbon::now()->addDays(3),
            ],
            [
                'title' => 'Implement JWT Authentication',
                'description' => 'Develop secure custom guard and middleware for login, logout, and authenticated me endpoint.',
                'status' => 'in_progress',
                'priority' => 'high',
                'assigned_user_id' => $john->id,
                'due_date' => Carbon::now()->addDays(5),
            ],
            [
                'title' => 'Build Task CRUD REST APIs',
                'description' => 'Develop GET, POST, PUT, and DELETE endpoints with robust server-side validation and search filters.',
                'status' => 'in_progress',
                'priority' => 'medium',
                'assigned_user_id' => $jane->id,
                'due_date' => Carbon::now()->addDays(7),
            ],
            [
                'title' => 'File Upload and Thumbnail Generation',
                'description' => 'Build secure attachment upload, generate 150x150 image thumbnails in background, and save versions.',
                'status' => 'todo',
                'priority' => 'medium',
                'assigned_user_id' => $alex->id,
                'due_date' => Carbon::now()->addDays(10),
            ],
            [
                'title' => 'Implement Chunked Large File Upload',
                'description' => 'Support chunked uploading for files exceeding 50MB, merging fragments sequentially on completion.',
                'status' => 'todo',
                'priority' => 'high',
                'assigned_user_id' => $sarah->id,
                'due_date' => Carbon::now()->addDays(12),
            ],
            [
                'title' => 'Simulated Virus Scanning Integration',
                'description' => 'Create a virus scan check that flags infected attachments matching test signatures.',
                'status' => 'todo',
                'priority' => 'low',
                'assigned_user_id' => $alex->id,
                'due_date' => Carbon::now()->addDays(15),
            ],
            [
                'title' => 'Background Queue Job Notifications',
                'description' => 'Configure queue worker to send logged email notifications to users when tasks are assigned.',
                'status' => 'todo',
                'priority' => 'medium',
                'assigned_user_id' => null,
                'due_date' => Carbon::now()->addDays(8),
            ],
            [
                'title' => 'Data Export Background Worker',
                'description' => 'Create a background worker that exports all tasks into a CSV file and emails a download link.',
                'status' => 'todo',
                'priority' => 'low',
                'assigned_user_id' => $sarah->id,
                'due_date' => Carbon::now()->addDays(20),
            ],
            [
                'title' => 'Real-time Server-Sent Events',
                'description' => 'Build an SSE streaming endpoint that broadcasts board status updates, comments, and presence indicators.',
                'status' => 'in_progress',
                'priority' => 'high',
                'assigned_user_id' => $john->id,
                'due_date' => Carbon::now()->addDays(4),
            ],
            [
                'title' => 'Write Backend PHPUnit Integration Tests',
                'description' => 'Write robust unit and integration tests to verify API endpoints, authentication, and job dispatching.',
                'status' => 'todo',
                'priority' => 'medium',
                'assigned_user_id' => $jane->id,
                'due_date' => Carbon::now()->addDays(14),
            ],
            [
                'title' => 'Optimize API Query Performance',
                'description' => 'Implement Redis key-value store to cache task list and avoid hitting SQL database on redundant requests.',
                'status' => 'todo',
                'priority' => 'low',
                'assigned_user_id' => null,
                'due_date' => Carbon::now()->addDays(25),
            ],
            [
                'title' => 'Build Next.js Frontend Layout',
                'description' => 'Initialize Next.js application, write custom CSS modules, and design dynamic glassmorphic views.',
                'status' => 'in_progress',
                'priority' => 'medium',
                'assigned_user_id' => $sarah->id,
                'due_date' => Carbon::now()->addDays(6),
            ],
            [
                'title' => 'Drag-and-Drop Board Interactions',
                'description' => 'Build an interactive Kanban board allowing smooth drag and drop of tasks across board columns.',
                'status' => 'todo',
                'priority' => 'high',
                'assigned_user_id' => $alex->id,
                'due_date' => Carbon::now()->addDays(9),
            ],
            [
                'title' => 'Implement Presence and Typing Indicators',
                'description' => 'Track active online users in frontend using SSE and display typing indicators on comments.',
                'status' => 'todo',
                'priority' => 'low',
                'assigned_user_id' => $john->id,
                'due_date' => Carbon::now()->addDays(11),
            ],
        ];

        $tasks = [];
        foreach ($taskData as $data) {
            $tasks[] = Task::create(array_merge($data, [
                'created_by' => $admin->id,
            ]));
        }

        // 3. Create 10 Comments
        $commentPool = [
            'Let\'s make sure we write clean code and document the API structure.',
            'Working on this today, will update status when migrations run.',
            'Should we use Sanctum or a custom JWT encoder?',
            'Custom JWT is better to show control over security headers.',
            'Need to configure GD extension in php.ini before processing images.',
            'Is the chunk size for uploading set to 5MB or larger?',
            'Let\'s set chunk size to 5MB, it works well with Laragon defaults.',
            'Virus scan simulation should support the EICAR test string.',
            'The dashboard design is coming along nicely, glassmorphism looks premium!',
            'Will start writing unit tests for tasks API tomorrow.',
        ];

        for ($i = 0; $i < 10; $i++) {
            $randomTask = $tasks[$i % count($tasks)];
            $randomUser = $members[$i % count($members)];

            TaskComment::create([
                'task_id' => $randomTask->id,
                'user_id' => $randomUser->id,
                'comment' => $commentPool[$i],
            ]);
        }
    }
}
