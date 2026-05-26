'use client';

import React, { useState, useEffect } from 'react';
import { apiRequest } from '@/services/api';
import styles from './modal.module.css';

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
}

interface CreateTaskModalProps {
  onClose: () => void;
  onSuccess: (task: any) => void;
}

export default function CreateTaskModal({ onClose, onSuccess }: CreateTaskModalProps) {
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [status, setStatus] = useState('todo');
  const [priority, setPriority] = useState('medium');
  const [assignedUserId, setAssignedUserId] = useState<string>('');
  const [dueDate, setDueDate] = useState('');
  
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Fetch users on mount for assignment dropdown
  useEffect(() => {
    async function fetchUsers() {
      try {
        const list = await apiRequest('/users');
        setUsers(list);
      } catch (err) {
        console.error('Failed to load users:', err);
      }
    }
    fetchUsers();
  }, []);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      const payload = {
        title,
        description: description || null,
        status,
        priority,
        assigned_user_id: assignedUserId ? parseInt(assignedUserId) : null,
        due_date: dueDate ? dueDate : null,
      };

      const task = await apiRequest('/tasks', {
        method: 'POST',
        body: JSON.stringify(payload),
      });

      onSuccess(task);
    } catch (err: any) {
      console.error('Task creation failed:', err);
      setError(err.message || 'Failed to create task. Please try again.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className={styles.overlay}>
      <div className={styles.modal}>
        <div className={styles.header}>
          <h2 className={styles.title}>Create New Task</h2>
          <button className={styles.closeBtn} onClick={onClose}>&times;</button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className={styles.body}>
            {error && <div style={{ color: '#ef4444', marginBottom: '16px', fontSize: '13px' }}>{error}</div>}

            <div className={styles.formGroup}>
              <label className={styles.label} htmlFor="title">Task Title *</label>
              <input
                className={styles.input}
                type="text"
                id="title"
                placeholder="E.g., Implement custom JWT guard"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                required
                disabled={loading}
              />
            </div>

            <div className={styles.formGroup}>
              <label className={styles.label} htmlFor="description">Description</label>
              <textarea
                className={styles.textarea}
                id="description"
                placeholder="Provide detailed instructions..."
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                disabled={loading}
              />
            </div>

            <div className={styles.formRow}>
              <div className={styles.formGroup}>
                <label className={styles.label} htmlFor="status">Status</label>
                <select
                  className={styles.select}
                  id="status"
                  value={status}
                  onChange={(e) => setStatus(e.target.value)}
                  disabled={loading}
                >
                  <option value="todo">To Do</option>
                  <option value="in_progress">In Progress</option>
                  <option value="review">Review</option>
                  <option value="completed">Completed</option>
                </select>
              </div>

              <div className={styles.formGroup}>
                <label className={styles.label} htmlFor="priority">Priority</label>
                <select
                  className={styles.select}
                  id="priority"
                  value={priority}
                  onChange={(e) => setPriority(e.target.value)}
                  disabled={loading}
                >
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                </select>
              </div>
            </div>

            <div className={styles.formRow}>
              <div className={styles.formGroup}>
                <label className={styles.label} htmlFor="assignee">Assignee</label>
                <select
                  className={styles.select}
                  id="assignee"
                  value={assignedUserId}
                  onChange={(e) => setAssignedUserId(e.target.value)}
                  disabled={loading}
                >
                  <option value="">Unassigned</option>
                  {users.map((user) => (
                    <option key={user.id} value={user.id}>
                      {user.name} ({user.role})
                    </option>
                  ))}
                </select>
              </div>

              <div className={styles.formGroup}>
                <label className={styles.label} htmlFor="dueDate">Due Date</label>
                <input
                  className={styles.input}
                  type="date"
                  id="dueDate"
                  value={dueDate}
                  onChange={(e) => setDueDate(e.target.value)}
                  disabled={loading}
                />
              </div>
            </div>
          </div>

          <div className={styles.footer}>
            <button type="button" className={styles.cancelBtn} onClick={onClose} disabled={loading}>
              Cancel
            </button>
            <button type="submit" className={styles.submitBtn} disabled={loading}>
              {loading ? 'Creating...' : 'Create Task'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
