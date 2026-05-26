# Architecture Decisions Document (ADR)

This document provides a breakdown of technical design choices, scalability considerations, and architectural decisions made for the TaskGrid platform.

---

## 1. Authentication: Custom JWT Guard vs. Heavy Dependencies
- **Decision:** Custom JWT Auth guard implemented with `firebase/php-jwt`.
- **Context:** To ensure seamless integration with Laravel 11's default guard design without introducing conflicts from heavy community packages (such as tymon/jwt-auth which often lag in Laravel version compatibility).
- **Implementation:** A custom `JwtService` handles signing, decoding, and validity checks. The custom `JwtGuard` implements Laravel's standard `Guard` interface. This allows us to use `$request->user()`, `Auth::id()`, and standard `auth:api` controller middlewares transparently.
- **Security Features:** Stateless authentication tokens check authorization headers, falling back safely to cookies (to allow secure downloads via regular links) or query parameters (to authenticate SSE EventSource streams natively).

---

## 2. Real-Time Architecture: Server-Sent Events (SSE) vs. WebSockets
- **Decision:** Use Server-Sent Events (SSE) via `/api/realtime/stream` for real-time synchronization.
- **Rationale:** 
  - **Simplicity:** WebSockets require running a persistent background WebSocket daemon (e.g. Pusher, Soketi, or custom node servers) which demands opening custom ports (e.g. 6001), managing complex reverse proxies, and setting up firewalls.
  - **Native Browser Support:** SSE is supported natively by browsers out-of-the-box (`EventSource`). It runs over standard HTTP, eliminating CORS proxy issues and custom ports.
  - **One-Way Broadcasts:** The majority of real-time requirements in task boards are unidirectional (updates broadcasted from the server database, like card moves or exports finishing). One-way streaming is a perfect fit for SSE.
- **Implementation:** The endpoint uses Laravel's `StreamedResponse` to run a loop checking for new entries in a `realtime_events` event store table. Heartsbeats are sent every 5 seconds to keep connections alive. Presence checking runs every 5 seconds against a Redis/Memcached/File cache.

---

## 3. Large File Handling: Chunked Upload Protocol
- **Decision:** Implement sequential client-side slicing and chunked uploading for files exceeding 50MB.
- **Context:** Standard web servers (like Nginx, Apache) and PHP configurations restrict upload sizes through configurations like `upload_max_filesize` and `post_max_size` (usually defaulting to 2MB - 50MB). Raising this globally introduces DDoS vulnerabilities (payload size exhaustion).
- **Solution:**
  - On dropping/selecting a file, the frontend checks if the file is larger than 50MB.
  - If so, it slices the file into 5MB chunks (`Blob.prototype.slice()`).
  - It assigns a unique `upload_id` and uploads chunks sequentially to `POST /api/attachments/chunk`.
  - The backend stores chunks in a temporary directories named after the `upload_id`.
  - Once the final chunk reaches the server, the backend merges all chunks, deletes the temporary chunk files, registers the new version, and dispatches the background malware scan.

---

## 4. Background Job Processing & Status Feedback loop
- **Decision:** Offload intensive file processing, email, and reports compilation to a database-backed Queue, linking completions back to the frontend via the SSE event stream.
- **Processing Chains:**
  - **Emailing:** Task assignment dispatches `SendAssignmentEmailJob`.
  - **Attachments:** Files trigger `ProcessAttachmentJob` doing:
    - GD-based Image Thumbnailing (150x150 downscaling, maintaining alpha channels for PNG/GIF/WebP).
    - Malware simulation (blocking files containing signatures or files named `*infected*`).
    - Video Preparation (Adaptive streaming preparation logs).
  - **CSV Exports:** Large board reports compile in memory using streams, save securely as files, and send an email download link to the user.
- **Real-Time Hook:** Upon completion, background jobs insert a message into `RealtimeEvent`. The active SSE listener on the frontend catches the message (e.g., `attachment_updated` or `export_completed`) and updates the user interface instantly.

---

## 5. Database Schema & Index Optimization
- **Indexes:**
  - `tasks`: Indexes on `status`, `priority`, `assigned_user_id`, and `due_date` speed up compound filters and sorting.
  - `task_attachments`: Index on `task_id` speeds up modal retrieval queries.
  - `task_comments`: Index on `task_id` speeds up comments lists.
  - `realtime_events`: Autoincrement ID index supports the SSE query `where('id', '>', lastEventId)`.
