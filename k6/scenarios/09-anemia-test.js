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
    console.log('ANEMIA SCAN TEST INITIATED');
    console.log('Target URL:', CONFIG.BASE_URL);
    console.log('========================================');
  
    const token = login(CONFIG.TEST_USER.email, CONFIG.TEST_USER.password);
    if (!token) throw new Error('Setup failed: Could not login');
    return { token };
}

export default function (data) {
    const headers = getAuthHeaders(data.token);
    headers['Content-Type'] = 'application/json';

    group('Anemia Scan Endpoint', function () {
        const anemiaUrl = `${CONFIG.BASE_URL}/anemia-scan`;
        const anemiaPayload = JSON.stringify({
            "image_data": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAAAAAAAD/2wBDAAI..."
        });

        const resAnemia = http.post(anemiaUrl, anemiaPayload, { headers: headers });
        check(resAnemia, {
            'Anemia status is < 500': (r) => r.status < 500,
        });
    });

    sleep(1);
}
