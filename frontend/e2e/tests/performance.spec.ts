import { test, expect } from '../fixtures';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DE PERFORMANCE E CARREGAMENTO                        ║
// ║  Verifica tempo de carregamento das páginas principais        ║
// ╚═══════════════════════════════════════════════════════════════╝

const paginasCriticas = [
  { rota: '/', nome: 'Dashboard', maxMs: 10000 },
  { rota: '/cadastros/clientes', nome: 'Clientes', maxMs: 10000 },
  { rota: '/os', nome: 'Ordens de Serviço', maxMs: 10000 },
  { rota: '/crm', nome: 'CRM Dashboard', maxMs: 10000 },
  { rota: '/financeiro', nome: 'Financeiro Dashboard', maxMs: 10000 },
  { rota: '/estoque', nome: 'Estoque Dashboard', maxMs: 10000 },
  { rota: '/rh', nome: 'RH Dashboard', maxMs: 10000 },
  { rota: '/configuracoes', nome: 'Configurações', maxMs: 10000 },
  { rota: '/inmetro', nome: 'Inmetro Dashboard', maxMs: 10000 },
  { rota: '/perfil', nome: 'Perfil', maxMs: 10000 },
  { rota: '/os/kanban', nome: 'OS Kanban', maxMs: 10000 },
  { rota: '/crm/pipeline', nome: 'CRM Pipeline', maxMs: 10000 },
  { rota: '/financeiro/pagar', nome: 'Contas a Pagar', maxMs: 10000 },
  { rota: '/financeiro/receber', nome: 'Contas a Receber', maxMs: 10000 },
  { rota: '/ceo-cockpit', nome: 'CEO Cockpit', maxMs: 10000 },
];

test.describe('Performance — Tempo de Carregamento', () => {
  for (const { rota, nome, maxMs } of paginasCriticas) {
    test(`Performance: ${nome} deve carregar em menos de ${maxMs/1000}s`, async ({ page }) => {
      const start = Date.now();
      await page.goto(rota, { timeout: maxMs });
      const elapsed = Date.now() - start;
      expect(elapsed).toBeLessThan(maxMs);
    });
  }
});

// ─── Testes de erro de console ───
test.describe('Console — Sem Erros Críticos', () => {
  const paginas = [
    '/', '/cadastros/clientes', '/os', '/crm', '/financeiro',
    '/estoque', '/rh', '/equipamentos', '/configuracoes', '/perfil',
  ];

  for (const rota of paginas) {
    test(`Console: ${rota} não deve ter erros fatais de JS`, async ({ page }) => {
      const erros: string[] = [];
      page.on('pageerror', (err) => erros.push(err.message));

      await page.goto(rota);
      await page.waitForTimeout(500);

      // Filtra apenas erros que são realmente fatais
      const errosFatais = erros.filter(e =>
        !e.includes('ResizeObserver') &&
        !e.includes('Loading chunk') &&
        !e.includes('Network Error') &&
        !e.includes('timeout')
      );

      expect(errosFatais.length).toBeLessThanOrEqual(0);
    });
  }
});

// ─── Testes de HTTP 404/500 ───
test.describe('HTTP — Sem Erros de Rede Críticos', () => {
  const paginas = [
    '/', '/cadastros/clientes', '/os', '/crm', '/financeiro',
    '/estoque', '/rh', '/equipamentos', '/configuracoes',
    '/os/kanban', '/crm/pipeline', '/financeiro/pagar',
    '/financeiro/receber', '/inmetro',
  ];

  for (const rota of paginas) {
    test(`HTTP: ${rota} não deve retornar 500 em recursos estáticos`, async ({ page }) => {
      const erros500: string[] = [];
      page.on('response', (response) => {
        if (response.status() === 500 && response.url().includes('/assets/')) {
          erros500.push(response.url());
        }
      });

      await page.goto(rota);
      await page.waitForTimeout(500);

      expect(erros500.length).toBe(0);
    });
  }
});
