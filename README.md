# TaskGrid - Full-Stack Task Collaboration Platform

TaskGrid is a high-performance, real-time, state-of-the-art task management application. The platform features a Laravel API backend powering real-time synchronization, queue-based background processing, custom JWT authentication, and secure, chunked large file uploads, integrated with a Next.js (TypeScript) Kanban dashboard styled with responsive Vanilla CSS Modules and custom dark/light theme options.

---

## Key Features

1. **RESTful API Backend (Laravel)**
   - Custom JWT Authentication guard mapping stateless tokens seamlessly to standard `Auth::user()` queries.
   - Task management endpoints supporting pagination, complex filtering (status, priority, assignee), textual search, sorting, and bulk actions.
   - Secure attachment download system verifying JWT auth and filtering out blocked/infected files.
   - Real-time Server-Sent Events (SSE) server streaming data updates without heavy third-party WebSocket server overhead.

2. **Interactive Kanban Dashboard (Next.js & React 19)**
   - Beautiful visual layout styled using CSS variables, background mesh glows, and glassmorphism.
   - HTML5 Drag-and-drop interfaces enabling intuitive card status changes.
   - Search/filter controls (textual search, priority filters, assignee filters, sort order).
   - Real-time SSE updates for card movements, creation, deletion, and comment additions.
   - Live presence list showing online team members, alongside comment-typing indicators ("X is typing...").
   - Integrated toast notifications for background actions (such as CSV export completion).
   - Dynamic dark/light theme switching.

3. **Background Jobs & CSV Exports**
   - Background email notification triggers when a user is assigned a task.
   - Asynchronous malware scanning simulation, GD-based image thumbnail generation, and adaptive bitrate video preparation.
   - Background CSV report generation triggered via the dashboard, pushing a download notification to the user via SSE when ready.
   - Secure download endpoint for exports.

---

## Project Structure

```
project-root/
├── backend/                  # Laravel REST API application
│   ├── app/                  # Application Core (Controllers, Models, Jobs, Services)
│   ├── database/             # Migrations, Seeders, SQL Schemas
│   ├── tests/                # Feature & Unit PHPUnit test suites
│   └── .env                  # Environment configurations
├── frontend/                 # Next.js App Router SPA
│   ├── src/
│   │   ├── app/              # Router Pages, Layouts, CSS Modules
│   │   ├── components/       # UI Components (TaskCard, Modals)
│   │   ├── context/          # State Providers (Auth, Realtime/SSE)
│   │   └── services/         # API fetch wrappers & tests
│   └── package.json          # Node dependencies & Vitest setup
└── documentation/            # Deployment and Technical Docs
    ├── architecture.md       # ADR & Architecture explanations
    ├── setup-guide.md        # Step-by-step setup guide
    └── api-docs/
        ├── api-docs.json     # OpenAPI 3.0 specification
        └── postman_collection.json # Postman v2.1 API request collection
```

---

## Quick Start

For detailed step-by-step setup, configuration, database seeding, queue workers, and execution instructions, please refer to the following documents:
- 📖 [Setup & Running Guide](file:///c:/laragon/www/test-trans-cosmos/documentation/setup-guide.md)
- 📖 [Architecture Decisions Document](file:///c:/laragon/www/test-trans-cosmos/documentation/architecture.md)
- 📖 [OpenAPI 3.0 API Specification](file:///c:/laragon/www/test-trans-cosmos/documentation/api-docs/api-docs.json)
- 📖 [Postman API Collection](file:///c:/laragon/www/test-trans-cosmos/documentation/api-docs/postman_collection.json)
