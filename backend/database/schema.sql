-- MySQL Dump of TaskGrid Schema & Sample Data
-- Database: `test_trans_cosmos`
-- Generated: 2026-05-26

CREATE DATABASE IF NOT EXISTS `test_trans_cosmos` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `test_trans_cosmos`;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'member',
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_index` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dumping data for table `users`
-- --------------------------------------------------------

LOCK TABLES `users` WRITE;
-- Default password: 'password' (hashed using bcrypt)
INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `role`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Admin User', 'admin@taskmanager.com', NULL, '$2y$12$t4zHqU3.4pP7H3N9b7UvDeiK9e7yD2mJ2qX5684Ea8F6k84h5hG2y', 'admin', NULL, NOW(), NOW()),
(2, 'John Member', 'john@taskmanager.com', NULL, '$2y$12$t4zHqU3.4pP7H3N9b7UvDeiK9e7yD2mJ2qX5684Ea8F6k84h5hG2y', 'member', NULL, NOW(), NOW()),
(3, 'Jane Member', 'jane@taskmanager.com', NULL, '$2y$12$t4zHqU3.4pP7H3N9b7UvDeiK9e7yD2mJ2qX5684Ea8F6k84h5hG2y', 'member', NULL, NOW(), NOW()),
(4, 'Alex Member', 'alex@taskmanager.com', NULL, '$2y$12$t4zHqU3.4pP7H3N9b7UvDeiK9e7yD2mJ2qX5684Ea8F6k84h5hG2y', 'member', NULL, NOW(), NOW()),
(5, 'Sarah Member', 'sarah@taskmanager.com', NULL, '$2y$12$t4zHqU3.4pP7H3N9b7UvDeiK9e7yD2mJ2qX5684Ea8F6k84h5hG2y', 'member', NULL, NOW(), NOW());
UNLOCK TABLES;

-- --------------------------------------------------------
-- Table structure for table `tasks`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'todo',
  `priority` varchar(255) NOT NULL DEFAULT 'medium',
  `assigned_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `due_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tasks_status_index` (`status`),
  KEY `tasks_priority_index` (`priority`),
  KEY `tasks_due_date_index` (`due_date`),
  KEY `tasks_assigned_user_id_foreign` (`assigned_user_id`),
  KEY `tasks_created_by_foreign` (`created_by`),
  CONSTRAINT `tasks_assigned_user_id_foreign` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dumping data for table `tasks`
-- --------------------------------------------------------

LOCK TABLES `tasks` WRITE;
INSERT INTO `tasks` (`id`, `title`, `description`, `status`, `priority`, `assigned_user_id`, `created_by`, `due_date`, `created_at`, `updated_at`) VALUES
(1, 'Design System Architecture', 'Create a detailed design document for the task management system backend and frontend connection.', 'completed', 'high', 2, 1, DATE_ADD(NOW(), INTERVAL 2 DAY), NOW(), NOW()),
(2, 'Setup MySQL Database Schema', 'Install migrations and define table schemas with appropriate foreign keys and indexes.', 'completed', 'high', 3, 1, DATE_ADD(NOW(), INTERVAL 3 DAY), NOW(), NOW()),
(3, 'Implement JWT Authentication', 'Develop secure custom guard and middleware for login, logout, and authenticated me endpoint.', 'in_progress', 'high', 2, 1, DATE_ADD(NOW(), INTERVAL 5 DAY), NOW(), NOW()),
(4, 'Build Task CRUD REST APIs', 'Develop GET, POST, PUT, and DELETE endpoints with robust server-side validation and search filters.', 'in_progress', 'medium', 3, 1, DATE_ADD(NOW(), INTERVAL 7 DAY), NOW(), NOW()),
(5, 'File Upload and Thumbnail Generation', 'Build secure attachment upload, generate 150x150 image thumbnails in background, and save versions.', 'todo', 'medium', 4, 1, DATE_ADD(NOW(), INTERVAL 10 DAY), NOW(), NOW()),
(6, 'Implement Chunked Large File Upload', 'Support chunked uploading for files exceeding 50MB, merging fragments sequentially on completion.', 'todo', 'high', 5, 1, DATE_ADD(NOW(), INTERVAL 12 DAY), NOW(), NOW()),
(7, 'Simulated Virus Scanning Integration', 'Create a virus scan check that flags infected attachments matching test signatures.', 'todo', 'low', 4, 1, DATE_ADD(NOW(), INTERVAL 15 DAY), NOW(), NOW()),
(8, 'Background Queue Job Notifications', 'Configure queue worker to send logged email notifications to users when tasks are assigned.', 'todo', 'medium', NULL, 1, DATE_ADD(NOW(), INTERVAL 8 DAY), NOW(), NOW()),
(9, 'Data Export Background Worker', 'Create a background worker that exports all tasks into a CSV file and emails a download link.', 'todo', 'low', 5, 1, DATE_ADD(NOW(), INTERVAL 20 DAY), NOW(), NOW()),
(10, 'Real-time Server-Sent Events', 'Build an SSE streaming endpoint that broadcasts board status updates, comments, and presence indicators.', 'in_progress', 'high', 2, 1, DATE_ADD(NOW(), INTERVAL 4 DAY), NOW(), NOW()),
(11, 'Write Backend PHPUnit Integration Tests', 'Write robust unit and integration tests to verify API endpoints, authentication, and job dispatching.', 'todo', 'medium', 3, 1, DATE_ADD(NOW(), INTERVAL 14 DAY), NOW(), NOW()),
(12, 'Optimize API Query Performance', 'Implement Redis key-value store to cache task list and avoid hitting SQL database on redundant requests.', 'todo', 'low', NULL, 1, DATE_ADD(NOW(), INTERVAL 25 DAY), NOW(), NOW()),
(13, 'Build Next.js Frontend Layout', 'Initialize Next.js application, write custom CSS modules, and design dynamic glassmorphic views.', 'in_progress', 'medium', 5, 1, DATE_ADD(NOW(), INTERVAL 6 DAY), NOW(), NOW()),
(14, 'Drag-and-Drop Board Interactions', 'Build an interactive Kanban board allowing smooth drag and drop of tasks across board columns.', 'todo', 'high', 4, 1, DATE_ADD(NOW(), INTERVAL 9 DAY), NOW(), NOW()),
(15, 'Implement Presence and Typing Indicators', 'Track active online users in frontend using SSE and display typing indicators on comments.', 'todo', 'low', 2, 1, DATE_ADD(NOW(), INTERVAL 11 DAY), NOW(), NOW());
UNLOCK TABLES;

-- --------------------------------------------------------
-- Table structure for table `task_comments`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `task_comments`;
CREATE TABLE `task_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_comments_task_id_foreign` (`task_id`),
  KEY `task_comments_user_id_foreign` (`user_id`),
  CONSTRAINT `task_comments_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dumping data for table `task_comments`
-- --------------------------------------------------------

LOCK TABLES `task_comments` WRITE;
INSERT INTO `task_comments` (`id`, `task_id`, `user_id`, `comment`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'Let\'s make sure we write clean code and document the API structure.', NOW(), NOW()),
(2, 2, 3, 'Working on this today, will update status when migrations run.', NOW(), NOW()),
(3, 3, 4, 'Should we use Sanctum or a custom JWT encoder?', NOW(), NOW()),
(4, 4, 5, 'Custom JWT is better to show control over security headers.', NOW(), NOW()),
(5, 5, 2, 'Need to configure GD extension in php.ini before processing images.', NOW(), NOW()),
(6, 6, 3, 'Is the chunk size for uploading set to 5MB or larger?', NOW(), NOW()),
(7, 7, 4, 'Let\'s set chunk size to 5MB, it works well with Laragon defaults.', NOW(), NOW()),
(8, 8, 5, 'Virus scan simulation should support the EICAR test string.', NOW(), NOW()),
(9, 9, 2, 'The dashboard design is coming along nicely, glassmorphism looks premium!', NOW(), NOW()),
(10, 10, 3, 'Will start writing unit tests for tasks API tomorrow.', NOW(), NOW());
UNLOCK TABLES;

-- --------------------------------------------------------
-- Table structure for table `task_attachments`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `task_attachments`;
CREATE TABLE `task_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `mime_type` varchar(255) NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `status` varchar(255) NOT NULL DEFAULT 'ready',
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `task_attachments_task_id_foreign` (`task_id`),
  KEY `task_attachments_status_index` (`status`),
  CONSTRAINT `task_attachments_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `realtime_events`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `realtime_events`;
CREATE TABLE `realtime_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(255) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `realtime_events_event_type_index` (`event_type`),
  KEY `realtime_events_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `password_reset_tokens`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `sessions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `jobs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `cache`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
