'use client';

import React, { createContext, useContext, useEffect, useState, ReactNode, useRef } from 'react';
import { useAuth } from './AuthContext';
import { API_URL, apiRequest, getCookie } from '../services/api';

export interface OnlineUser {
  id: number;
  name: string;
  email: string;
  role: string;
}

export interface TypingUser {
  id: number;
  name: string;
}

interface RealtimeContextType {
  onlineUsers: OnlineUser[];
  typingUsers: { [taskId: number]: { [userId: number]: string } }; // maps taskId -> userId -> name
  sendTypingStatus: (taskId: number, isTyping: boolean) => Promise<void>;
  registerListener: (eventType: string, callback: (payload: any) => void) => () => void;
}

const RealtimeContext = createContext<RealtimeContextType | undefined>(undefined);

export function RealtimeProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth();
  const [onlineUsers, setOnlineUsers] = useState<OnlineUser[]>([]);
  const [typingUsers, setTypingUsers] = useState<{ [taskId: number]: { [userId: number]: string } }>({});
  const listenersRef = useRef<{ [eventType: string]: Array<(payload: any) => void> }>({});
  const eventSourceRef = useRef<EventSource | null>(null);

  // Helper to register listeners for SSE events
  const registerListener = (eventType: string, callback: (payload: any) => void) => {
    if (!listenersRef.current[eventType]) {
      listenersRef.current[eventType] = [];
    }
    listenersRef.current[eventType].push(callback);

    // Return unsubscribe function
    return () => {
      listenersRef.current[eventType] = listenersRef.current[eventType].filter((cb) => cb !== callback);
    };
  };

  // Dispatch events to registered listeners
  const dispatchEvent = (eventType: string, payload: any) => {
    const list = listenersRef.current[eventType] || [];
    list.forEach((cb) => {
      try {
        cb(payload);
      } catch (err) {
        console.error(`Error in listener for event ${eventType}:`, err);
      }
    });
  };

  // Presence heartbeat: Ping presence endpoint every 10 seconds
  useEffect(() => {
    if (!user) return;

    // Send initial presence signal
    const sendPresence = async () => {
      try {
        await apiRequest('/realtime/presence', { method: 'POST' });
      } catch (err) {
        console.error('Failed to send presence heartbeat:', err);
      }
    };

    sendPresence();
    const interval = setInterval(sendPresence, 10000);

    return () => clearInterval(interval);
  }, [user]);

  // Establish SSE stream connection
  useEffect(() => {
    if (!user) {
      if (eventSourceRef.current) {
        eventSourceRef.current.close();
        eventSourceRef.current = null;
      }
      setOnlineUsers([]);
      setTypingUsers({});
      return;
    }

    const token = getCookie('token') || localStorage.getItem('token');
    if (!token) return;

    const url = `${API_URL}/realtime/stream?token=${encodeURIComponent(token)}`;
    const es = new EventSource(url);
    eventSourceRef.current = es;

    // Listen to generic message/events
    const handleEvent = (type: string, dataStr: string) => {
      try {
        const payload = JSON.parse(dataStr);
        dispatchEvent(type, payload);
      } catch (err) {
        console.error(`Failed to parse SSE event: ${type}`, err);
      }
    };

    es.addEventListener('presence_updated', (e) => {
      try {
        const users = JSON.parse(e.data);
        setOnlineUsers(users);
      } catch (err) {
        console.error('Failed to parse presence_updated event:', err);
      }
    });

    es.addEventListener('user_typing', (e) => {
      try {
        const data = JSON.parse(e.data);
        const { task_id, is_typing, user: typingUser } = data;

        setTypingUsers((prev) => {
          const next = { ...prev };
          if (!next[task_id]) {
            next[task_id] = {};
          }

          if (is_typing) {
            next[task_id][typingUser.id] = typingUser.name;
          } else {
            const taskTypers = { ...next[task_id] };
            delete taskTypers[typingUser.id];
            next[task_id] = taskTypers;
          }

          return next;
        });
      } catch (err) {
        console.error('Failed to parse user_typing event:', err);
      }
    });

    // Handle generic custom events
    const customEvents = [
      'task_created',
      'task_updated',
      'task_deleted',
      'comment_created',
      'attachment_added',
      'attachment_updated',
      'attachment_deleted',
      'bulk_status_completed',
      'export_completed'
    ];

    customEvents.forEach((type) => {
      es.addEventListener(type, (e) => handleEvent(type, e.data));
    });

    es.onopen = () => {
      console.log('SSE connection established');
    };

    es.onerror = (err) => {
      console.error('SSE connection error, attempting reconnect...', err);
    };

    return () => {
      es.close();
      eventSourceRef.current = null;
    };
  }, [user]);

  // Send typing status to backend
  const sendTypingStatus = async (taskId: number, isTyping: boolean) => {
    try {
      await apiRequest('/realtime/typing', {
        method: 'POST',
        body: JSON.stringify({ task_id: taskId, is_typing: isTyping }),
      });
    } catch (err) {
      console.error('Failed to send typing status:', err);
    }
  };

  return (
    <RealtimeContext.Provider value={{ onlineUsers, typingUsers, sendTypingStatus, registerListener }}>
      {children}
    </RealtimeContext.Provider>
  );
}

export function useRealtime() {
  const context = useContext(RealtimeContext);
  if (context === undefined) {
    throw new Error('useRealtime must be used within a RealtimeProvider');
  }
  return context;
}
