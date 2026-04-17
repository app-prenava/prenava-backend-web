// k6 Mixed Workload Load Test
// Simulates real-world usage patterns with mixed read/write operations
// This is the most comprehensive test scenario
// Run: k6 run scenarios/05-mixed-workload-test.js

import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { CONFIG } from '../config.js';
import { login, getAuthHeaders } from '../helpers/auth.js';

// Test data
const threadIds = new SharedArray('threadIds', function () {
  return Array.from({ length: 100 }, (_, i) => i + 1);
});

const tipIds = new SharedArray('tipIds', function () {
  return Array.from({ length: 50 }, (_, i) => i + 1);
});

// Test options - realistic user journey simulation
export const options = {
  scenarios: {
    // Mixed workload with realistic user behavior
    mixed_workload: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '2m', target: 100 },   // Ramp up
        { duration: '5m', target: 100 },   // Sustained load
        { duration: '2m', target: 500 },   // Ramp up
        { duration: '5m', target: 500 },   // Peak load
        { duration: '2m', target: 1000 },  // High load
        { duration: '5m', target: 1000 },  // Sustained high load
        { duration: '3m', target: 2000 },  // Stress level
        { duration: '5m', target: 2000 },  // Sustained stress
        { duration: '2m', target: 0 },     // Ramp down
      ],
      exec: 'userJourney',
    },
  },

  thresholds: {
    http_req_duration: ['p(95)<800', 'p(99)<2000'],
    http_req_failed: ['rate<0.02'],
    http_reqs: ['rate>100'],
  },
};

// Setup
export function setup() {
  console.log('Setup: Starting mixed workload test');
  const token = login(CONFIG.TEST_USER.email, CONFIG.TEST_USER.password);
  if (!token) {
    throw new Error('Setup failed: Could not login');
  }

  // Log test configuration
  console.log(`Target URL: ${CONFIG.BASE_URL}`);
  console.log(`Test user: ${CONFIG.TEST_USER.email}`);

  return {
    token,
    startTime: new Date().toISOString(),
  };
}

// Main user journey - simulates real app usage
export function userJourney(data) {
  const headers = getAuthHeaders();

  // Phase 1: User opens app and loads home/dashboard
  group('User Journey - App Launch', function () {
    // Get home data
    const homeRes = http.get(`${CONFIG.BASE_URL}/home`, {
      headers,
      tags: { name: 'Home' },
    });

    check(homeRes, {
      'home loaded': (r) => r.status === 200,
      'home load time < 1s': (r) => r.timings.duration < 1000,
    });

    sleep(Math.random() * 2 + 1); // 1-3 seconds thinking time
  });

  // Phase 2: User checks community/threads
  if (Math.random() > 0.2) {
    group('User Journey - Browse Community', function () {
      // Get threads
      const threadsRes = http.get(`${CONFIG.BASE_URL}/threads/main`, {
        headers,
        tags: { name: 'Threads' },
      });

      check(threadsRes, {
        'threads loaded': (r) => r.status === 200,
      });

      // Maybe view a thread detail
      if (Math.random() > 0.5) {
        const threadId = threadIds[Math.floor(Math.random() * threadIds.length)];
        const detailRes = http.get(
          `${CONFIG.BASE_URL}/threads/detail/${threadId}`,
          {
            headers,
            tags: { name: 'ThreadDetail' },
          }
        );

        check(detailRes, {
          'thread detail loaded': (r) => r.status === 200 || r.status === 404,
        });
      }

      sleep(Math.random() * 3 + 2); // 2-5 seconds reading time
    });
  }

  // Phase 3: User checks tips/articles
  if (Math.random() > 0.3) {
    group('User Journey - Read Tips', function () {
      const tipsRes = http.get(`${CONFIG.BASE_URL}/tips`, {
        headers,
        tags: { name: 'Tips' },
      });

      check(tipsRes, {
        'tips loaded': (r) => r.status === 200,
      });

      // Maybe get specific tip
      if (Math.random() > 0.6) {
        const tipId = tipIds[Math.floor(Math.random() * tipIds.length)];
        http.get(`${CONFIG.BASE_URL}/tips/${tipId}`, {
          headers,
          tags: { name: 'TipDetail' },
        });
      }

      sleep(Math.random() * 4 + 2); // 2-6 seconds reading time
    });
  }

  // Phase 4: User checks shop/products (occasionally)
  if (Math.random() > 0.5) {
    group('User Journey - Browse Shop', function () {
      const shopRes = http.get(`${CONFIG.BASE_URL}/shop/all`, {
        headers,
        tags: { name: 'Shop' },
      });

      check(shopRes, {
        'shop loaded': (r) => r.status === 200,
        'shop load time < 1.5s': (r) => r.timings.duration < 1500,
      });

      sleep(Math.random() * 3 + 1); // 1-4 seconds browsing time
    });
  }

  // Phase 5: User performs write operation (occasionally)
  if (Math.random() > 0.7) {
    group('User Journey - Interact', function () {
      const actions = [
        // Like a thread
        () => {
          const threadId = threadIds[Math.floor(Math.random() * threadIds.length)];
          return http.post(
            `${CONFIG.BASE_URL}/threads/like/${threadId}`,
            null,
            {
              headers,
              tags: { name: 'LikeThread' },
            }
          );
        },
        // Add comment
        () => {
          const threadId = threadIds[Math.floor(Math.random() * threadIds.length)];
          return http.post(
            `${CONFIG.BASE_URL}/komunitas/komen/add/${threadId}`,
            JSON.stringify({
              konten: `K6 load test comment ${Date.now()}`,
            }),
            {
              headers,
              tags: { name: 'AddComment' },
            }
          );
        },
        // Get recommendations
        () => {
          return http.get(
            `${CONFIG.BASE_URL}/recomendation/sports/get`,
            {
              headers,
              tags: { name: 'GetRecommendations' },
            }
          );
        },
      ];

      const action = actions[Math.floor(Math.random() * actions.length)];
      const res = action();

      check(res, {
        'action successful': (r) => r.status === 200 || r.status === 201,
      });

      sleep(Math.random() * 2 + 1); // 1-3 seconds
    });
  }

  // Phase 6: User checks personal data (occasionally)
  if (Math.random() > 0.6) {
    group('User Journey - Personal Data', function () {
      const endpoints = [
        '/user-data',
        '/profile',
        '/kick-counter',
        '/water-intake',
        '/pregnancy-calculator/my',
      ];

      const endpoint = endpoints[Math.floor(Math.random() * endpoints.length)];
      const res = http.get(`${CONFIG.BASE_URL}${endpoint}`, {
        headers,
        tags: { name: 'PersonalData' },
      });

      check(res, {
        'personal data loaded': (r) => r.status === 200 || r.status === 404,
      });

      sleep(Math.random() * 2 + 1); // 1-3 seconds
    });
  }

  // Brief pause between iterations (simulating real user behavior)
  sleep(Math.random() * 2 + 1);
}

// Teardown
export function teardown(data) {
  const endTime = new Date().toISOString();
  const duration = new Date(endTime) - new Date(data.startTime);

  console.log('========================================');
  console.log('Mixed Workload Test Completed');
  console.log(`Start Time: ${data.startTime}`);
  console.log(`End Time: ${endTime}`);
  console.log(`Duration: ${Math.floor(duration / 1000)} seconds`);
  console.log('========================================');
}
