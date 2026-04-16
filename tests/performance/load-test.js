import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend } from 'k6/metrics';

// ============================================================
// KALIBRIUM ERP — k6 Load Test: Endpoints Críticos
// ============================================================

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000/api/v1';

// Métricas customizadas
const errorRate = new Rate('errors');
const loginDuration = new Trend('login_duration');
const listDuration = new Trend('list_duration');
const createDuration = new Trend('create_duration');

// ============================================================
// Cenários de carga
// ============================================================
export const options = {
    scenarios: {
        // Teste de carga: uso normal
        load_test: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '1m', target: 50 },   // Ramp up
                { duration: '3m', target: 50 },   // Sustenta
                { duration: '1m', target: 100 },  // Pico
                { duration: '2m', target: 100 },  // Sustenta pico
                { duration: '1m', target: 0 },    // Ramp down
            ],
            exec: 'loadTest',
        },
    },

    // Thresholds (falha se ultrapassar)
    thresholds: {
        http_req_duration: ['p(95)<500', 'p(99)<2000'],
        errors: ['rate<0.01'],             // < 1% erro
        login_duration: ['p(95)<1000'],    // Login < 1s no p95
        list_duration: ['p(95)<500'],      // Listagens < 500ms
        create_duration: ['p(95)<1000'],   // Criações < 1s
    },
};

// ============================================================
// Helpers
// ============================================================
function login() {
    const res = http.post(`${BASE_URL}/login`, JSON.stringify({
        email: __ENV.TEST_EMAIL || 'admin@kalibrium.com',
        password: __ENV.TEST_PASSWORD || 'password',
    }), {
        headers: { 'Content-Type': 'application/json' },
    });

    loginDuration.add(res.timings.duration);

    check(res, {
        'login: status 200': (r) => r.status === 200,
        'login: token presente': (r) => r.json('data.token') !== undefined,
    }) || errorRate.add(1);

    return res.json('data.token');
}

function authHeaders(token) {
    return {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
        'X-Company-Id': __ENV.COMPANY_ID || '1',
    };
}

// ============================================================
// Cenário: Load Test
// ============================================================
export function loadTest() {
    const token = login();
    const headers = authHeaders(token);

    group('Listagens (GET)', () => {
        const endpoints = [
            '/work-orders',
            '/customers',
            '/quotes',
            '/products',
        ];

        endpoints.forEach((endpoint) => {
            const res = http.get(`${BASE_URL}${endpoint}?page=1&per_page=15`, { headers });
            listDuration.add(res.timings.duration);
            check(res, {
                [`${endpoint}: status 200`]: (r) => r.status === 200,
            }) || errorRate.add(1);
        });
    });

    sleep(1);

    group('Criação (POST)', () => {
        const res = http.post(`${BASE_URL}/service-calls`, JSON.stringify({
            customer_id: 1,
            description: `k6 load test - ${Date.now()}`,
            priority: 'medium',
        }), { headers });

        createDuration.add(res.timings.duration);
        check(res, {
            'create service-call: status 201': (r) => r.status === 201,
        }) || errorRate.add(1);
    });

    sleep(1);
}

// ============================================================
// Cenário standalone: Stress Test
// ============================================================
export function stressTest() {
    const token = login();
    const headers = authHeaders(token);

    // Endpoint mais pesado: relatório financeiro
    const res = http.get(`${BASE_URL}/reports/financial?start_date=2026-01-01&end_date=2026-03-06`, { headers });
    check(res, {
        'financial report: status 200': (r) => r.status === 200,
        'financial report: < 5s': (r) => r.timings.duration < 5000,
    }) || errorRate.add(1);
}
