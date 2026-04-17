// k6 Data Heavy Load Test
// Tests data-heavy endpoints (shop, products, protected data)
// Run: k6 run scenarios/04-data-heavy-test.js

import http from 'k6/http';
import { check, group } from 'k6';
import { SharedArray } from 'k6/data';
import { CONFIG } from '../config.js';
import { login, getAuthHeaders } from '../helpers/auth.js';

// Test data
const productIds = new SharedArray('productIds', function () {
  return Array.from({ length: 50 }, (_, i) => i + 1);
});

// Test options
export const options = {
  scenarios: {
    // Shop/products read test
    shop_read: {
      executor: 'ramping-constantarrivalrate',
      startRate: 20,
      timeUnit: '1s',
      preAllocatedVUs: 100,
      maxVUs: 500,
      stages: [
        { duration: '2m', target: 100 },
        { duration: '3m', target: 100 },
        { duration: '2m', target: 300 },
        { duration: '3m', target: 300 },
        { duration: '1m', target: 0 },
      ],
      exec: 'shopReadScenario',
      startTime: '0s',
    },

    // Protected data test
    protected_data: {
      executor: 'constant-arrival-rate',
      rate: 30,
      timeUnit: '1s',
      preAllocatedVUs: 100,
      maxVUs: 300,
      duration: '5m',
      exec: 'protectedDataScenario',
      startTime: '0s',
    },

    // Dashboard data test
    dashboard_data: {
      executor: 'constant-arrival-rate',
      rate: 20,
      timeUnit: '1s',
      preAllocatedVUs: 80,
      maxVUs: 200,
      duration: '5m',
      exec: 'dashboardScenario',
      startTime: '0s',
    },
  },

  thresholds: CONFIG.DATA_HEAVY_THRESHOLDS,
};

// Setup
export function setup() {
  const token = login(CONFIG.TEST_USER.email, CONFIG.TEST_USER.password);
  if (!token) {
    throw new Error('Setup failed: Could not login');
  }
  return { token };
}

// Scenario 1: Shop and products read
export function shopReadScenario() {
  group('Shop - Read Operations', function () {
    const headers = getAuthHeaders();

    // Get all shop items
    group('GET /shop/all', function () {
      const res = http.get(`${CONFIG.BASE_URL}/shop/all`, {
        headers,
        tags: { name: 'GetAllShop' },
      });

      check(res, {
        'status is 200': (r) => r.status === 200,
        'has data': (r) => r.json('data') !== undefined,
        'response time < 1000ms': (r) => r.timings.duration < 1000,
      });
    });

    // Get user's shop
    group('GET /shop', function () {
      const res = http.get(`${CONFIG.BASE_URL}/shop`, {
        headers,
        tags: { name: 'GetUserShop' },
      });

      check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 800ms': (r) => r.timings.duration < 800,
      });
    });

    // Get products
    group('GET /products', function () {
      const res = http.get(`${CONFIG.BASE_URL}/products`, {
        headers,
        tags: { name: 'GetProducts' },
      });

      check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 1000ms': (r) => r.timings.duration < 1000,
      });
    });

    // Get specific product details
    if (Math.random() > 0.3) {
      const productId = productIds[Math.floor(Math.random() * productIds.length)];

      group(`GET /products/${productId}`, function () {
        const res = http.get(
          `${CONFIG.BASE_URL}/products/${productId}`,
          {
            headers,
            tags: { name: 'GetProductDetail' },
          }
        );

        check(res, {
          'status is 200 or 404': (r) => r.status === 200 || r.status === 404,
          'response time < 500ms': (r) => r.timings.duration < 500,
        });
      });
    }

    // Get product reviews
    if (Math.random() > 0.5) {
      const productId = productIds[Math.floor(Math.random() * productIds.length)];

      group(`GET /shop/${productId}/reviews`, function () {
        const res = http.get(
          `${CONFIG.BASE_URL}/shop/${productId}/reviews`,
          {
            headers,
            tags: { name: 'GetProductReviews' },
          }
        );

        check(res, {
          'status is 200': (r) => r.status === 200,
          'response time < 800ms': (r) => r.timings.duration < 800,
        });
      });
    }
  });
}

// Scenario 2: Protected data endpoints
export function protectedDataScenario() {
  group('Protected Data - Access', function () {
    const headers = getAuthHeaders();

    // Get user data
    group('GET /user-data', function () {
      const res = http.get(`${CONFIG.BASE_URL}/user-data`, {
        headers,
        tags: { name: 'GetUserData' },
      });

      check(res, {
        'status is 200': (r) => r.status === 200,
        'has user data': (r) => r.json('data') !== undefined,
        'response time < 800ms': (r) => r.timings.duration < 800,
      });
    });

    // Get profile
    group('GET /profile', function () {
      const res = http.get(`${CONFIG.BASE_URL}/profile`, {
        headers,
        tags: { name: 'GetProfile' },
      });

      check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 800ms': (r) => r.timings.duration < 800,
      });
    });

    // Get kick counter data
    group('GET /kick-counter', function () {
      const res = http.get(`${CONFIG.BASE_URL}/kick-counter`, {
        headers,
        tags: { name: 'GetKickCounter' },
      });

      check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 800ms': (r) => r.timings.duration < 800,
      });
    });

    // Get water intake
    group('GET /water-intake', function () {
      const res = http.get(`${CONFIG.BASE_URL}/water-intake`, {
        headers,
        tags: { name: 'GetWaterIntake' },
      });

      check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 800ms': (r) => r.timings.duration < 800,
      });
    });
  });
}

// Scenario 3: Dashboard and aggregated data
export function dashboardScenario() {
  group('Dashboard - Aggregated Data', function () {
    const headers = getAuthHeaders();

    // Get home data
    group('GET /home', function () {
      const res = http.get(`${CONFIG.BASE_URL}/home`, {
        headers,
        tags: { name: 'GetHome' },
      });

      check(res, {
        'status is 200': (r) => r.status === 200,
        'has dashboard data': (r) => r.json('data') !== undefined,
        'response time < 1500ms': (r) => r.timings.duration < 1500,
      });
    });

    // Get catatan history
    group('GET /catatan/history', function () {
      const res = http.get(`${CONFIG.BASE_URL}/catatan/history`, {
        headers,
        tags: { name: 'GetCatatanHistory' },
      });

      check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 1000ms': (r) => r.timings.duration < 1000,
      });
    });

    // Get deteksi history
    group('GET /deteksi/history', function () {
      const res = http.get(`${CONFIG.BASE_URL}/deteksi/history`, {
        headers,
        tags: { name: 'GetDeteksiHistory' },
      });

      check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 1000ms': (r) => r.timings.duration < 1000,
      });
    });

    // Get pregnancy calculator data
    group('GET /pregnancy-calculator/my', function () {
      const res = http.get(
        `${CONFIG.BASE_URL}/pregnancy-calculator/my`,
        {
          headers,
          tags: { name: 'GetPregnancyCalculator' },
        }
      );

      check(res, {
        'status is 200 or 404': (r) => r.status === 200 || r.status === 404,
        'response time < 1000ms': (r) => r.timings.duration < 1000,
      });
    });
  });
}

export function teardown() {
  console.log('Teardown: Data heavy test completed');
}
