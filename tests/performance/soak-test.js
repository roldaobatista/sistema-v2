import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

// ============================================================
// KALIBRIUM ERP — k6 Soak Test
// Objetivo: detectar memory leaks e degradação ao longo do tempo
// ============================================================

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000/api/v1';
const errorRate = new Rate('errors');

export const options = {
    scenarios: {
        soak: {
            executor: 'constant-vus',
            vus: 50,
            duration: '2h',
        },
    },

    thresholds: {
        http_req_duration: ['p(95)<1000'],
        errors: ['rate<0.01'],
    },
};

export default function () {
    const loginRes = http.post(`${BASE_URL}/login`, JSON.stringify({
        email: __ENV.TEST_EMAIL || 'admin@kalibrium.com',
        password: __ENV.TEST_PASSWORD || 'password',
    }), {
        headers: { 'Content-Type': 'application/json' },
    });

    if (loginRes.status !== 200) {
        errorRate.add(1);
        sleep(2);
        return;
    }

    const token = loginRes.json('data.token');
    const headers = {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
        'X-Company-Id': __ENV.COMPANY_ID || '1',
    };

    // Mix de operações (simula uso real por 2h)
    const ops = [
        () => http.get(`${BASE_URL}/work-orders?page=1`, { headers }),
        () => http.get(`${BASE_URL}/customers?page=1`, { headers }),
        () => http.get(`${BASE_URL}/quotes?page=1`, { headers }),
        () => http.get(`${BASE_URL}/products?page=1`, { headers }),
        () => http.get(`${BASE_URL}/stock/movements?page=1`, { headers }),
    ];

    const op = ops[Math.floor(Math.random() * ops.length)];
    const res = op();

    check(res, {
        'soak: status ok': (r) => r.status === 200,
    }) || errorRate.add(1);

    sleep(Math.random() * 3 + 1); // 1-4s entre requests (realista)
}
