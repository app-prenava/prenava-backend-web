// k6 Auth Load Test
// Tests authentication endpoints under load
// Run: k6 run scenarios/01-auth-test.js

import http from 'k6/http';
import { check, group } from 'k6';
import { Rate } from 'k6/metrics';
import { SharedArray } from 'k6/data';
import { CONFIG } from '../config.js';
import { login, registerTestUser } from '../helpers/auth.js';

// Custom metrics
const authErrorRate = new Rate('auth_errors');

// Test data - use unique emails for registration
const testUsers = new SharedArray('testUsers', function () {
  const users = [];
  for (let i = 0; i < 1000; i++) {
    users.push({
      email: `k6-auth-test-${i}@example.com`,
      name: `K6 Auth Test ${i}`,
    });
  }
  return users;
});

// Test options
export const options = {
  scenarios: {
    // Login load test
    login_load: {
      executor: 'ramping-constantarrivalrate',
      startRate: 10,
      timeUnit: '1s',
      preAllocatedVUs: 100,
      maxVUs: 500,
      stages: [
        { duration: '1m', target: 100 },  // Ramp up to 100 users/sec
        { duration: '3m', target: 100 },  // Sustain 100 users/sec
        { duration: '1m', target: 200 },  // Ramp up to 200 users/sec
        { duration: '3m', target: 200 },  // Sustain 200 users/sec
        { duration: '1m', target: 0 },    // Ramp down
      ],
      exec: 'loginScenario',
      startTime: '0s',
    },

    // Registration load test
    registration_load: {
      executor: 'constant-arrival-rate',
      rate: 20,
      timeUnit: '1s',
      preAllocatedVUs: 50,
      maxVUs: 200,
      duration: '5m',
      exec: 'registrationScenario',
      startTime: '0s',
    },
  },

  thresholds: CONFIG.AUTH_THRESHOLDS,
};

// Setup - create initial test user
export function setup() {
  // This user will be used for login tests
  const email = `k6-login-test-${Date.now()}@example.com`;
  console.log(`Setup: Creating test user ${email}`);
  return { email };
}

// Scenario 1: Login under load
export function loginScenario(data) {
  group('Authentication - Login', function () {
    const loginPayload = JSON.stringify({
      email: CONFIG.TEST_USER.email,
      password: CONFIG.TEST_USER.password,
    });

    const params = {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      tags: { name: 'Login' },
    };

    const res = http.post(`${CONFIG.BASE_URL}/auth/login`, loginPayload, params);

    const success = check(res, {
      'status is 200': (r) => r.status === 200,
      'has access token': (r) => r.json('access_token') !== undefined,
      'has refresh token': (r) => r.json('refresh_token') !== undefined,
      'has user data': (r) => r.json('user') !== undefined,
      'response time < 500ms': (r) => r.timings.duration < 500,
    });

    authErrorRate.add(!success);

    if (!success) {
      console.error(`Login failed: ${res.status} - ${res.body.substring(0, 200)}`);
    }
  });
}

// Scenario 2: Registration under load
export function registrationScenario() {
  const user = testUsers[__VU % testUsers.length];

  group('Authentication - Register', function () {
    const registerPayload = JSON.stringify({
      name: user.name,
      email: `${user.email}-${__VU}-${Date.now()}`, // Ensure uniqueness
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

    const success = check(res, {
      'status is 201 or 200': (r) => r.status === 201 || r.status === 200,
      'has access token': (r) => r.json('access_token') !== undefined,
      'response time < 1000ms': (r) => r.timings.duration < 1000,
    });

    authErrorRate.add(!success);
  });
}

// Teardown
export function teardown(data) {
  console.log('Teardown: Auth test completed');
}
