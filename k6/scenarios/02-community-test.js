// k6 Community Load Test
// Tests community endpoints (komunitas, threads, tips) under load
// These are high-read endpoints that should benefit from caching
// Run: k6 run scenarios/02-community-test.js

import http from 'k6/http';
import { check, group } from 'k6';
import { SharedArray } from 'k6/data';
import { CONFIG } from '../config.js';
import { login, getAuthHeaders } from '../helpers/auth.js';

// Test data
const threadIds = new SharedArray('threadIds', function () {
  // In production, fetch actual thread IDs or generate test data
  return Array.from({ length: 100 }, (_, i) => i + 1);
});

// Test options
export const options = {
  scenarios: {
    // Community read test
    community_read: {
      executor: 'ramping-constantarrivalrate',
      startRate: 50,
      timeUnit: '1s',
      preAllocatedVUs: 200,
      maxVUs: 1000,
      stages: [
        { duration: '2m', target: 200 },   // Ramp to 200 RPS
        { duration: '5m', target: 200 },   // Sustain
        { duration: '2m', target: 500 },   // Ramp to 500 RPS
        { duration: '5m', target: 500 },   // Sustain
        { duration: '2m', target: 1000 },  // Ramp to 1000 RPS
        { duration: '5m', target: 1000 },  // Sustain
        { duration: '1m', target: 0 },     // Ramp down
      ],
      exec: 'communityReadScenario',
      startTime: '0s',
    },

    // Community write test
    community_write: {
      executor: 'constant-arrival-rate',
      rate: 10,
      timeUnit: '1s',
      preAllocatedVUs: 50,
      maxVUs: 200,
      duration: '10m',
      exec: 'communityWriteScenario',
      startTime: '0s',
    },

    // Tips and articles read test
    tips_read: {
      executor: 'ramping-constantarrivalrate',
      startRate: 30,
      timeUnit: '1s',
      preAllocatedVUs: 150,
      maxVUs: 500,
      stages: [
        { duration: '2m', target: 100 },
        { duration: '5m', target: 100 },
        { duration: '2m', target: 300 },
        { duration: '5m', target: 300 },
        { duration: '1m', target: 0 },
      ],
      exec: 'tipsReadScenario',
      startTime: '0s',
    },
  },

  thresholds: CONFIG.COMMUNITY_THRESHOLDS,
};

// Setup - login and get token
export function setup() {
  const token = login(CONFIG.TEST_USER.email, CONFIG.TEST_USER.password);
  if (!token) {
    throw new Error('Setup failed: Could not login');
  }
  return { token };
}

// Scenario 1: Community read operations
export function communityReadScenario(data) {
  group('Community - Read Operations', function () {
    const headers = getAuthHeaders();

    // Test komunitas list endpoint
    group('GET /komunitas', function () {
      const res = http.get(`${CONFIG.BASE_URL}/komunitas`, {
        headers,
        tags: { name: 'GetKomunitas' },
      });

      check(res, {
        'status is 200': (r) => r.status === 200,
        'has data': (r) => r.json('data') !== undefined,
        'response time < 400ms': (r) => r.timings.duration < 400,
      });
    });

    // Test threads list endpoint
    group('GET /threads/main', function () {
      const res = http.get(`${CONFIG.BASE_URL}/threads/main`, {
        headers,
        tags: { name: 'GetThreads' },
      });

      check(res, {
        'status is 200': (r) => r.status === 200,
        'has threads': (r) => r.json('data') !== undefined,
        'response time < 400ms': (r) => r.timings.duration < 400,
      });
    });

    // Random thread detail
    if (Math.random() > 0.5) {
      const threadId = threadIds[Math.floor(Math.random() * threadIds.length)];

      group(`GET /threads/detail/${threadId}`, function () {
        const res = http.get(
          `${CONFIG.BASE_URL}/threads/detail/${threadId}`,
          {
            headers,
            tags: { name: 'GetThreadDetail' },
          }
        );

        check(res, {
          'status is 200': (r) => r.status === 200,
          'response time < 500ms': (r) => r.timings.duration < 500,
        });
      });
    }
  });
}

// Scenario 2: Community write operations
export function communityWriteScenario() {
  group('Community - Write Operations', function () {
    const headers = getAuthHeaders();

    // Test creating a thread
    group('POST /threads/create', function () {
      const payload = JSON.stringify({
        title: `K6 Load Test Thread ${__VU}-${Date.now()}`,
        content: 'This is a load test thread. Please ignore.',
        category: 'general',
      });

      const res = http.post(
        `${CONFIG.BASE_URL}/threads/create`,
        payload,
        {
          headers,
          tags: { name: 'CreateThread' },
        }
      );

      check(res, {
        'status is 200 or 201': (r) => r.status === 200 || r.status === 201,
        'response time < 1000ms': (r) => r.timings.duration < 1000,
      });
    });

    // Test adding a like
    if (Math.random() > 0.3) {
      const threadId = threadIds[Math.floor(Math.random() * threadIds.length)];

      group(`POST /threads/like/${threadId}`, function () {
        const res = http.post(
          `${CONFIG.BASE_URL}/threads/like/${threadId}`,
          null,
          {
            headers,
            tags: { name: 'LikeThread' },
          }
        );

        check(res, {
          'status is 200': (r) => r.status === 200,
          'response time < 500ms': (r) => r.timings.duration < 500,
        });
      });
    }
  });
}

// Scenario 3: Tips and articles read
export function tipsReadScenario() {
  group('Tips - Read Operations', function () {
    const headers = getAuthHeaders();

    // Get tips categories
    group('GET /tips/categories', function () {
      const res = http.get(
        `${CONFIG.BASE_URL}/tips/categories`,
        {
          headers,
          tags: { name: 'GetTipCategories' },
        }
      );

      check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 300ms': (r) => r.timings.duration < 300,
      });
    });

    // Get tips list
    group('GET /tips', function () {
      const res = http.get(`${CONFIG.BASE_URL}/tips`, {
        headers,
        tags: { name: 'GetTips' },
      });

      check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 400ms': (r) => r.timings.duration < 400,
      });
    });

    // Get postpartum articles
    group('GET /postpartum', function () {
      const res = http.get(`${CONFIG.BASE_URL}/postpartum`, {
        headers,
        tags: { name: 'GetPostpartumArticles' },
      });

      check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 400ms': (r) => r.timings.duration < 400,
      });
    });
  });
}

export function teardown() {
  console.log('Teardown: Community test completed');
}
