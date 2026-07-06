import http from 'k6/http';
import { check, sleep } from 'k6';
import { CONFIG } from '../config.js';
import { login, getAuthHeaders } from '../helpers/auth.js';

// Konfigurasi Test (Bisa diubah-ubah)
export let options = {
    stages: [
        { duration: '30s', target: 5 },  // Naik ke 5 user
        { duration: '1m', target: 5 },   // Tahan di 5 user (API eksternal/Gemini bisa kena rate limit jika terlalu besar)
        { duration: '10s', target: 0 },  // Turun ke 0 user
    ],
    thresholds: {
        http_req_duration: ['p(95)<15000'], // Menggunakan threshold 15 detik karena API Gemini bisa lambat
    }
};

export function setup() {
    console.log('========================================');
    console.log('MEAL PLAN (GEMINI) TEST INITIATED');
    console.log('Target URL:', CONFIG.BASE_URL);
    console.log('WARNING: Jaga VU (Virtual Users) tetap rendah untuk menghindari Rate Limit API Gemini');
    console.log('========================================');
  
    const token = login(CONFIG.TEST_USER.email, CONFIG.TEST_USER.password);
    if (!token) {
        throw new Error('Setup failed: Could not login');
    }
  
    return { token };
}

export default function (data) {
    // If auth is needed, you can use the token. Since our new endpoint doesn't explicitly require auth in this test,
    // we can still send the header just in case it's behind the auth middleware.
    let headers = {};
    if (data && data.token) {
        headers = getAuthHeaders(data.token);
    }
    headers['Content-Type'] = 'application/json';

    const url = `${CONFIG.BASE_URL}/api/meal-plan/generate`;

    const payload = JSON.stringify({
        risiko_stunting: "Tinggi",
        bmi_ibu: 28.5,
        trimester: 2,
        berat_badan: 75,
        tinggi_badan: 160,
        hb: 10.5,
        budget: "Terbatas",
        pantangan: "Alergi seafood, tidak suka susu"
    });

    const res = http.post(url, payload, { headers: headers, timeout: '30s' });

    check(res, {
        'status is 200': (r) => r.status === 200,
        'response valid JSON': (r) => {
            try {
                JSON.parse(r.body);
                return true;
            } catch (e) {
                return false;
            }
        },
        'response success is true': (r) => {
            try {
                return JSON.parse(r.body).success === true;
            } catch (e) {
                return false;
            }
        }
    });

    sleep(1);
}
