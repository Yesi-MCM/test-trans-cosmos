'use client';

import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useAuth } from '@/context/AuthContext';
import { useRealtime } from '@/context/RealtimeContext';
import { apiRequest, getCookie, API_URL } from '@/services/api';
import TaskCard from '@/components/TaskCard';
import CreateTaskModal from '@/components/CreateTaskModal';
import TaskDetailsModal from '@/components/TaskDetailsModal';
import styles from './page.module.css';

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
}

interface Task {
  id: number;
  title: string;
  description: string | null;
  status: 'todo' | 'in_progress' | 'review' | 'completed';
  priority: 'low' | 'medium' | 'high';
  assigned_user_id: number | null;
  assigned_user?: User | null;
  created_by: number;
  due_date: string | null;
  created_at: string;
  updated_at: string;
}

interface Toast {
  id: string;
  message: string;
  type: 'success' | 'error' | 'info';
  downloadUrl?: string;
}

export default function Dashboard() {
  const { user, logout } = useAuth();
  const { onlineUsers, registerListener } = useRealtime();

  // State
  const [tasks, setTasks] = useState<Task[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Filters & Sorting
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [priorityFilter, setPriorityFilter] = useState('');
  const [assigneeFilter, setAssigneeFilter] = useState('');
  const [sortBy, setSortBy] = useState('created_at');
  const [sortOrder, setSortOrder] = useState('desc');

  // Drag over visual state
  const [draggedOverColumn, setDraggedOverColumn] = useState<string | null>(null);

  // Bulk mode
  const [isBulkMode, setIsBulkMode] = useState(false);
  const [selectedTaskIds, setSelectedTaskIds] = useState<number[]>([]);
  const [bulkStatus, setBulkStatus] = useState<'todo' | 'in_progress' | 'review' | 'completed'>('todo');
  const [isBulkProcessing, setIsBulkProcessing] = useState(false);

  // Modals
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [selectedTaskId, setSelectedTaskId] = useState<number | null>(null);

  // Toasts
  const [toasts, setToasts] = useState<Toast[]>([]);

  // Theme
  const [theme, setTheme] = useState<'light' | 'dark'>('dark');

  const token = getCookie('token') || (typeof window !== 'undefined' ? localStorage.getItem('token') : '');

  // 1. Toast helper
  const addToast = useCallback((message: string, type: 'success' | 'error' | 'info' = 'success', downloadUrl?: string) => {
    const id = `${Date.now()}-${Math.random()}`;
    setToasts((prev) => [...prev, { id, message, type, downloadUrl }]);

    // Auto dismiss after 6s
    setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== id));
    }, 6000);
  }, []);

  // 2. Fetch Tasks and Users list
  const fetchTasks = useCallback(async () => {
    try {
      setLoading(true);
      
      // Build query string
      const params = new URLSearchParams();
      if (search) params.append('search', search);
      if (statusFilter) params.append('status', statusFilter);
      if (priorityFilter) params.append('priority', priorityFilter);
      if (assigneeFilter) params.append('assigned_user_id', assigneeFilter);
      params.append('sort_by', sortBy);
      params.append('sort_order', sortOrder);
      params.append('per_page', '100'); // Get up to 100 for boards

      const data = await apiRequest(`/tasks?${params.toString()}`);
      // Laravel paginate returns list inside `data` key
      setTasks(data.data || []);
      setError(null);
    } catch (err: any) {
      console.error('Failed to fetch tasks:', err);
      setError(err.message || 'Failed to fetch tasks.');
    } finally {
      setLoading(false);
    }
  }, [search, statusFilter, priorityFilter, assigneeFilter, sortBy, sortOrder]);

  const fetchUsers = useCallback(async () => {
    try {
      const data = await apiRequest('/users');
      setUsers(data || []);
    } catch (err) {
      console.error('Failed to fetch users:', err);
    }
  }, []);

  // 3. Load on filters change
  useEffect(() => {
    fetchTasks();
  }, [fetchTasks]);

  useEffect(() => {
    fetchUsers();
    
    // Check local storage for theme preference
    if (typeof window !== 'undefined') {
      const savedTheme = localStorage.getItem('theme') as 'light' | 'dark' | null;
      if (savedTheme) {
        setTheme(savedTheme);
        document.documentElement.setAttribute('data-theme', savedTheme);
      } else {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    }
  }, [fetchUsers]);

  // 4. Toggle theme
  const toggleTheme = () => {
    const nextTheme = theme === 'dark' ? 'light' : 'dark';
    setTheme(nextTheme);
    localStorage.setItem('theme', nextTheme);
    document.documentElement.setAttribute('data-theme', nextTheme);
  };

  // 5. Register real-time SSE listeners
  useEffect(() => {
    const unsubCreated = registerListener('task_created', (newTask) => {
      addToast(`New task created: "${newTask.title}"`, 'success');
      setTasks((prev) => {
        if (prev.some((t) => t.id === newTask.id)) return prev;
        return [newTask, ...prev];
      });
    });

    const unsubUpdated = registerListener('task_updated', (updatedTask) => {
      // Avoid spamming toast if status update was our own action
      setTasks((prev) => prev.map((t) => (t.id === updatedTask.id ? updatedTask : t)));
    });

    const unsubDeleted = registerListener('task_deleted', (payload) => {
      setTasks((prev) => prev.filter((t) => t.id !== payload.id));
    });

    const unsubExport = registerListener('export_completed', (payload) => {
      if (payload.user_id === user?.id) {
        const fullDownloadUrl = `${API_URL}${payload.download_url}?token=${encodeURIComponent(token || '')}`;
        addToast(`Your CSV export report is ready!`, 'success', fullDownloadUrl);
      }
    });

    return () => {
      unsubCreated();
      unsubUpdated();
      unsubDeleted();
      unsubExport();
    };
  }, [registerListener, user, token, addToast]);

  // 6. Drag and drop handlers
  const handleDragStart = (e: React.DragEvent, taskId: number) => {
    if (isBulkMode) {
      e.preventDefault();
      return; // Disable drag during bulk selection to avoid confusion
    }
    e.dataTransfer.setData('text/plain', String(taskId));
  };

  const handleDragOver = (e: React.DragEvent, columnStatus: string) => {
    e.preventDefault();
    setDraggedOverColumn(columnStatus);
  };

  const handleDragLeave = () => {
    setDraggedOverColumn(null);
  };

  const handleDrop = async (e: React.DragEvent, targetStatus: 'todo' | 'in_progress' | 'review' | 'completed') => {
    e.preventDefault();
    setDraggedOverColumn(null);
    const taskIdStr = e.dataTransfer.getData('text/plain');
    if (!taskIdStr) return;

    const taskId = parseInt(taskIdStr);

    // Optimistically update status in local state
    setTasks((prev) =>
      prev.map((t) => (t.id === taskId ? { ...t, status: targetStatus } : t))
    );

    try {
      await apiRequest(`/tasks/${taskId}`, {
        method: 'PUT',
        body: JSON.stringify({ status: targetStatus }),
      });
      addToast('Task status updated successfully', 'success');
    } catch (err: any) {
      console.error('Failed to update task status:', err);
      addToast(err.message || 'Failed to update task status.', 'error');
      // Re-fetch to sync actual state in case of error
      fetchTasks();
    }
  };

  // 7. Bulk selection handlers
  const handleToggleSelectTask = (taskId: number) => {
    setSelectedTaskIds((prev) => {
      if (prev.includes(taskId)) {
        return prev.filter((id) => id !== taskId);
      } else {
        return [...prev, taskId];
      }
    });
  };

  const handleApplyBulkStatus = async () => {
    if (selectedTaskIds.length === 0) return;
    setIsBulkProcessing(true);

    try {
      await apiRequest('/tasks/bulk-status', {
        method: 'POST',
        body: JSON.stringify({
          task_ids: selectedTaskIds,
          status: bulkStatus,
        }),
      });

      addToast(`Bulk update queued for ${selectedTaskIds.length} tasks!`, 'info');
      
      // Optimistically update locally
      setTasks((prev) =>
        prev.map((t) =>
          selectedTaskIds.includes(t.id) ? { ...t, status: bulkStatus } : t
        )
      );

      // Clean up selection
      setSelectedTaskIds([]);
      setIsBulkMode(false);
    } catch (err: any) {
      console.error('Bulk update failed:', err);
      addToast(err.message || 'Bulk update failed.', 'error');
    } finally {
      setIsBulkProcessing(false);
    }
  };

  // 8. Trigger CSV export
  const handleTriggerExport = async () => {
    try {
      await apiRequest('/tasks/export', { method: 'POST' });
      addToast('CSV export requested. You will receive a download link here when it completes.', 'info');
    } catch (err: any) {
      console.error('Export failed:', err);
      addToast(err.message || 'Failed to trigger CSV export.', 'error');
    }
  };

  // 9. Separate tasks into columns
  const getTasksByStatus = (status: 'todo' | 'in_progress' | 'review' | 'completed') => {
    return tasks.filter((t) => t.status === status);
  };

  const columns: Array<{ status: 'todo' | 'in_progress' | 'review' | 'completed'; label: string }> = [
    { status: 'todo', label: 'To Do' },
    { status: 'in_progress', label: 'In Progress' },
    { status: 'review', label: 'Review' },
    { status: 'completed', label: 'Completed' },
  ];

  return (
    <div className={styles.dashboard}>
      {/* Top Header */}
      <header className={styles.header}>
        <div className={styles.logoSection}>
          <h1 className={styles.logoText}>TaskSphere</h1>
          <span style={{ fontSize: '11px', color: 'var(--text-muted)', background: 'var(--bg-tertiary)', padding: '2px 8px', borderRadius: '10px', fontWeight: 'bold' }}>COLLAB</span>
        </div>

        <div className={styles.userInfo}>
          {user && (
            <>
              <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end' }}>
                <span className={styles.userName}>{user.name}</span>
                <span className={styles.userRole}>{user.role}</span>
              </div>
              <div className={styles.headerActions}>
                <button type="button" className={styles.themeToggleBtn} onClick={toggleTheme}>
                  {theme === 'dark' ? '☀️ Light' : '🌙 Dark'}
                </button>
                <button type="button" className={styles.logoutBtn} onClick={logout}>
                  Sign Out
                </button>
              </div>
            </>
          )}
        </div>
      </header>

      {/* Online Users Presence Bar */}
      <div className={styles.presenceBar}>
        <span className={styles.presenceTitle}>Active Now:</span>
        <div className={styles.onlineUsersList}>
          {onlineUsers.length === 0 ? (
            <span style={{ color: 'var(--text-muted)', fontSize: '12px', fontStyle: 'italic' }}>Only you are online</span>
          ) : (
            onlineUsers.map((u) => (
              <span key={u.id} className={styles.onlineUserDot} title={`${u.name} (${u.email})`}>
                <span className={styles.onlineDot} />
                {u.name} {u.id === user?.id && '(You)'}
              </span>
            ))
          )}
        </div>
      </div>

      {/* Control Panel (Search, Filters, Create, Export) */}
      <section className={styles.controlPanel}>
        <div className={styles.filterRow}>
          {/* Search box */}
          <div className={styles.searchGroup}>
            <input
              type="text"
              placeholder="Search by title or description..."
              className={styles.searchInput}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>

          {/* Filtering selectors */}
          <div className={styles.filterControls}>
            {/* Priority filter */}
            <select
              className={styles.filterSelect}
              value={priorityFilter}
              onChange={(e) => setPriorityFilter(e.target.value)}
            >
              <option value="">All Priorities</option>
              <option value="low">Low Priority</option>
              <option value="medium">Medium Priority</option>
              <option value="high">High Priority</option>
            </select>

            {/* Assignee filter */}
            <select
              className={styles.filterSelect}
              value={assigneeFilter}
              onChange={(e) => setAssigneeFilter(e.target.value)}
            >
              <option value="">All Assignees</option>
              <option value="0">Unassigned</option>
              {users.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name}
                </option>
              ))}
            </select>

            {/* Sorting */}
            <select
              className={styles.filterSelect}
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value)}
            >
              <option value="created_at">Date Created</option>
              <option value="due_date">Due Date</option>
              <option value="priority">Priority</option>
              <option value="title">Title</option>
            </select>

            <select
              className={styles.filterSelect}
              value={sortOrder}
              onChange={(e) => setSortOrder(e.target.value)}
            >
              <option value="desc">Descending</option>
              <option value="asc">Ascending</option>
            </select>
          </div>
        </div>

        <div className={styles.actionRow}>
          <div className={styles.actionButtons}>
            <button
              type="button"
              className={styles.btnPrimary}
              onClick={() => setIsCreateModalOpen(true)}
            >
              + Create Task
            </button>
            <button
              type="button"
              className={styles.btnSecondary}
              onClick={handleTriggerExport}
            >
              CSV Report Export
            </button>
            <button
              type="button"
              className={styles.btnSecondary}
              onClick={() => {
                setIsBulkMode(!isBulkMode);
                setSelectedTaskIds([]);
              }}
            >
              {isBulkMode ? 'Cancel Selection' : 'Bulk Action Mode'}
            </button>
          </div>

          {/* Bulk Action Controls */}
          {isBulkMode && (
            <div className={styles.bulkBar}>
              <span className={styles.bulkInfo}>
                Selected <strong>{selectedTaskIds.length}</strong> tasks
              </span>
              <div className={styles.bulkActions}>
                <select
                  className={styles.filterSelect}
                  value={bulkStatus}
                  onChange={(e) => setBulkStatus(e.target.value as any)}
                  style={{ background: 'var(--bg-secondary)', padding: '8px 12px' }}
                >
                  <option value="todo">Move to: To Do</option>
                  <option value="in_progress">Move to: In Progress</option>
                  <option value="review">Move to: Review</option>
                  <option value="completed">Move to: Completed</option>
                </select>
                <button
                  type="button"
                  className={styles.btnPrimary}
                  style={{ padding: '8px 16px' }}
                  onClick={handleApplyBulkStatus}
                  disabled={selectedTaskIds.length === 0 || isBulkProcessing}
                >
                  {isBulkProcessing ? 'Processing...' : 'Apply Status'}
                </button>
              </div>
            </div>
          )}
        </div>
      </section>

      {/* Main Kanban Board */}
      {loading && tasks.length === 0 ? (
        <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '15px', color: 'var(--text-secondary)' }}>
          Loading your workspaces...
        </div>
      ) : (
        <main className={styles.board}>
          {columns.map((col) => {
            const colTasks = getTasksByStatus(col.status);
            const isDraggedOver = draggedOverColumn === col.status;

            return (
              <div
                key={col.status}
                className={`${styles.column} ${isDraggedOver ? styles.columnDragOver : ''}`}
                onDragOver={(e) => handleDragOver(e, col.status)}
                onDragLeave={handleDragLeave}
                onDrop={(e) => handleDrop(e, col.status)}
              >
                <div className={styles.columnHeader}>
                  <h3 className={styles.columnTitle}>
                    <span
                      style={{
                        width: '8px',
                        height: '8px',
                        borderRadius: '50%',
                        display: 'inline-block',
                        backgroundColor: `var(--status-${col.status})`,
                        boxShadow: `0 0 6px var(--status-${col.status})`,
                      }}
                    />
                    {col.label}
                  </h3>
                  <span className={styles.columnCount}>{colTasks.length}</span>
                </div>

                <div className={styles.columnBody}>
                  {colTasks.length === 0 ? (
                    <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '12px', color: 'var(--text-muted)', border: '1px dashed var(--border-color)', borderRadius: 'var(--radius-sm)', minHeight: '150px', fontStyle: 'italic' }}>
                      Drag cards here
                    </div>
                  ) : (
                    colTasks.map((t) => (
                      <div key={t.id} className={styles.cardContainer}>
                        {isBulkMode && (
                          <input
                            type="checkbox"
                            className={styles.cardCheckbox}
                            checked={selectedTaskIds.includes(t.id)}
                            onChange={() => handleToggleSelectTask(t.id)}
                          />
                        )}
                        <div style={{ flex: 1 }}>
                          <TaskCard
                            task={t}
                            onClick={() => {
                              if (!isBulkMode) {
                                setSelectedTaskId(t.id);
                              } else {
                                handleToggleSelectTask(t.id);
                              }
                            }}
                            onDragStart={handleDragStart}
                          />
                        </div>
                      </div>
                    ))
                  )}
                </div>
              </div>
            );
          })}
        </main>
      )}

      {/* Modals */}
      {isCreateModalOpen && (
        <CreateTaskModal
          onClose={() => setIsCreateModalOpen(false)}
          onSuccess={(newTask) => {
            setIsCreateModalOpen(false);
            setTasks((prev) => [newTask, ...prev]);
            addToast('Task created successfully', 'success');
          }}
        />
      )}

      {selectedTaskId && (
        <TaskDetailsModal
          taskId={selectedTaskId}
          onClose={() => setSelectedTaskId(null)}
          onUpdate={(updatedTask) => {
            setTasks((prev) => prev.map((t) => (t.id === updatedTask.id ? updatedTask : t)));
            addToast('Task description updated', 'success');
          }}
          onDelete={(deletedId) => {
            setTasks((prev) => prev.filter((t) => t.id !== deletedId));
            addToast('Task deleted successfully', 'success');
          }}
        />
      )}

      {/* Toast Notification Container */}
      <div className={styles.toastContainer}>
        {toasts.map((t) => (
          <div
            key={t.id}
            className={`${styles.toast} ${
              t.type === 'success' ? styles.toastSuccess : t.type === 'error' ? styles.toastError : ''
            }`}
          >
            <div style={{ display: 'flex', flexDirection: 'column', gap: '4px', flex: 1 }}>
              <span>{t.message}</span>
              {t.downloadUrl && (
                <a
                  href={t.downloadUrl}
                  download
                  style={{ color: 'var(--primary)', fontWeight: 'bold', textDecoration: 'underline', fontSize: '12px' }}
                >
                  Download Report CSV
                </a>
              )}
            </div>
            <button
              className={styles.toastClose}
              onClick={() => setToasts((prev) => prev.filter((item) => item.id !== t.id))}
            >
              &times;
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}
