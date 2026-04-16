/**
 * MVP API TEST — Testa endpoints reais do KALIBRIUM ERP
 * Faz login, obtém token e testa cada endpoint GET principal
 *
 * Uso: node mvp_api_test.cjs
 */

const http = require('http');

const BASE = 'http://localhost:8000';
const LOGIN_CREDS = { email: 'admin@example.test', password: process.env.E2E_PASSWORD ?? 'CHANGE_ME_E2E_PASSWORD' };

function httpRequest(method, urlPath, token = null, body = null) {
    return new Promise((resolve) => {
        const url = new URL(urlPath, BASE);
        const isJson = body && typeof body === 'object';
        const bodyStr = isJson ? JSON.stringify(body) : body;

        const headers = { 'Accept': 'application/json' };
        if (token) headers['Authorization'] = `Bearer ${token}`;
        if (isJson) {
            headers['Content-Type'] = 'application/json';
            headers['Content-Length'] = Buffer.byteLength(bodyStr);
        }

        const req = http.request({
            hostname: url.hostname,
            port: url.port,
            path: url.pathname + url.search,
            method,
            headers,
            timeout: 10000,
        }, (res) => {
            let data = '';
            res.on('data', c => data += c);
            res.on('end', () => {
                let json = null;
                // Remover BOM (Byte Order Mark) que o Laravel pode adicionar
                const cleanData = data.replace(/^\uFEFF/, '');
                try { json = JSON.parse(cleanData); } catch { }
                resolve({ status: res.statusCode, json, raw: cleanData.substring(0, 300) });
            });
        });

        req.on('error', (e) => resolve({ status: 0, error: e.message }));
        req.on('timeout', () => { req.destroy(); resolve({ status: 0, error: 'TIMEOUT' }); });

        if (bodyStr) req.write(bodyStr);
        req.end();
    });
}

// Endpoints a testar (extraídos das rotas reais do api.php)
const ENDPOINTS = [
    // Core
    { module: 'Dashboard', method: 'GET', path: '/api/v1/dashboard-stats' },
    { module: 'Auth - Me', method: 'GET', path: '/api/v1/me' },

    // IAM
    { module: 'Usuários', method: 'GET', path: '/api/v1/users' },
    { module: 'Roles', method: 'GET', path: '/api/v1/roles' },
    { module: 'Permissões', method: 'GET', path: '/api/v1/permissions' },
    { module: 'Audit Log', method: 'GET', path: '/api/v1/audit-logs' },

    // Cadastros
    { module: 'Clientes', method: 'GET', path: '/api/v1/customers' },
    { module: 'Produtos', method: 'GET', path: '/api/v1/products' },
    { module: 'Serviços', method: 'GET', path: '/api/v1/services' },
    { module: 'Fornecedores', method: 'GET', path: '/api/v1/suppliers' },

    // Orçamentos
    { module: 'Orçamentos', method: 'GET', path: '/api/v1/quotes' },

    // Chamados
    { module: 'Chamados', method: 'GET', path: '/api/v1/service-calls' },

    // OS
    { module: 'Ordens de Serviço', method: 'GET', path: '/api/v1/work-orders' },
    { module: 'OS Metadata', method: 'GET', path: '/api/v1/work-orders-metadata' },
    { module: 'Contratos Recorrentes', method: 'GET', path: '/api/v1/recurring-contracts' },

    // Técnicos
    { module: 'Agenda', method: 'GET', path: '/api/v1/schedules' },
    { module: 'Apontamentos', method: 'GET', path: '/api/v1/time-entries' },

    // Financeiro
    { module: 'Contas Receber', method: 'GET', path: '/api/v1/accounts-receivable' },
    { module: 'Contas Pagar', method: 'GET', path: '/api/v1/accounts-payable' },
    { module: 'Pagamentos', method: 'GET', path: '/api/v1/payments' },
    { module: 'Formas Pagamento', method: 'GET', path: '/api/v1/payment-methods' },
    { module: 'Caixa', method: 'GET', path: '/api/v1/cash-flow' },
    { module: 'Faturamento', method: 'GET', path: '/api/v1/invoices' },
    { module: 'Conciliação', method: 'GET', path: '/api/v1/bank-reconciliation/summary' },
    { module: 'Plano Contas', method: 'GET', path: '/api/v1/chart-of-accounts' },

    // ▶ MÓDULOS CRÍTICOS — Comissões (cobertura completa)
    { module: '⭐ Comissões Regras', method: 'GET', path: '/api/v1/commission-rules' },
    { module: '⭐ Comissões Eventos', method: 'GET', path: '/api/v1/commission-events' },
    { module: '⭐ Comissões Dashboard', method: 'GET', path: '/api/v1/commission-dashboard' },
    { module: '⭐ Comissões Fechamentos', method: 'GET', path: '/api/v1/commission-settlements' },
    { module: '⭐ Comissões Disputas', method: 'GET', path: '/api/v1/commission-disputes' },
    { module: '⭐ Comissões Metas', method: 'GET', path: '/api/v1/commission-goals' },
    { module: '⭐ Comissões Campanhas', method: 'GET', path: '/api/v1/commission-campaigns' },

    // ▶ MÓDULOS CRÍTICOS — Despesas (cobertura completa)
    { module: '⭐ Despesas Lista', method: 'GET', path: '/api/v1/expenses' },
    { module: '⭐ Despesas Categorias', method: 'GET', path: '/api/v1/expense-categories' },
    { module: '⭐ Abastecimento', method: 'GET', path: '/api/v1/fueling-logs' },

    // ▶ MÓDULOS CRÍTICOS — Caixa do Técnico (cobertura completa)
    { module: '⭐ Caixa Técnico', method: 'GET', path: '/api/v1/technician-cash' },
    { module: '⭐ Transferências', method: 'GET', path: '/api/v1/fund-transfers' },
    { module: '⭐ Adiantamentos', method: 'GET', path: '/api/v1/technician-advances' },

    // Estoque
    { module: 'Estoque Resumo', method: 'GET', path: '/api/v1/stock/summary' },
    { module: 'Movimentações', method: 'GET', path: '/api/v1/stock/movements' },
    { module: 'Armazéns', method: 'GET', path: '/api/v1/warehouses' },
    { module: 'Inventários', method: 'GET', path: '/api/v1/inventories' },
    { module: 'Intel. Estoque', method: 'GET', path: '/api/v1/stock/intelligence/abc-curve' },

    // Equipamentos
    { module: 'Equipamentos', method: 'GET', path: '/api/v1/equipments' },
    { module: 'Pesos Padrão', method: 'GET', path: '/api/v1/standard-weights' },

    // INMETRO
    { module: 'INMETRO Dashboard', method: 'GET', path: '/api/v1/inmetro/dashboard' },
    { module: 'INMETRO Leads', method: 'GET', path: '/api/v1/inmetro/leads' },
    { module: 'INMETRO Instrumentos', method: 'GET', path: '/api/v1/inmetro/instruments' },

    // Fiscal
    { module: 'Notas Fiscais', method: 'GET', path: '/api/v1/fiscal/notas' },

    // CRM
    { module: 'CRM Dashboard', method: 'GET', path: '/api/v1/crm/dashboard' },

    // Email
    { module: 'Email Contas', method: 'GET', path: '/api/v1/email/accounts' },

    // Importação
    { module: 'Import Histórico', method: 'GET', path: '/api/v1/import/history' },

    // Relatórios
    { module: 'Relatório OS', method: 'GET', path: '/api/v1/reports/work-orders' },
    { module: 'Relatório Financeiro', method: 'GET', path: '/api/v1/reports/financial' },

    // Notificações
    { module: 'Notificações', method: 'GET', path: '/api/v1/notifications' },

    // Checklists
    { module: 'Checklists', method: 'GET', path: '/api/v1/checklists' },

    // SLA
    { module: 'SLA Policies', method: 'GET', path: '/api/v1/sla-policies' },

    // Frota
    { module: 'Frota Veículos', method: 'GET', path: '/api/v1/fleet/vehicles' },
    { module: 'Frota Dashboard', method: 'GET', path: '/api/v1/fleet/dashboard' },

    // RH
    { module: 'RH Funcionários', method: 'GET', path: '/api/v1/hr/employees' },
    { module: 'RH Ponto', method: 'GET', path: '/api/v1/hr/clock-entries' },

    // Qualidade
    { module: 'Qualidade', method: 'GET', path: '/api/v1/quality/procedures' },

    // Automação
    { module: 'Automação', method: 'GET', path: '/api/v1/automation/rules' },

    // IA
    { module: 'IA Predições', method: 'GET', path: '/api/v1/ai/predictions' },
];

async function main() {
    console.log('╔══════════════════════════════════════════════════════════╗');
    console.log('║   KALIBRIUM ERP — Teste de API Real (HTTP Requests)    ║');
    console.log('╚══════════════════════════════════════════════════════════╝');
    console.log('');

    // 1. Login
    console.log('🔐 Tentando login...');
    const loginRes = await httpRequest('POST', '/api/v1/login', null, LOGIN_CREDS);

    if (loginRes.status === 0) {
        console.log(`❌ Backend OFFLINE: ${loginRes.error}`);
        console.log('   Certifique-se de que o backend está rodando: php artisan serve');
        process.exit(1);
    }

    let token = null;
    if (loginRes.json && loginRes.json.token) {
        token = loginRes.json.token;
        console.log(`✅ Login OK — Token obtido`);
    } else if (loginRes.json && loginRes.json.access_token) {
        token = loginRes.json.access_token;
        console.log(`✅ Login OK — Token obtido`);
    } else {
        console.log(`❌ Login falhou — Status: ${loginRes.status}`);
        console.log(`   Resposta: ${loginRes.raw}`);

        // Tentar com credenciais alternativas
        console.log('🔐 Tentando credenciais alternativas...');
        const altCreds = [
            { email: 'superadmin@kalibrium.com', password: process.env.E2E_PASSWORD ?? 'CHANGE_ME_E2E_PASSWORD' },
            { email: 'admin@admin.com', password: process.env.E2E_PASSWORD ?? 'CHANGE_ME_E2E_PASSWORD' },
            { email: 'admin@empresa.com', password: process.env.E2E_PASSWORD ?? 'CHANGE_ME_E2E_PASSWORD' },
            { email: 'roldao@kalibrium.com', password: process.env.E2E_PASSWORD ?? 'CHANGE_ME_E2E_PASSWORD' },
        ];

        for (const cred of altCreds) {
            const r = await httpRequest('POST', '/api/v1/login', null, cred);
            if (r.json && (r.json.token || r.json.access_token)) {
                token = r.json.token || r.json.access_token;
                console.log(`✅ Login OK com: ${cred.email}`);
                break;
            }
        }

        if (!token) {
            console.log('❌ Nenhuma credencial funcionou. Continuando sem autenticação...');
        }
    }

    console.log('');

    // 2. Testar endpoints
    console.log('🧪 Testando endpoints...\n');

    const results = [];

    for (const ep of ENDPOINTS) {
        const res = await httpRequest(ep.method, ep.path, token);

        let statusIcon;
        let statusText;

        if (res.status === 200) { statusIcon = '✅'; statusText = '200 OK'; }
        else if (res.status === 201) { statusIcon = '✅'; statusText = '201 Created'; }
        else if (res.status === 401) { statusIcon = '🔒'; statusText = '401 Unauthorized'; }
        else if (res.status === 403) { statusIcon = '🚫'; statusText = '403 Forbidden'; }
        else if (res.status === 404) { statusIcon = '❌'; statusText = '404 Not Found'; }
        else if (res.status === 500) { statusIcon = '💥'; statusText = '500 Server Error'; }
        else if (res.status === 302) { statusIcon = '↪️'; statusText = '302 Redirect'; }
        else if (res.status === 0) { statusIcon = '⚠️'; statusText = `ERR: ${res.error}`; }
        else { statusIcon = '🟡'; statusText = `${res.status}`; }

        const dataInfo = res.json
            ? (Array.isArray(res.json.data) ? `${res.json.data.length} registros` :
                res.json.data ? 'tem data' :
                    res.json.message || 'JSON ok')
            : (res.raw ? res.raw.substring(0, 50) : 'sem corpo');

        console.log(`${statusIcon} ${ep.module.padEnd(25)} ${statusText.padEnd(20)} ${dataInfo}`);

        results.push({
            module: ep.module,
            method: ep.method,
            path: ep.path,
            status: res.status,
            statusText,
            statusIcon,
            hasData: res.json && (res.json.data || Array.isArray(res.json)),
            dataInfo,
        });
    }

    // 3. Resumo
    console.log('');
    const ok = results.filter(r => r.status === 200 || r.status === 201).length;
    const auth = results.filter(r => r.status === 401 || r.status === 403).length;
    const notFound = results.filter(r => r.status === 404).length;
    const serverErr = results.filter(r => r.status === 500).length;
    const other = results.filter(r => ![200, 201, 401, 403, 404, 500].includes(r.status)).length;

    console.log('┌──────────────────────────────────────────────┐');
    console.log(`│ ✅ OK (200/201):      ${String(ok).padStart(3)} endpoints         │`);
    console.log(`│ 🔒 Auth (401/403):    ${String(auth).padStart(3)} endpoints         │`);
    console.log(`│ ❌ Not Found (404):   ${String(notFound).padStart(3)} endpoints         │`);
    console.log(`│ 💥 Server Error (500):${String(serverErr).padStart(3)} endpoints         │`);
    console.log(`│ 🟡 Outros:            ${String(other).padStart(3)} endpoints         │`);
    console.log(`│ TOTAL:                ${String(results.length).padStart(3)} endpoints         │`);
    console.log('└──────────────────────────────────────────────┘');

    // 4. Salvar relatório
    const lines = [];
    lines.push('# KALIBRIUM ERP — Teste de API Real');
    lines.push(`> Gerado em: ${new Date().toISOString().replace('T', ' ').slice(0, 19)}`);
    lines.push(`> Login: ${token ? '✅ Autenticado' : '❌ Sem token'}`);
    lines.push('');
    lines.push('## Resumo');
    lines.push('');
    lines.push(`| Status | Qtd |`);
    lines.push(`|--------|-----|`);
    lines.push(`| ✅ OK (200/201) | ${ok} |`);
    lines.push(`| 🔒 Auth (401/403) | ${auth} |`);
    lines.push(`| ❌ Not Found (404) | ${notFound} |`);
    lines.push(`| 💥 Server Error (500) | ${serverErr} |`);
    lines.push(`| 🟡 Outros | ${other} |`);
    lines.push('');
    lines.push('## Detalhes');
    lines.push('');
    lines.push('| Módulo | Método | Path | Status | Dados |');
    lines.push('|--------|--------|------|--------|-------|');
    for (const r of results) {
        lines.push(`| ${r.module} | ${r.method} | \`${r.path}\` | ${r.statusIcon} ${r.statusText} | ${r.dataInfo.substring(0, 40)} |`);
    }

    // Listar problemáticos
    const problematic = results.filter(r => r.status !== 200 && r.status !== 201);
    if (problematic.length > 0) {
        lines.push('');
        lines.push('## Endpoints com Problemas');
        lines.push('');
        for (const r of problematic) {
            lines.push(`- ${r.statusIcon} **${r.module}** — \`${r.method} ${r.path}\` → ${r.statusText} — ${r.dataInfo}`);
        }
    }

    const fs = require('fs');
    const reportFile = require('path').join(__dirname, 'mvp_api_results.md');
    fs.writeFileSync(reportFile, lines.join('\n'), 'utf-8');
    console.log(`\n✅ Relatório salvo em: ${reportFile}`);
}

main().catch(console.error);
