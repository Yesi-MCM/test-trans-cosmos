# Setup and Running Guide

This guide provides detailed setup instructions to configure, run, and verify both the Laravel API backend and the Next.js frontend of TaskGrid locally.

---

## 1. Backend Setup (Laravel)

### Prerequisites
- PHP >= 8.2 with extensions loaded: `gd`, `pdo_mysql`, `openssl`, `mbstring`
- Composer (PHP Dependency Manager)
- MySQL / MariaDB (e.g. through Laragon, XAMPP, or standalone)

### Installation Steps

1. **Navigate to the Backend Directory:**
   ```bash
   cd backend
   ```

2. **Install Composer Dependencies:**
   ```bash
   composer install
   ```

3. **Configure Environment:**
   - Copy `.env.example` to `.env`:
     ```bash
     cp .env.example .env
     ```
   - Update database credentials in `.env`:
     ```env
     DB_CONNECTION=mysql
     DB_HOST=127.0.0.1
     DB_PORT=3306
     DB_DATABASE=test_trans_cosmos
     DB_USERNAME=root
     DB_PASSWORD=
     ```
   - Update queue and mail settings to process jobs locally:
     ```env
     QUEUE_CONNECTION=database
     MAIL_MAILER=log
     ```

4. **Generate Application Key:**
   ```bash
   php artisan key:generate
   ```

5. **Generate JWT Secret:**
   - The custom JWT system expects a configuration entry in `config/auth.php` or a fallback. It uses the `JWT_SECRET` key in `.env`.
   - Add a custom `JWT_SECRET` to `.env`:
     ```env
     JWT_SECRET=your-super-secret-random-key-at-least-32-chars-long
     ```

6. **Run Database Migrations and Seeders:**
   - Create the database `test_trans_cosmos` in MySQL.
   - Run the migrations and seed mock users, tasks, and comments:
     ```bash
     php artisan migrate --seed
     ```
     This will seed 5 default users (roles: admin, manager, member) and 15 tasks.
   - **Alternative (Direct SQL Import):**
     You can import the database schema and sample data dump directly:
     ```bash
     mysql -u root -p test_trans_cosmos < database/schema.sql
     ```

7. **Start Queue Worker:**
   - The platform relies on a database queue to process attachments (thumbnails, malware scanning) and report exports. Keep a terminal window open running:
     ```bash
     php artisan queue:work
     ```

8. **Start Laravel Serve (Handling SSE Concurrency):**
   Since Server-Sent Events (SSE) opens a persistent connection, running a single-threaded PHP server blocks all other concurrent requests (causing app hangs). To run concurrently:
   
   - **On Windows (using Laragon's built-in Apache/Nginx):**
     Windows PHP built-in server does **not** support process forking (`forking is not supported on this platform`). 
     Instead, use Laragon's multi-threaded Apache or Nginx server directly:
     1. Make sure Apache/Nginx is started in the Laragon control panel.
     2. Access the API via Laragon's local path: `http://localhost/test-trans-cosmos/backend/public/api` (or `http://test-trans-cosmos.test/backend/public/api`).
     3. Update your frontend configuration (`frontend/.env.local`) to point to this Laragon API URL:
        ```env
        NEXT_PUBLIC_API_URL=http://localhost/test-trans-cosmos/backend/public/api
        ```
   
   - **On Linux / macOS (using PHP CLI workers):**
     You can run the PHP built-in server with concurrent worker processes:
     ```bash
     PHP_CLI_SERVER_WORKERS=5 php artisan serve --port=8000 --no-reload
     ```

---

## 2. Frontend Setup (Next.js)

### Prerequisites
- Node.js >= 18
- npm (Node Package Manager)

### Installation Steps

1. **Navigate to the Frontend Directory:**
   ```bash
   cd ../frontend
   ```

2. **Install Node Dependencies:**
   ```bash
   npm install
   ```

3. **Configure Environment:**
   - By default, the frontend service connects to the backend API at `http://localhost:8000/api`. If your backend runs on a different port, create a `.env.local` inside `frontend/` containing:
     ```env
     NEXT_PUBLIC_API_URL=http://localhost:8000/api
     ```

4. **Start Development Server:**
   ```bash
   npm run dev
   ```
   The dashboard will now be accessible at `http://localhost:3000`.

---

## 3. Running Tests

### Backend Tests (PHPUnit)
- Navigate to the `backend` directory and run:
  ```bash
  php artisan test
  ```
  All 29 tests validating JWT guard, task CRUD, attachments uploads, SSE queues, and exports should pass.

### Frontend Tests (Vitest)
- Navigate to the `frontend` directory and run:
  ```bash
  npm run test
  ```
  or
  ```bash
  npx vitest run
  ```
  This executes unit tests verifying cookie handlers and API integration settings.

---

## 4. Verification Workflow

1. Open `http://localhost:3000/login` in your browser.
2. Sign in with seed credentials (emails can be fetched from the database seeder `DatabaseSeeder.php` or `users` table):
   - Example Email: `admin@example.com`
   - Default Password: `password`
3. Try dragging cards between columns (To Do -> In Progress -> Review -> Completed) and verify that changes persist.
4. Click on a task to view comments, add a new comment (triggers typing indicators), or upload a file.
5. Drag and drop file attachments:
   - Files <= 50MB will use the standard upload route.
   - Files > 50MB will automatically chunk and show upload percentages.
   - If the file name contains `"infected"` (e.g. `infected_file.txt`), the simulated background scanning job will flag it as **Infected** and disable downloading.
6. Click **CSV Report Export** in the top bar. You will receive a toast alert containing a clickable download link as soon as the background queue completes compilation.

---

## 5. Troubleshooting & Concurrent Connections

### App Hangs / Unresponsive API Requests
- **Symptom:** The frontend UI is stuck, cards cannot be dragged, comments won't post, and clicking "Save" does nothing. 
- **Cause:** This is caused by the built-in single-threaded PHP server (`php artisan serve`). When the React client opens the persistent SSE connection at `/api/realtime/stream`, the PHP server dedicates its only execution thread to that streaming loop. All subsequent API calls (like `/realtime/presence` or saving details) are queued indefinitely.
- **Solution:** 
  - **On Windows:**
    Because Windows PHP does not support process forking, `PHP_CLI_SERVER_WORKERS` will fail with the error `forking is not supported on this platform`.
    **You must use Laragon's built-in Apache/Nginx web server instead:**
    1. Open Laragon, make sure the Apache/Nginx server is started.
    2. Serve your backend through the local virtual path: `http://localhost/test-trans-cosmos/backend/public/api` (or `http://test-trans-cosmos.test/backend/public/api`).
    3. Update the frontend environment configuration in `frontend/.env.local`:
       ```env
       NEXT_PUBLIC_API_URL=http://localhost/test-trans-cosmos/backend/public/api
       ```
  - **On Linux / macOS:**
    Configure the environment variable `PHP_CLI_SERVER_WORKERS` to spawn multiple worker processes and bypass hot-reloading (using the `--no-reload` flag) before starting the server:
    ```bash
    PHP_CLI_SERVER_WORKERS=5 php artisan serve --port=8000 --no-reload
    ```
