'use client';

import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import { apiRequest, setCookie, eraseCookie, getCookie } from '../services/api';

export interface UserProfile {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'manager' | 'member';
}

interface AuthContextType {
  user: UserProfile | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  checkAuth: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<UserProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const router = useRouter();
  const pathname = usePathname();

  // Load user profile on mount
  useEffect(() => {
    checkAuth();
  }, []);

  // Sync route transitions on auth changes
  useEffect(() => {
    if (!loading) {
      const publicPaths = ['/login'];
      const isPublicPath = publicPaths.includes(pathname);

      if (!user && !isPublicPath) {
        router.replace('/login');
      } else if (user && isPublicPath) {
        router.replace('/');
      }
    }
  }, [user, loading, pathname]);

  /**
   * Verify token and load profile from server.
   */
  async function checkAuth() {
    const token = getCookie('token') || localStorage.getItem('token');
    
    if (!token) {
      setUser(null);
      setLoading(false);
      return;
    }

    try {
      const profile = await apiRequest('/auth/me');
      setUser(profile);
    } catch (err) {
      console.error('Session verification failed:', err);
      // Clean up invalid session
      eraseCookie('token');
      localStorage.removeItem('token');
      setUser(null);
    } finally {
      setLoading(false);
    }
  }

  /**
   * Handle user login.
   */
  async function login(email: string, password: string) {
    setLoading(true);
    try {
      const data = await apiRequest('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password }),
      });

      const token = data.access_token;
      
      // Save token in cookies and localStorage
      setCookie('token', token, 7); // 7 Days expiration
      localStorage.setItem('token', token);
      
      setUser(data.user);
      router.replace('/');
    } catch (err) {
      setUser(null);
      throw err;
    } finally {
      setLoading(false);
    }
  }

  /**
   * Handle user logout.
   */
  async function logout() {
    setLoading(true);
    try {
      // Direct API call for stateless notification (optional)
      await apiRequest('/auth/logout', { method: 'POST' }).catch(() => {});
    } catch (err) {
      // Fail silently for network errors on logout
    } finally {
      // Clear client session
      eraseCookie('token');
      localStorage.removeItem('token');
      setUser(null);
      router.replace('/login');
      setLoading(false);
    }
  }

  return (
    <AuthContext.Provider value={{ user, loading, login, logout, checkAuth }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
