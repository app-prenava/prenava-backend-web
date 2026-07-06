import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { CONFIG } from '../config.js';
import { login, getAuthHeaders } from '../helpers/auth.js';

export let options = {
    stages: [
        { duration: '1m', target: 1 },
        { duration: '30s', target: 25 },
        { duration: '5m', target: 25 },
        { duration: '1m', target: 35 },
        { duration: '2m', target: 35 },
        { duration: '30s', target: 0 },
        { duration: '10s', target: 50 },
        { duration: '30s', target: 0 },
    ],
    thresholds: {
        http_req_duration: ['p(95)<3000'],
    }
};

export function setup() {
    console.log('========================================');
    console.log('STUNTING PREDICTION TEST INITIATED');
    console.log('Target URL:', CONFIG.BASE_URL);
    console.log('========================================');
  
    const token = login(CONFIG.TEST_USER.email, CONFIG.TEST_USER.password);
    if (!token) throw new Error('Setup failed: Could not login');
    return { token };
}

export default function (data) {
    const headers = getAuthHeaders(data.token);
    headers['Content-Type'] = 'application/json';

    group('Stunting Prediction Endpoint', function () {
        const stuntingUrl = `${CONFIG.BASE_URL}/stunting/predict`;
        const stuntingPayload = JSON.stringify({
            "mother_height": 155,
            "mother_weight": 60,
            "maternal_hemoglobin": 11.5,
            "mother_age": 25,
            "mother_education": 12,
            "anc_visits_total": 5,
            "iron_pills": 1,
            "pregnancy_sickness": 0
        });

        const resStunting = http.post(stuntingUrl, stuntingPayload, { headers: headers });
        check(resStunting, {
            'Stunting status is 200': (r) => r.status === 200,
        });
    });

    sleep(1);
}
