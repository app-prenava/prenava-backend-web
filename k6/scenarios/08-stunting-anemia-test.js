import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { CONFIG } from '../config.js';
import { login, getAuthHeaders } from '../helpers/auth.js';

// Konfigurasi Test Menggabungkan 4 Jenis Testing secara berurutan
export let options = {
    stages: [
        // 1. SMOKE TESTING: Cek biasa, stabil atau tidak (1 VU selama 1m)
        { duration: '1m', target: 1 },
        
        // 2. LOAD TESTING: Uji beban normal (naik ke 50 VU, tahan 5m)
        { duration: '30s', target: 50 },  // Ramp-up
        { duration: '5m', target: 50 },   // Tahan di 50 VU
        
        // 3. STRESS TESTING: Uji batas server (naik ke 200 VU, tahan 2m)
        { duration: '1m', target: 200 },  // Ramp-up
        { duration: '2m', target: 200 },  // Tahan di 200 VU
        { duration: '30s', target: 0 },   // Turun sejenak untuk persiapan spike

        // 4. SPIKE TESTING: Lonjakan tiba-tiba (1000 VU dalam 10s)
        { duration: '10s', target: 1000 },
        { duration: '30s', target: 0 },   // Cool down
    ],
    thresholds: {
        http_req_duration: ['p(95)<3000'], // 95% request harus selesai di bawah 3 detik
        http_req_failed: ['rate<0.15'],    // Kegagalan maksimal 15% (karena akan dihantam spike test)
    }
};

export function setup() {
    console.log('========================================');
    console.log('STUNTING & ANEMIA COMBINED TEST INITIATED');
    console.log('Test ini menggabungkan: Smoke -> Load -> Stress -> Spike Test');
    console.log('Target URL:', CONFIG.BASE_URL);
    console.log('========================================');
  
    const token = login(CONFIG.TEST_USER.email, CONFIG.TEST_USER.password);
    if (!token) {
        throw new Error('Setup failed: Could not login');
    }
  
    return { token };
}

export default function (data) {
    const headers = getAuthHeaders(data.token);
    headers['Content-Type'] = 'application/json';

    group('Stunting Prediction Endpoint', function () {
        const stuntingUrl = `${CONFIG.BASE_URL}/api/stunting/predict`;
        const stuntingPayload = JSON.stringify({
            "age": 25,
            "weight": 60,
            "height": 155,
            "maternal_hemoglobin": 11,
            "baby_weight_kg": 2.5,
            "history_of_stunting": "No"
        }); // Contoh payload prediksi stunting

        const resStunting = http.post(stuntingUrl, stuntingPayload, { headers: headers });
        check(resStunting, {
            'Stunting status is 200': (r) => r.status === 200,
        });
    });

    sleep(0.5); // Jeda kecil antar endpoint agar realistis

    group('Anemia Scan Endpoint', function () {
        const anemiaUrl = `${CONFIG.BASE_URL}/api/anemia-scan`;
        // Endpoint ini biasanya menerima file gambar (multipart/form-data)
        // Sebagai simulasi stress test dasar, kita kirim POST kosong atau parameter base64 string
        const anemiaPayload = JSON.stringify({
            "image_data": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAAAAAAAD/2wBDAAI..." // Dummy image
        });

        const resAnemia = http.post(anemiaUrl, anemiaPayload, { headers: headers });
        check(resAnemia, {
            // Kita terima 200 atau 400/422 karena ini simulasi dan image_data mungkin invalid, 
            // yang penting server merespon dan tidak mati (500)
            'Anemia status is < 500': (r) => r.status < 500,
        });
    });

    sleep(1); // Jeda tiap iterasi
}
