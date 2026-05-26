'use client';

import React from 'react';
import styles from './card.module.css';

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
  comments_count?: number;
  attachments_count?: number;
}

interface TaskCardProps {
  task: Task;
  onClick: () => void;
  onDragStart: (e: React.DragEvent, taskId: number) => void;
}

export default function TaskCard({ task, onClick, onDragStart }: TaskCardProps) {
  const getInitials = (name: string) => {
    return name
      .split(' ')
      .map((n) => n[0])
      .join('')
      .toUpperCase()
      .substring(0, 2);
  };

  const isOverdue = task.due_date && new Date(task.due_date) < new Date() && task.status !== 'completed';

  const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
    });
  };

  return (
    <div
      className={styles.card}
      draggable
      onDragStart={(e) => onDragStart(e, task.id)}
      onClick={onClick}
    >
      <div className={styles.header}>
        <span className={`${styles.priorityBadge} ${styles[`priority-${task.priority}`]}`}>
          {task.priority}
        </span>
        {isOverdue && (
          <span className={`${styles.priorityBadge} ${styles['priority-high']}`} style={{ background: 'rgba(239, 68, 68, 0.15)' }}>
            Overdue
          </span>
        )}
      </div>

      <h3 className={styles.title}>{task.title}</h3>

      {task.description && (
        <p className={styles.description}>{task.description}</p>
      )}

      <div className={styles.footer}>
        <div className={styles.assignee}>
          {task.assigned_user ? (
            <>
              <div className={styles.avatar} title={task.assigned_user.name}>
                {getInitials(task.assigned_user.name)}
              </div>
              <span>{task.assigned_user.name}</span>
            </>
          ) : (
            <span style={{ color: 'var(--text-muted)', fontStyle: 'italic' }}>Unassigned</span>
          )}
        </div>

        {task.due_date && (
          <div className={`${styles.dueDate} ${isOverdue ? styles.dueDateAlert : ''}`}>
            <svg
              width="12"
              height="12"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              viewBox="0 0 24 24"
            >
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
            <span>{formatDate(task.due_date)}</span>
          </div>
        )}
      </div>
    </div>
  );
}
