// k6 Configuration for Prenava Backend Performance Testing
// Base URL and environment configuration

export const CONFIG = {
  // Production API URL
  BASE_URL: __ENV.API_URL || 'https://prenavabe.cloud/api',

  // Test credentials (use dedicated test accounts only!)
  TEST_USER: {
    email: __ENV.TEST_EMAIL || 'k6-test@example.com',
    password: __ENV.TEST_PASSWORD || 'TestPassword123!',
    name: 'K6 Load Test User'
  },

  // Admin credentials for admin endpoint testing
  ADMIN_USER: {
    email: __ENV.ADMIN_EMAIL || 'admin@prenava.test',
    password: __ENV.ADMIN_PASSWORD || 'AdminPassword123!'
  },

  // Test thresholds
  THRESHOLDS: {
    // HTTP request duration thresholds (milliseconds)
    http_req_duration: ['p(95)<500', 'p(99)<1000'], // 95% under 500ms, 99% under 1s

    // HTTP request failed rate (percentage)
    http_req_failed: ['rate<0.01'], // Less than 1% failures

    // HTTP requests per second
    http_reqs: ['rate>10'], // At least 10 requests per second
  },

  // Scenario-specific thresholds
  AUTH_THRESHOLDS: {
    http_req_duration: ['p(95)<300', 'p(99)<500'], // Auth should be fast
    http_req_failed: ['rate<0.005'], // Less than 0.5% failures for auth
  },

  COMMUNITY_THRESHOLDS: {
    http_req_duration: ['p(95)<400', 'p(99)<800'], // Community endpoints
    http_req_failed: ['rate<0.01'],
  },

  ML_THRESHOLDS: {
    http_req_duration: ['p(95)<2000', 'p(99)<5000'], // ML predictions can be slower
    http_req_failed: ['rate<0.02'], // Allow slightly higher failure rate for ML
  },

  DATA_HEAVY_THRESHOLDS: {
    http_req_duration: ['p(95)<1000', 'p(99)<2000'], // Data-heavy endpoints
    http_req_failed: ['rate<0.01'],
  },
};

// Export for use in tests
export default CONFIG;
