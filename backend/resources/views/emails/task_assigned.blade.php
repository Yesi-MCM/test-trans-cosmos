<p>Hello {{ $task->assignedUser->name }},</p>

<p>You have been assigned a new task: <strong>{{ $task->title }}</strong> (Priority: {{ $task->priority }}).</p>

<p>Please check your dashboard for details.</p>

<p>Best regards,<br>Task Manager Platform</p>
