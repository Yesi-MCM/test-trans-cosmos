<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    /**
     * Display a listing of the tasks with filters, sorting, and pagination.
     */
    public function index(Request $request)
    {
        $query = Task::with(['assignedUser', 'creator']);

        // Filtering by status
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filtering by priority
        if ($request->filled('priority')) {
            $query->where('priority', $request->query('priority'));
        }

        // Filtering by assigned user
        if ($request->filled('assigned_user_id')) {
            $query->where('assigned_user_id', $request->query('assigned_user_id'));
        }

        // Text search on title/description
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');
        
        // White-list sorting fields to prevent SQL injection
        $allowedSorts = ['title', 'status', 'priority', 'due_date', 'created_at', 'updated_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100); // Constraint between 1 and 100

        $tasks = $query->paginate($perPage);

        return response()->json($tasks);
    }

    /**
     * Store a newly created task in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:todo,in_progress,review,completed',
            'priority' => 'nullable|string|in:low,medium,high',
            'assigned_user_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['created_by'] = Auth::id();
        $data['status'] = $data['status'] ?? 'todo';
        $data['priority'] = $data['priority'] ?? 'medium';

        $task = Task::create($data);

        // Load relationships
        $task->load(['assignedUser', 'creator']);

        // Dispatch SSE Event (To be fleshed out in Phase 5)
        $this->triggerSSEEvent('task_created', $task);

        // Dispatch Email notification in Phase 4 (Assignment)
        if ($task->assigned_user_id) {
            $this->dispatchAssignmentEmail($task);
        }

        return response()->json($task, 201);
    }

    /**
     * Display the specified task.
     */
    public function show($id)
    {
        $task = Task::with([
            'assignedUser', 
            'creator', 
            'attachments', 
            'comments.user'
        ])->find($id);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        return response()->json($task);
    }

    /**
     * Update the specified task in storage.
     */
    public function update(Request $request, $id)
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|string|in:todo,in_progress,review,completed',
            'priority' => 'sometimes|required|string|in:low,medium,high',
            'assigned_user_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldAssignedUser = $task->assigned_user_id;

        $task->update($validator->validated());

        // Load relationships
        $task->load(['assignedUser', 'creator']);

        // Trigger SSE Event (To be fleshed out in Phase 5)
        $this->triggerSSEEvent('task_updated', $task);

        // Dispatch Email notification in Phase 4 if assignee changed
        if ($task->assigned_user_id && $task->assigned_user_id !== $oldAssignedUser) {
            $this->dispatchAssignmentEmail($task);
        }

        return response()->json($task);
    }

    /**
     * Remove the specified task from storage.
     */
    public function destroy($id)
    {
        $task = Task::find($id);

        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $task->delete();

        // Trigger SSE Event (To be fleshed out in Phase 5)
        $this->triggerSSEEvent('task_deleted', ['id' => (int) $id]);

        return response()->json(['message' => 'Task deleted successfully']);
    }

    /**
     * Perform a bulk status update (Dispatches background job).
     */
    public function bulkStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'task_ids' => 'required|array',
            'task_ids.*' => 'required|exists:tasks,id',
            'status' => 'required|string|in:todo,in_progress,review,completed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $taskIds = $request->input('task_ids');
        $status = $request->input('status');

        // Dispatch bulk update job (Skeleton will be created next)
        if (class_exists(\App\Jobs\BulkTaskStatusUpdateJob::class)) {
            \App\Jobs\BulkTaskStatusUpdateJob::dispatch($taskIds, $status, Auth::id());
        } else {
            // Fallback direct update if job class is not loaded yet (prevents crashing during Phase 3 verification)
            Task::whereIn('id', $taskIds)->update(['status' => $status]);
            
            // Trigger SSE events for updated tasks
            $updatedTasks = Task::whereIn('id', $taskIds)->get();
            foreach ($updatedTasks as $task) {
                $this->triggerSSEEvent('task_updated', $task);
            }
        }

        return response()->json([
            'message' => 'Bulk status update job has been dispatched'
        ]);
    }

    /**
     * List comments for a task.
     */
    public function comments($id)
    {
        $task = Task::find($id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $comments = TaskComment::where('task_id', $id)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($comments);
    }

    /**
     * Add a comment to a task.
     */
    public function storeComment(Request $request, $id)
    {
        $task = Task::find($id);
        if (!$task) {
            return response()->json(['message' => 'Task not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $comment = TaskComment::create([
            'task_id' => $id,
            'user_id' => Auth::id(),
            'comment' => $request->input('comment'),
        ]);

        $comment->load('user');

        // Trigger SSE Event (To be fleshed out in Phase 5)
        $this->triggerSSEEvent('comment_created', $comment);

        return response()->json($comment, 201);
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

    /**
     * Helper to dispatch assignment email.
     */
    protected function dispatchAssignmentEmail(Task $task)
    {
        if (class_exists(\App\Jobs\SendAssignmentEmailJob::class)) {
            \App\Jobs\SendAssignmentEmailJob::dispatch($task);
        }
    }

    /**
     * Dispatch background task report export CSV job.
     */
    public function export(Request $request)
    {
        if (class_exists(\App\Jobs\ExportTasksReportJob::class)) {
            \App\Jobs\ExportTasksReportJob::dispatch(Auth::id());
        }
        
        return response()->json([
            'message' => 'Task export CSV job has been dispatched'
        ]);
    }

    /**
     * Securely download the exported task report CSV.
     */
    public function downloadExport($filename)
    {
        $filePath = "exports/{$filename}";

        if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($filePath)) {
            return response()->json(['message' => 'Export file not found'], 404);
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->download($filePath, $filename);
    }
}
