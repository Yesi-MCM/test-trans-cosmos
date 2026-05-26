<p>Hello {{ $user->name }},</p>

<p>Your task report export is complete. You can download it using the link below:</p>

<p><a href="{{ url($downloadUrl) }}">Download Task Report ({{ $fileName }})</a></p>

<p>Best regards,<br>Task Manager Platform</p>
