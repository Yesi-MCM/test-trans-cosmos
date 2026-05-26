# Full-Stack Developer Technical Assessment - Instructions

## Overview
This is a practical evaluation of your full-stack development skills, with a primary focus on PHP backend development.
You have **4-6 hours** to complete this assessment, which covers essential web development concepts including API development, database design, file handling, real-time features, and modern frontend integration.

## Project Requirements

### Core System: Task Management Platform
Build a simple task management system with real-time features, file attachments, and background processing capabilities.

---

## Part 1: Backend Development (PHP) - Primary Focus

### 1.1 Database Design & Setup (30 minutes)
**Requirements:**
Design and implement a MySQL database with the following entities:
- **Users**: id, name, email, password, role, created_at, updated_at
- **Tasks**: id, title, description, status, priority, assigned_user_id, created_by, due_date, created_at, updated_at
- **Task_attachments**: id, task_id, file_name, file_path, file_size, mime_type, uploaded_at
- **Task_comments**: id, task_id, user_id, comment, created_at

**Deliverables:**
- SQL schema file with proper indexes
- Database seeder with sample data (at least 5 users, 15 tasks, 10 comments)

### 1.2 RESTful API Development (90 minutes)
Build a PHP REST API (use any framework: Laravel, Symfony, or vanilla PHP) with the following endpoints:

#### Authentication Endpoints:
- `POST /api/auth/login` - User authentication with JWT
- `POST /api/auth/logout` - Logout user
- `GET /api/auth/me` - Get current user info

#### Task Management Endpoints:
- `GET /api/tasks` - List tasks with pagination, filtering, and sorting
- `POST /api/tasks` - Create new task
- `PUT /api/tasks/{id}` - Update task
- `DELETE /api/tasks/{id}` - Delete task

#### File Upload Endpoints:
- `POST /api/tasks/{id}/attachments` - Upload file attachment
- `GET /api/attachments/{id}/download` - Download attachment
- `DELETE /api/attachments/{id}` - Delete attachment

### 1.3 File Upload System (45 minutes)
**Requirements:**
- Implement secure file upload with validation
- Support multiple file types (images, documents, videos)
- File size limits and type restrictions
- Generate thumbnails for images
- Store files securely

**Advanced Challenges:**
- Handle large file uploads (chunked upload for files >50MB)
- Implement virus scanning simulation
- Create file versioning system

### 1.4 Background Job Processing (45 minutes)
Implement a queue system for background processing:

#### Queue Jobs:
- Email notifications when tasks are assigned
- Bulk task status updates
- File processing (thumbnail generation, virus scanning)
- Data export (CSV/PDF reports)

---

## Part 2: Frontend Development (1-1.5 hours)

### 2.1 Choose Your Framework
Select one of the following:
- **Next.js** (React-based)
- **Vue.js** with Nuxt.js
- **Vanilla JavaScript** with modern ES6+
- Others (your choices)

### 2.2 Frontend Requirements (90 minutes)
**Core Features:**
- User authentication (login/logout)
- Task dashboard with CRUD operations
- Real-time task updates using WebSockets or Server-Sent Events
- File upload with drag-and-drop interface
- Responsive design (mobile-friendly)

**Advanced Features:**
- Task filtering and search
- Real-time comments system
- Progress indicators for file uploads
- Toast notifications for user feedback

---

## Part 3: Bonus Challenges (Optional)

### 3.1 Video Streaming Challenge (30 minutes)
**Requirements:**
- Implement video upload and streaming capability
- Support for common video formats (MP4, WebM)
- Basic video player with controls
- Video thumbnail generation
- Adaptive streaming preparation (multiple quality levels)

### 3.2 Real-time Features (30 minutes)
**Requirements:**
- WebSocket implementation for real-time updates
- Live task status changes
- Real-time commenting system
- Online user presence indicators
- Typing indicators for comments

### 3.3 Performance Optimization (30 minutes)
**Requirements:**
- Implement caching strategy (Redis/Memcached)
- Database query optimization
- API response caching
- Image optimization and lazy loading
- Code splitting and lazy loading for frontend

---

## Part 4: Documentation & Testing (30-60 minutes)

### 4.1 Documentation Requirements
- **README.md** with setup instructions
- **API documentation** (Postman collection or OpenAPI)
- **Database schema** documentation
- **Architecture decisions** document
- **Deployment guide**

### 4.2 Testing Requirements
**Backend Testing:**
- Unit tests for critical business logic
- API endpoint testing
- Database integration tests
- File upload testing

**Frontend Testing:**
- Component unit tests
- Integration tests for API calls
- E2E tests for critical user flows

---

## Submission Guidelines

### What to Submit:
1. **Source Code**: Complete project with clear folder structure
2. **Database**: SQL dump with schema and sample data
3. **Documentation**: All required documentation files
4. **Demo**: Screen recording or live demo link (optional but preferred)
5. **Deployment**: Hosted version (optional but preferred)

### Project Structure:
```
project-root/
├── backend/
│   ├── src/
│   ├── config/
│   ├── database/
│   ├── tests/
│   └── README.md
├── frontend/
│   ├── src/
│   ├── public/
│   ├── tests/
│   └── README.md
├── README.md
└── documentation/
    ├── api-docs/
    ├── architecture.md
    └── setup-guide.md
```

---

## Time Management Tips

### Hour 1-2: Backend Foundation
- Set up project structure and database
- Implement basic authentication
- Create core CRUD endpoints

### Hour 3-4: Advanced Backend Features
- File upload system
- Queue implementation
- API refinement and testing

### Hour 5: Frontend Development
- Set up frontend framework
- Implement core UI components
- API integration

### Hour 6: Polish & Documentation
- Real-time features
- Testing
- Documentation
- Final refinements

---

## Additional Opportunities

### Extra Features (if time allows):
- CI/CD pipeline setup
- Advanced security features (2FA, OAuth)
- Multi-language support (i18n)
- Advanced caching strategies
- Microservices architecture implementation

### Framework-Specific Features:
- **Laravel**: Eloquent relationships, middlewares, service providers
- **Next.js**: SSR/SSG implementation, API routes, middleware
- **Symfony**: Dependency injection, event dispatchers, console commands

---

## Technical Notes

### Minimum Requirements:
- Working authentication system
- Basic CRUD operations for tasks
- File upload functionality
- Simple frontend interface
- Basic documentation

---

*Note: This assessment is designed to showcase your technical abilities. Focus on clean, well-structured code and proper documentation. Quality over quantity - it's better to have fewer features that work well than many features that are incomplete.*