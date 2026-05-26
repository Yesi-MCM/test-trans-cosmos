export const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

/**
 * Get cookie by name client-side.
 */
export function getCookie(name: string): string | null {
  if (typeof window === 'undefined') return null;
  const nameEQ = name + '=';
  const ca = document.cookie.split(';');
  for (let i = 0; i < ca.length; i++) {
    let c = ca[i];
    while (c.charAt(0) === ' ') c = c.substring(1, c.length);
    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
  }
  return null;
}

/**
 * Set cookie client-side.
 */
export function setCookie(name: string, value: string, days = 7) {
  if (typeof window === 'undefined') return;
  let expires = '';
  if (days) {
    const date = new Date();
    date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
    expires = '; expires=' + date.toUTCString();
  }
  document.cookie = name + '=' + (value || '') + expires + '; path=/; SameSite=Lax';
}

/**
 * Erase cookie client-side.
 */
export function eraseCookie(name: string) {
  if (typeof window === 'undefined') return;
  document.cookie = name + '=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
}

/**
 * Generic API request wrapper.
 */
export async function apiRequest(endpoint: string, options: RequestInit = {}) {
  const token = getCookie('token') || (typeof window !== 'undefined' ? localStorage.getItem('token') : null);
  
  const headers = new Headers(options.headers || {});
  
  if (token) {
    headers.set('Authorization', `Bearer ${token}`);
  }
  
  // Set JSON content-type if body is provided and is not a FormData object
  if (options.body && !(options.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }
  
  // Make sure we request JSON accept header
  if (!headers.has('Accept')) {
    headers.set('Accept', 'application/json');
  }

  const url = endpoint.startsWith('http') ? endpoint : `${API_URL}${endpoint}`;
  
  const response = await fetch(url, {
    ...options,
    headers,
  });

  if (!response.ok) {
    let errorData = null;
    try {
      errorData = await response.json();
    } catch (e) {
      // Body is not JSON
    }
    
    const error = new Error(errorData?.message || `HTTP error! Status: ${response.status}`);
    (error as any).status = response.status;
    (error as any).data = errorData;
    throw error;
  }

  // Handle empty bodies (e.g., status 204 or no content-type)
  const contentType = response.headers.get('Content-Type');
  if (response.status === 204 || !contentType || !contentType.includes('application/json')) {
    return null;
  }

  return response.json();
}
