// k6 Stress Test
// Tests system limits by gradually increasing load to breaking point
// WARNING: This test is designed to find the breaking point
// Run: k6 run scenarios/06-stress-test.js

import http from 'k6/http';
import { check, group } from 'k6';
import { SharedArray } from 'k6/data';
import { CONFIG } from '../config.js';
import { login, getAuthHeaders } from '../helpers/auth.js';

// Test data
const endpoints = new SharedArray('endpoints', function () {
  return [
    { path: '/home', method: 'GET' },
    { path: '/komunitas', method: 'GET' },
    { path: '/threads/main', method: 'GET' },
    { path: '/tips', method: 'GET' },
    { path: '/shop/all', method: 'GET' },
    { path: '/recomendation/sports/all', method: 'GET' },
    { path: '/postpartum', method: 'GET' },
    { path: '/user-data', method: 'GET' },
    { path: '/profile', method: 'GET' },
    { path: '/products', method: 'GET' },
  ];
});

// Stress test options - aggressive ramp up
export const options = {
  scenarios: {
    // Stress test - find breaking point
    stress_test: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '2m', target: 500 },   // Initial ramp
        { duration: '3m', target: 1000 },  // Increase
        { duration: '3m', target: 2000 },  // High load
        { duration: '3m', target: 3000 },  // Very high load
        { duration: '3m', target: 4000 },  // Extreme load
        { duration: '5m', target: 5000 },  // Breaking point attempt
        { duration: '5m', target: 5000 },  // Sustain at max
        { duration: '3m', target: 2000 },  // Recovery test
        { duration: '2m', target: 0 },     // Cool down
      ],
      gracefulStop: '30s',
    },

    // Spike test - sudden load increase
    spike_test: {
      executor: 'constant-vus',
      vus: 100,
      duration: '2m',
      exec: 'spikeTest',
      startTime: '25m', // Run after stress test cools down
      gracefulStop: '30s',
    },
  },

  thresholds: {
    // Relaxed thresholds for stress testing
    http_req_duration: ['p(95)<5000', 'p(99)<10000'], // Allow slower responses
    http_req_failed: ['rate<0.10'], // Allow up to 10% failure rate during stress
  },

  // Timeouts for stress testing
  timeout: '5m',
};

// Setup
export function setup() {
  console.log('========================================');
  console.log('STRESS TEST INITIATED');
  console.log('Target URL:', CONFIG.BASE_URL);
  console.log('WARNING: This test will push the system to its limits');
  console.log('========================================');

  const token = login(CONFIG.TEST_USER.email, CONFIG.TEST_USER.password);
  if (!token) {
    throw new Error('Setup failed: Could not login');
  }

  return {
    token,
    testStartTime: Date.now(),
  };
}

// Main stress test - random endpoint access
export default function stressTest(data) {
  const headers = getAuthHeaders();

  // Pick random endpoint
  const endpoint = endpoints[Math.floor(Math.random() * endpoints.length)];

  const res = http.get(`${CONFIG.BASE_URL}${endpoint.path}`, {
    headers,
    tags: {
      name: 'StressTest',
      endpoint: endpoint.path.replace('/', ''),
    },
    timeout: '30s', // Allow longer timeout during stress
  });

  // Check with relaxed thresholds
  const success = check(res, {
    'status acceptable': (r) => r.status < 500, // Accept 4xx errors during stress
    'response received': (r) => r.status !== 0,
  });

  // Log critical failures
  if (res.status === 0 || res.status >= 500) {
    console.error(`Critical failure: ${endpoint.path} returned ${res.status}`);
  }

  // Minimal sleep - max out the server
  // In real stress tests, we want to minimize think time
}

// Spike test - sudden burst of traffic
export function spikeTest(data) {
  const headers = getAuthHeaders();

  // Rapid-fire requests without sleep
  for (let i = 0; i < 10; i++) {
    const endpoint = endpoints[Math.floor(Math.random() * endpoints.length)];

    http.get(`${CONFIG.BASE_URL}${endpoint.path}`, {
      headers,
      tags: { name: 'SpikeTest' },
    });
  }

  // Small pause between bursts
  sleep(0.1);
}

// Teardown with detailed statistics
export function teardown(data) {
  const testDuration = (Date.now() - data.testStartTime) / 1000;

  console.log('========================================');
  console.log('STRESS TEST COMPLETED');
  console.log(`Duration: ${Math.floor(testDuration)} seconds`);
  console.log('');
  console.log('IMPORTANT: Review the following:');
  console.log('1. At what VU count did error rate spike?');
  console.log('2. At what VU count did response time degrade significantly?');
  console.log('3. Did the system recover after load reduction?');
  console.log('4. Check server logs for any errors during peak load');
  console.log('========================================');
}
