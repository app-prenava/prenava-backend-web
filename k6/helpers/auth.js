// Authentication Helper for k6 Tests
// Handles JWT token generation and management

import { check } from 'k6';
import { http } from 'k6/execution';
import { CONFIG } from '../config.js';

// Token storage (shared across VUs using shared-objects module if needed)
let authToken = null;
let refreshTokens = [];

/**
 * Perform login and return JWT token
 * @param {string} email - User email
 * @param {string} password - User password
 * @returns {string|null} JWT token or null if failed
 */
export function login(email, password) {
  const loginPayload = JSON.stringify({
    email: email || CONFIG.TEST_USER.email,
    password: password || CONFIG.TEST_USER.password,
  });

  const loginParams = {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    tags: { name: 'Login' },
  };

  const loginRes = http.post(
    `${CONFIG.BASE_URL}/auth/login`,
    loginPayload,
    loginParams
  );

  const success = check(loginRes, {
    'login successful': (r) => r.status === 200,
    'received token': (r) => r.json('access_token') !== undefined,
    'received refresh token': (r) => r.json('refresh_token') !== undefined,
  });

  if (success) {
    const token = loginRes.json('access_token');
    authToken = token;
    return token;
  }

  console.error(`Login failed: Status ${loginRes.status}, Body: ${loginRes.body}`);
  return null;
}

/**
 * Get auth headers with JWT token
 * @returns {object} Headers object with Authorization
 */
export function getAuthHeaders() {
  if (!authToken) {
    authToken = login();
  }

  return {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Authorization': `Bearer ${authToken}`,
  };
}

/**
 * Perform user registration for load testing
 * Generates random email for each VU
 * @returns {object} Registration response data
 */
export function registerTestUser(vuId) {
  const randomSuffix = Math.floor(Math.random() * 1000000);
  const email = `k6-test-${vuId}-${randomSuffix}@example.com`;

  const registerPayload = JSON.stringify({
    name: `K6 Test User ${vuId}`,
    email: email,
    password: 'TestPassword123!',
    password_confirmation: 'TestPassword123!',
  });

  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    tags: { name: 'Register' },
  };

  const res = http.post(
    `${CONFIG.BASE_URL}/auth/register`,
    registerPayload,
    params
  );

  if (res.status === 201) {
    return {
      success: true,
      email: email,
      token: res.json('access_token'),
    };
  }

  return {
    success: false,
    email: email,
    error: res.body,
  };
}

/**
 * Refresh JWT token
 * @param {string} refreshToken - Refresh token
 * @returns {string|null} New access token or null
 */
export function refreshAccessToken(refreshToken) {
  const payload = JSON.stringify({
    refresh_token: refreshToken,
  });

  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    tags: { name: 'RefreshToken' },
  };

  const res = http.post(
    `${CONFIG.BASE_URL}/auth/refresh`,
    payload,
    params
  );

  if (res.status === 200) {
    return res.json('access_token');
  }

  return null;
}

/**
 * Logout and invalidate token
 */
export function logout() {
  if (!authToken) return;

  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': `Bearer ${authToken}`,
    },
    tags: { name: 'Logout' },
  };

  http.post(`${CONFIG.BASE_URL}/auth/logout`, null, params);
  authToken = null;
}

/**
 * Setup function - login once per VU
 * Call this in the setup function of your test
 */
export function setupAuth() {
  return login(CONFIG.TEST_USER.email, CONFIG.TEST_USER.password);
}

/**
 * Teardown function - logout
 * Call this in the teardown function of your test
 */
export function teardownAuth() {
  logout();
}

// Export authToken for external access
export { authToken };
