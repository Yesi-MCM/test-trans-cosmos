'use client';

import React, { useState, useEffect, useRef } from 'react';
import { apiRequest, getCookie, API_URL } from '@/services/api';
import { useRealtime } from '@/context/RealtimeContext';
import { useAuth } from '@/context/AuthContext';
import styles from './modal.module.css';

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
}

interface Attachment {
  id: number;
  task_id: number;
  file_name: string;
  file_path: string;
  file_size: number;
  mime_type: string;
  version: number;
  status: 'processing' | 'ready' | 'infected';
  uploaded_at: string;
}

interface Comment {
  id: number;
  task_id: number;
  user_id: number;
  comment: string;
  created_at: string;
  user: {
    id: number;
    name: string;
  };
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
  attachments?: Attachment[];
  comments?: Comment[];
}

interface TaskDetailsModalProps {
  taskId: number;
  onClose: () => void;
  onUpdate: (updatedTask: any) => void;
  onDelete: (deletedTaskId: number) => void;
}

const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB chunks

export default function TaskDetailsModal({ taskId, onClose, onUpdate, onDelete }: TaskDetailsModalProps) {
  const { user: currentUser } = useAuth();
  const { typingUsers, sendTypingStatus, registerListener } = useRealtime();

  const [task, setTask] = useState<Task | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Edit fields
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [status, setStatus] = useState<'todo' | 'in_progress' | 'review' | 'completed'>('todo');
  const [priority, setPriority] = useState<'low' | 'medium' | 'high'>('medium');
  const [assignedUserId, setAssignedUserId] = useState<string>('');
  const [dueDate, setDueDate] = useState('');
  const [isSavingDetails, setIsSavingDetails] = useState(false);

  // Users list for assignment dropdown
  const [users, setUsers] = useState<User[]>([]);

  // Comments state
  const [comments, setComments] = useState<Comment[]>([]);
  const [newComment, setNewComment] = useState('');
  const [isSubmittingComment, setIsSubmittingComment] = useState(false);

  // Attachments & Upload state
  const [attachments, setAttachments] = useState<Attachment[]>([]);
  const [isDragActive, setIsDragActive] = useState(false);
  const [uploadProgress, setUploadProgress] = useState<number | null>(null);
  const [uploadStatusText, setUploadStatusText] = useState<string | null>(null);

  const fileInputRef = useRef<HTMLInputElement>(null);
  const typingTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const isTypingRef = useRef(false);

  const token = getCookie('token') || (typeof window !== 'undefined' ? localStorage.getItem('token') : '');

  // 1. Fetch Task details and Users
  useEffect(() => {
    async function loadData() {
      try {
        setLoading(true);
        const [taskData, usersList] = await Promise.all([
          apiRequest(`/tasks/${taskId}`),
          apiRequest('/users').catch(() => []),
        ]);

        setTask(taskData);
        setComments(taskData.comments || []);
        setAttachments(taskData.attachments || []);
        setUsers(usersList);

        // Populate forms
        setTitle(taskData.title);
        setDescription(taskData.description || '');
        setStatus(taskData.status);
        setPriority(taskData.priority);
        setAssignedUserId(taskData.assigned_user_id ? String(taskData.assigned_user_id) : '');
        setDueDate(taskData.due_date ? taskData.due_date.substring(0, 10) : '');

        setError(null);
      } catch (err: any) {
        console.error('Failed to load task details:', err);
        setError(err.message || 'Failed to load task details.');
      } finally {
        setLoading(false);
      }
    }

    loadData();
  }, [taskId]);

  // 2. Register real-time listeners for updates when modal is open
  useEffect(() => {
    const unsubUpdate = registerListener('task_updated', (updatedTask) => {
      if (updatedTask.id === taskId) {
        setTask(updatedTask);
        setTitle(updatedTask.title);
        setDescription(updatedTask.description || '');
        setStatus(updatedTask.status);
        setPriority(updatedTask.priority);
        setAssignedUserId(updatedTask.assigned_user_id ? String(updatedTask.assigned_user_id) : '');
        setDueDate(updatedTask.due_date ? updatedTask.due_date.substring(0, 10) : '');
      }
    });

    const unsubComment = registerListener('comment_created', (comment: Comment) => {
      if (comment.task_id === taskId) {
        setComments((prev) => {
          // Avoid duplicate comments
          if (prev.some((c) => c.id === comment.id)) return prev;
          return [...prev, comment];
        });
      }
    });

    const unsubAttachmentAdded = registerListener('attachment_added', (attachment: Attachment) => {
      if (attachment.task_id === taskId) {
        setAttachments((prev) => {
          if (prev.some((a) => a.id === attachment.id)) return prev;
          return [...prev, attachment];
        });
      }
    });

    const unsubAttachmentUpdated = registerListener('attachment_updated', (attachment: Attachment) => {
      if (attachment.task_id === taskId) {
        setAttachments((prev) => prev.map((a) => (a.id === attachment.id ? attachment : a)));
      }
    });

    const unsubAttachmentDeleted = registerListener('attachment_deleted', (payload: { id: number }) => {
      setAttachments((prev) => prev.filter((a) => a.id !== payload.id));
    });

    return () => {
      unsubUpdate();
      unsubComment();
      unsubAttachmentAdded();
      unsubAttachmentUpdated();
      unsubAttachmentDeleted();
    };
  }, [taskId, registerListener]);

  // 3. Save details
  async function handleSaveDetails(e?: React.FormEvent) {
    if (e) e.preventDefault();
    setIsSavingDetails(true);
    setError(null);

    try {
      const payload = {
        title,
        description: description || null,
        status,
        priority,
        assigned_user_id: assignedUserId ? parseInt(assignedUserId) : null,
        due_date: dueDate ? dueDate : null,
      };

      const updated = await apiRequest(`/tasks/${taskId}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
      });

      setTask(updated);
      onUpdate(updated);
    } catch (err: any) {
      console.error('Failed to update task details:', err);
      setError(err.message || 'Failed to update task details.');
    } finally {
      setIsSavingDetails(false);
    }
  }

  // 4. Delete task
  async function handleDeleteTask() {
    if (!confirm('Are you sure you want to delete this task?')) return;

    try {
      await apiRequest(`/tasks/${taskId}`, { method: 'DELETE' });
      onDelete(taskId);
      onClose();
    } catch (err: any) {
      console.error('Failed to delete task:', err);
      setError(err.message || 'Failed to delete task.');
    }
  }

  // 5. Submit comment & handle typing indicators
  async function handleAddComment(e: React.FormEvent) {
    e.preventDefault();
    if (!newComment.trim() || isSubmittingComment) return;

    setIsSubmittingComment(true);
    // Instantly stop typing indicator
    handleTyping(false);

    try {
      const comment = await apiRequest(`/tasks/${taskId}/comments`, {
        method: 'POST',
        body: JSON.stringify({ comment: newComment }),
      });

      setComments((prev) => [...prev, comment]);
      setNewComment('');
    } catch (err: any) {
      console.error('Failed to post comment:', err);
      setError(err.message || 'Failed to add comment.');
    } finally {
      setIsSubmittingComment(false);
    }
  }

  const handleTyping = (typing: boolean) => {
    if (isTypingRef.current !== typing) {
      isTypingRef.current = typing;
      sendTypingStatus(taskId, typing);
    }
  };

  const handleCommentInputChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    setNewComment(e.target.value);
    
    // Send typing: true
    handleTyping(true);

    // Debounce typing: false after 3 seconds of inactivity
    if (typingTimeoutRef.current) {
      clearTimeout(typingTimeoutRef.current);
    }

    typingTimeoutRef.current = setTimeout(() => {
      handleTyping(false);
    }, 3000);
  };

  const handleCommentBlur = () => {
    if (typingTimeoutRef.current) {
      clearTimeout(typingTimeoutRef.current);
    }
    handleTyping(false);
  };

  // Cleanup typing indicator on unmount
  useEffect(() => {
    return () => {
      if (typingTimeoutRef.current) {
        clearTimeout(typingTimeoutRef.current);
      }
      if (isTypingRef.current) {
        sendTypingStatus(taskId, false);
      }
    };
  }, [taskId]);

  // 6. Drag and Drop File Upload + Chunking (>50MB)
  const handleDrag = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (e.type === 'dragenter' || e.type === 'dragover') {
      setIsDragActive(true);
    } else if (e.type === 'dragleave') {
      setIsDragActive(false);
    }
  };

  const handleDrop = async (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragActive(false);

    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      await handleFileUpload(e.dataTransfer.files[0]);
    }
  };

  const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      await handleFileUpload(e.target.files[0]);
    }
  };

  const handleFileUpload = async (file: File) => {
    setUploadProgress(0);
    setError(null);

    const fiftyMB = 50 * 1024 * 1024;

    try {
      if (file.size > fiftyMB) {
        // Chunked upload
        setUploadStatusText(`Uploading large file in chunks: ${file.name}...`);
        const uploadId = `${Date.now()}-${Math.floor(Math.random() * 1000000)}`;
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

        for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
          const start = chunkIndex * CHUNK_SIZE;
          const end = Math.min(start + CHUNK_SIZE, file.size);
          const chunkBlob = file.slice(start, end);

          const formData = new FormData();
          formData.append('task_id', String(taskId));
          formData.append('upload_id', uploadId);
          formData.append('chunk_index', String(chunkIndex));
          formData.append('total_chunks', String(totalChunks));
          formData.append('file_name', file.name);
          formData.append('file', chunkBlob, file.name);

          setUploadStatusText(`Uploading chunk ${chunkIndex + 1} of ${totalChunks}...`);
          
          const result = await apiRequest('/attachments/chunk', {
            method: 'POST',
            body: formData,
          });

          const progress = Math.round(((chunkIndex + 1) / totalChunks) * 100);
          setUploadProgress(progress);

          if (chunkIndex === totalChunks - 1 && result?.attachment) {
            setAttachments((prev) => [...prev, result.attachment]);
            setUploadStatusText('Assembling chunks and running malware check...');
          }
        }
      } else {
        // Standard upload
        setUploadStatusText(`Uploading file: ${file.name}...`);
        const formData = new FormData();
        formData.append('file', file);

        const attachment = await apiRequest(`/tasks/${taskId}/attachments`, {
          method: 'POST',
          body: formData,
        });

        setUploadProgress(100);
        setAttachments((prev) => [...prev, attachment]);
      }

      setUploadStatusText('Upload complete. Running background malware scan...');
      setTimeout(() => {
        setUploadProgress(null);
        setUploadStatusText(null);
      }, 3000);
    } catch (err: any) {
      console.error('File upload failed:', err);
      setError(err.message || 'File upload failed.');
      setUploadProgress(null);
      setUploadStatusText(null);
    }
  };

  // 7. Delete attachment
  async function handleDeleteAttachment(id: number) {
    if (!confirm('Are you sure you want to delete this attachment?')) return;

    try {
      await apiRequest(`/attachments/${id}`, { method: 'DELETE' });
      setAttachments((prev) => prev.filter((a) => a.id !== id));
    } catch (err: any) {
      console.error('Failed to delete attachment:', err);
      setError(err.message || 'Failed to delete attachment.');
    }
  }

  // Helper formatting
  const formatBytes = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  // Typing list string
  const taskTypers = typingUsers[taskId] || {};
  const typingList = Object.entries(taskTypers)
    .filter(([uid]) => parseInt(uid) !== currentUser?.id)
    .map(([, name]) => name);

  return (
    <div className={styles.overlay} onClick={onClose}>
      <div className={`${styles.modal} ${styles.modalLarge}`} onClick={(e) => e.stopPropagation()}>
        <div className={styles.header}>
          <h2 className={styles.title}>Task Details</h2>
          <button className={styles.closeBtn} onClick={onClose}>&times;</button>
        </div>

        <div className={styles.body}>
          {error && <div className={styles.formError} style={{ color: '#ef4444', marginBottom: '20px' }}>{error}</div>}

          <div style={{ display: 'grid', gridTemplateColumns: '1.2fr 0.8fr', gap: '32px' }}>
            {/* Left Column: Description & Comments */}
            <div>
              <form onSubmit={handleSaveDetails}>
                <div className={styles.formGroup}>
                  <label className={styles.label} htmlFor="modal-title">Task Title</label>
                  <input
                    className={styles.input}
                    type="text"
                    id="modal-title"
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    required
                  />
                </div>

                <div className={styles.formGroup}>
                  <label className={styles.label} htmlFor="modal-desc">Description</label>
                  <textarea
                    className={styles.textarea}
                    id="modal-desc"
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    placeholder="Describe this task in details..."
                  />
                </div>

                <div style={{ display: 'flex', gap: '12px', marginBottom: '24px' }}>
                  <button
                    type="submit"
                    className={styles.submitBtn}
                    disabled={isSavingDetails}
                  >
                    {isSavingDetails ? 'Saving...' : 'Save Description'}
                  </button>
                  <button
                    type="button"
                    className={`${styles.cancelBtn} ${styles.deleteBtn}`}
                    onClick={handleDeleteTask}
                  >
                    Delete Task
                  </button>
                </div>
              </form>

              {/* Comments Section */}
              <div className={styles.section}>
                <h3 className={styles.sectionTitle}>
                  <svg width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                  </svg>
                  Comments ({comments.length})
                </h3>

                <div className={styles.commentList}>
                  {comments.length === 0 ? (
                    <div style={{ color: 'var(--text-muted)', fontSize: '13px', fontStyle: 'italic', padding: '12px 0' }}>
                      No comments yet. Start the conversation below.
                    </div>
                  ) : (
                    comments.map((comment) => (
                      <div key={comment.id} className={styles.commentCard}>
                        <div className={styles.commentHeader}>
                          <span className={styles.commentAuthor}>{comment.user?.name || 'Unknown User'}</span>
                          <span className={styles.commentDate}>
                            {new Date(comment.created_at).toLocaleString()}
                          </span>
                        </div>
                        <div className={styles.commentBody}>{comment.comment}</div>
                      </div>
                    ))
                  )}
                </div>

                <form onSubmit={handleAddComment}>
                  <div className={styles.commentInputWrapper}>
                    <textarea
                      className={styles.textarea}
                      style={{ minHeight: '60px', height: '60px' }}
                      placeholder="Write a comment..."
                      value={newComment}
                      onChange={handleCommentInputChange}
                      onBlur={handleCommentBlur}
                      disabled={isSubmittingComment}
                      required
                    />
                    <button
                      type="submit"
                      className={styles.submitBtn}
                      style={{ alignSelf: 'flex-end', height: '42px' }}
                      disabled={isSubmittingComment}
                    >
                      Post
                    </button>
                  </div>
                  {typingList.length > 0 && (
                    <div className={styles.typingIndicator}>
                      {typingList.join(', ')} {typingList.length === 1 ? 'is' : 'are'} typing...
                    </div>
                  )}
                </form>
              </div>
            </div>

            {/* Right Column: Meta settings & File Attachments */}
            <div>
              {/* Task Meta Settings */}
              <div className={styles.section} style={{ background: 'var(--bg-tertiary)', padding: '20px', borderRadius: 'var(--radius-md)', border: '1px solid var(--border-color)' }}>
                <h3 className={styles.sectionTitle} style={{ marginBottom: '16px' }}>Settings</h3>
                
                <div className={styles.formGroup}>
                  <label className={styles.label} htmlFor="modal-status">Status</label>
                  <select
                    className={styles.select}
                    id="modal-status"
                    value={status}
                    onChange={(e) => {
                      setStatus(e.target.value as any);
                      // Auto-save setting change
                      setTimeout(() => handleSaveDetails(), 50);
                    }}
                  >
                    <option value="todo">To Do</option>
                    <option value="in_progress">In Progress</option>
                    <option value="review">Review</option>
                    <option value="completed">Completed</option>
                  </select>
                </div>

                <div className={styles.formGroup}>
                  <label className={styles.label} htmlFor="modal-priority">Priority</label>
                  <select
                    className={styles.select}
                    id="modal-priority"
                    value={priority}
                    onChange={(e) => {
                      setPriority(e.target.value as any);
                      setTimeout(() => handleSaveDetails(), 50);
                    }}
                  >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                  </select>
                </div>

                <div className={styles.formGroup}>
                  <label className={styles.label} htmlFor="modal-assignee">Assignee</label>
                  <select
                    className={styles.select}
                    id="modal-assignee"
                    value={assignedUserId}
                    onChange={(e) => {
                      setAssignedUserId(e.target.value);
                      setTimeout(() => handleSaveDetails(), 50);
                    }}
                  >
                    <option value="">Unassigned</option>
                    {users.map((u) => (
                      <option key={u.id} value={u.id}>
                        {u.name} ({u.role})
                      </option>
                    ))}
                  </select>
                </div>

                <div className={styles.formGroup} style={{ marginBottom: 0 }}>
                  <label className={styles.label} htmlFor="modal-due">Due Date</label>
                  <input
                    className={styles.input}
                    type="date"
                    id="modal-due"
                    value={dueDate}
                    onChange={(e) => {
                      setDueDate(e.target.value);
                      setTimeout(() => handleSaveDetails(), 50);
                    }}
                  />
                </div>
              </div>

              {/* Attachments Section */}
              <div className={styles.section}>
                <h3 className={styles.sectionTitle}>
                  <svg width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
                  </svg>
                  Attachments ({attachments.length})
                </h3>

                {/* Dropzone */}
                <div
                  className={`${styles.dropzone} ${isDragActive ? styles.dropzoneActive : ''}`}
                  onDragEnter={handleDrag}
                  onDragOver={handleDrag}
                  onDragLeave={handleDrag}
                  onDrop={handleDrop}
                  onClick={() => fileInputRef.current?.click()}
                >
                  <input
                    type="file"
                    ref={fileInputRef}
                    style={{ display: 'none' }}
                    onChange={handleFileSelect}
                  />
                  <div className={styles.dropzoneTitle}>Drag & drop file here</div>
                  <div className={styles.dropzoneSubtitle}>or click to browse (supports files &gt; 50MB)</div>
                </div>

                {uploadProgress !== null && (
                  <div style={{ marginTop: '12px' }}>
                    <div className={styles.progressContainer}>
                      <div className={styles.progressBar} style={{ width: `${uploadProgress}%` }} />
                    </div>
                    <div className={styles.progressText}>
                      {uploadStatusText} ({uploadProgress}%)
                    </div>
                  </div>
                )}

                {/* Attachments List */}
                <div className={styles.attachmentList}>
                  {attachments.map((file) => {
                    const isImg = file.mime_type.startsWith('image/');
                    const isVid = file.mime_type.startsWith('video/');
                    const isSecureDownloadEnabled = file.status === 'ready';

                    return (
                      <div key={file.id} className={styles.attachmentCard} style={{ flexDirection: 'column', alignItems: 'stretch', gap: '8px' }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                          <div className={styles.attachmentInfo}>
                            <div className={styles.attachmentMeta}>
                              <span className={styles.attachmentName} title={file.file_name}>
                                {file.file_name}
                              </span>
                              <span className={styles.attachmentSize}>
                                {formatBytes(file.file_size)} • {file.mime_type}
                              </span>
                            </div>
                          </div>

                          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <span className={styles.attachmentVersion}>v{file.version}</span>
                            
                            {file.status === 'processing' && (
                              <span style={{ fontSize: '11px', color: 'var(--text-muted)', fontStyle: 'italic' }}>
                                Processing...
                              </span>
                            )}

                            {file.status === 'infected' && (
                              <span style={{ fontSize: '11px', color: '#ef4444', fontWeight: 'bold' }} title="Flagged by simulated anti-virus. Download blocked.">
                                Infected
                              </span>
                            )}

                            {file.status === 'ready' && (
                              <span style={{ fontSize: '11px', color: '#10b981', fontWeight: 'bold' }}>
                                Safe
                              </span>
                            )}
                          </div>
                        </div>

                        {/* If image, display thumbnail */}
                        {isImg && file.status === 'ready' && (
                          <div style={{ marginTop: '6px', borderRadius: 'var(--radius-sm)', overflow: 'hidden', border: '1px solid var(--border-color)', width: '150px', height: '150px', background: 'var(--bg-primary)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                            <img
                              src={`${API_URL}/attachments/${file.id}/download?thumbnail=1&token=${encodeURIComponent(token || '')}`}
                              alt={file.file_name}
                              style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }}
                            />
                          </div>
                        )}

                        {/* Video Adaptive Streaming Simulation message */}
                        {isVid && file.status === 'ready' && (
                          <div style={{ fontSize: '11px', color: 'var(--text-muted)', background: 'var(--bg-primary)', padding: '6px', borderRadius: '4px', border: '1px solid var(--border-color)' }}>
                            📺 Adaptive HLS preparation complete. Available in 480p, 720p, 1080p.
                          </div>
                        )}

                        <div className={styles.attachmentActions} style={{ justifyContent: 'flex-end', borderTop: '1px solid var(--border-color)', paddingTop: '6px', marginTop: '4px' }}>
                          {isSecureDownloadEnabled ? (
                            <a
                              href={`${API_URL}/attachments/${file.id}/download?token=${encodeURIComponent(token || '')}`}
                              download
                              className={styles.actionBtn}
                              style={{ textDecoration: 'none' }}
                            >
                              Download
                            </a>
                          ) : (
                            <span className={styles.actionBtn} style={{ opacity: 0.5, cursor: 'not-allowed' }}>
                              Blocked
                            </span>
                          )}

                          <button
                            type="button"
                            className={`${styles.actionBtn} ${styles.deleteBtn}`}
                            onClick={() => handleDeleteAttachment(file.id)}
                          >
                            Delete
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
