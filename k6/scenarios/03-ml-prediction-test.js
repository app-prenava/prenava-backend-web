// k6 ML Prediction Load Test
// Tests ML-based endpoints (sports recommendation, depression prediction)
// Run: k6 run scenarios/03-ml-prediction-test.js

import http from 'k6/http';
import { check, group } from 'k6';
import { CONFIG } from '../config.js';
import { login, getAuthHeaders } from '../helpers/auth.js';

// Test options
export const options = {
  scenarios: {
    // Sports recommendation test
    sports_recommendation: {
      executor: 'constant-arrival-rate',
      rate: 10,
      timeUnit: '1s',
      preAllocatedVUs: 50,
      maxVUs: 200,
      duration: '5m',
      exec: 'sportsRecommendationScenario',
      startTime: '0s',
    },

    // Depression prediction test
    depression_prediction: {
      executor: 'constant-arrival-rate',
      rate: 5,
      timeUnit: '1s',
      preAllocatedVUs: 30,
      maxVUs: 100,
      duration: '5m',
      exec: 'depressionPredictionScenario',
      startTime: '0s',
    },

    // Anemia detection test
    anemia_detection: {
      executor: 'constant-arrival-rate',
      rate: 3,
      timeUnit: '1s',
      preAllocatedVUs: 20,
      maxVUs: 50,
      duration: '5m',
      exec: 'anemiaDetectionScenario',
      startTime: '0s',
    },
  },

  thresholds: CONFIG.ML_THRESHOLDS,
};

// Setup
export function setup() {
  const token = login(CONFIG.TEST_USER.email, CONFIG.TEST_USER.password);
  if (!token) {
    throw new Error('Setup failed: Could not login');
  }
  return { token };
}

// Scenario 1: Sports recommendation
export function sportsRecommendationScenario() {
  group('ML - Sports Recommendation', function () {
    const headers = getAuthHeaders();

    // Get sports recommendations
    group('GET /recomendation/sports/get', function () {
      const res = http.get(
        `${CONFIG.BASE_URL}/recomendation/sports/get`,
        {
          headers,
          tags: { name: 'GetSportsRecommendation' },
        }
      );

      check(res, {
        'status is 200': (r) => r.status === 200,
        'has recommendations': (r) => r.json('data') !== undefined,
        'response time < 2000ms': (r) => r.timings.duration < 2000,
      });
    });

    // Get all sports
    group('GET /recomendation/sports/all', function () {
      const res = http.get(
        `${CONFIG.BASE_URL}/recomendation/sports/all`,
        {
          headers,
          tags: { name: 'GetAllSports' },
        }
      );

      check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 1000ms': (r) => r.timings.duration < 1000,
      });
    });

    // Create sports recommendation (with random data)
    if (Math.random() > 0.7) {
      group('POST /recomendation/sports/create', function () {
        const payload = JSON.stringify({
          pregnancy_week: Math.floor(Math.random() * 40) + 1,
          activity_level: ['low', 'medium', 'high'][Math.floor(Math.random() * 3)],
          health_condition: 'none',
        });

        const res = http.post(
          `${CONFIG.BASE_URL}/recomendation/sports/create`,
          payload,
          {
            headers,
            tags: { name: 'CreateSportsRecommendation' },
          }
        );

        check(res, {
          'status is 200 or 201': (r) => r.status === 200 || r.status === 201,
          'response time < 3000ms': (r) => r.timings.duration < 3000,
        });
      });
    }
  });
}

// Scenario 2: Depression prediction
export function depressionPredictionScenario() {
  group('ML - Depression Prediction', function () {
    const headers = getAuthHeaders();

    // Get depression predictions
    group('GET /prediksidepresi', function () {
      const res = http.get(
        `${CONFIG.BASE_URL}/prediksidepresi`,
        {
          headers,
          tags: { name: 'GetDepressionPredictions' },
        }
      );

      check(res, {
        'status is 200': (r) => r.status === 200,
        'has data': (r) => r.json('data') !== undefined,
        'response time < 2000ms': (r) => r.timings.duration < 2000,
      });
    });

    // Create depression prediction (EPDS score based)
    if (Math.random() > 0.5) {
      group('POST /prediksidepresi/store', function () {
        // Generate random EPDS scores (0-30 scale)
        const epdsScores = Array.from({ length: 10 }, () =>
          Math.floor(Math.random() * 4)
        );
        const totalScore = epdsScores.reduce((a, b) => a + b, 0);

        const payload = JSON.stringify({
          epds_score: totalScore,
          answers: epdsScores,
          pregnancy_week: Math.floor(Math.random() * 40) + 1,
        });

        const res = http.post(
          `${CONFIG.BASE_URL}/prediksidepresi/store`,
          payload,
          {
            headers,
            tags: { name: 'CreateDepressionPrediction' },
          }
        );

        check(res, {
          'status is 200 or 201': (r) => r.status === 200 || r.status === 201,
          'has prediction': (r) => r.json('data') !== undefined,
          'response time < 3000ms': (r) => r.timings.duration < 3000,
        });
      });
    }
  });
}

// Scenario 3: Anemia detection (simulated - normally requires image upload)
export function anemiaDetectionScenario() {
  group('ML - Anemia Detection', function () {
    const headers = getAuthHeaders();

    // Note: This endpoint normally requires image upload
    // For load testing, we're testing the endpoint's responsiveness
    // In real scenarios, you'd need to handle multipart/form-data

    group('GET /deteksi/latest', function () {
      const res = http.get(
        `${CONFIG.BASE_URL}/deteksi/latest`,
        {
          headers,
          tags: { name: 'GetLatestDetection' },
        }
      );

      check(res, {
        'status is 200 or 404': (r) => r.status === 200 || r.status === 404,
        'response time < 1000ms': (r) => r.timings.duration < 1000,
      });
    });

    // Get detection history
    group('GET /deteksi/history', function () {
      const res = http.get(
        `${CONFIG.BASE_URL}/deteksi/history`,
        {
          headers,
          tags: { name: 'GetDetectionHistory' },
        }
      );

      check(res, {
        'status is 200': (r) => r.status === 200,
        'response time < 1000ms': (r) => r.timings.duration < 1000,
      });
    });
  });
}

export function teardown() {
  console.log('Teardown: ML prediction test completed');
}
