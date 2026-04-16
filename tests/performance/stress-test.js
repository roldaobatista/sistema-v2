import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

// ============================================================
// KALIBRIUM ERP — k6 Stress Test
// Objetivo: encontrar o ponto de ruptura do sistema
// ============================================================

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000/api/v1';
const errorRate = new Rate('errors');

export const options = {
    scenarios: {
        stress: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '2m', target: 100 },   // Ramp normal
                { duration: '5m', target: 100 },   // Sustenta
                { duration: '2m', target: 200 },   // Começa stress
                { duration: '5m', target: 200 },   // Sustenta stress
                { duration: '2m', target: 300 },   // Alto stress
                { duration: '5m', target: 300 },   // Sustenta
                { duration: '2m', target: 500 },   // Pré-ruptura
                { duration: '5m', target: 500 },   // Sustenta pré-ruptura
                { duration: '5m', target: 0 },     // Recovery
            ],
        },
    },

    thresholds: {
        http_req_duration: ['p(99)<3000'],
        errors: ['rate<0.05'],  // < 5% erro aceitável em stress
    },
};

export default function () {
    // Login
    const loginRes = http.post(`${BASE_URL}/login`, JSON.stringify({
        email: __ENV.TEST_EMAIL || 'admin@kalibrium.com',
        password: __ENV.TEST_PASSWORD || 'password',
    }), {
        headers: { 'Content-Type': 'application/json' },
    });

    if (loginRes.status !== 200) {
        errorRate.add(1);
        return;
    }

    const token = loginRes.json('data.token');
    const headers = {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
        'X-Company-Id': __ENV.COMPANY_ID || '1',
    };

    // Operação pesada: listagem com filtros
    const res = http.get(`${BASE_URL}/work-orders?page=1&per_page=50&status=open`, { headers });
    check(res, {
        'work-orders: status ok': (r) => r.status === 200 || r.status === 429,
    }) || errorRate.add(1);

    sleep(0.5);
}
