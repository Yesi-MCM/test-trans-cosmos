import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { getCookie, setCookie, eraseCookie } from './api';

describe('Cookie Utilities', () => {
  let cookieStore = '';

  beforeEach(() => {
    cookieStore = '';
    
    // Mock global document and window
    global.document = {
      get cookie() {
        return cookieStore;
      },
      set cookie(val) {
        cookieStore = val;
      }
    } as any;

    global.window = {} as any;
  });

  afterEach(() => {
    delete (global as any).document;
    delete (global as any).window;
  });

  it('sets and gets a cookie correctly', () => {
    setCookie('test_cookie', 'test_value', 1);
    const value = getCookie('test_cookie');
    expect(value).toBe('test_value');
  });

  it('returns null if cookie does not exist', () => {
    const value = getCookie('non_existent');
    expect(value).toBeNull();
  });

  it('erases a cookie correctly', () => {
    cookieStore = 'test_cookie=test_value';
    eraseCookie('test_cookie');
    expect(cookieStore).toContain('test_cookie=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;');
  });
});
