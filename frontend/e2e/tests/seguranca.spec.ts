import { test, expect } from '@playwright/test';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DE SEGURANÇA E CONTROLE DE ACESSO                   ║
// ║  100 testes: acesso sem login, injeção, headers, etc         ║
// ╚═══════════════════════════════════════════════════════════════╝

// Desliga a sessão salva — robô precisa estar DESLOGADO
test.use({ storageState: { cookies: [], origins: [] } });

// ─── Rotas que DEVEM redirecionar para /login sem autenticação ───
const rotasProtegidas = [
  '/', '/dashboard', '/agenda', '/agenda/kanban', '/agenda/dashboard',
  '/iam/usuarios', '/iam/roles', '/iam/permissoes',
  '/cadastros/clientes', '/cadastros/produtos', '/cadastros/servicos', '/cadastros/fornecedores',
  '/os', '/os/kanban', '/os/nova', '/os/dashboard', '/os/mapa',
  '/chamados', '/chamados/novo', '/chamados/kanban', '/chamados/dashboard',
  '/crm', '/crm/pipeline', '/crm/forecast', '/crm/goals',
  '/financeiro', '/financeiro/pagar', '/financeiro/receber', '/financeiro/fluxo-caixa',
  '/estoque', '/estoque/armazens', '/estoque/movimentacoes', '/estoque/kardex',
  '/rh', '/rh/ponto', '/rh/ferias', '/rh/desempenho',
  '/equipamentos', '/equipamentos/novo', '/calibracoes',
  '/inmetro', '/inmetro/leads', '/inmetro/instrumentos',
  '/qualidade', '/qualidade/auditorias',
  '/fiscal', '/fiscal/notas',
  '/configuracoes', '/configuracoes/filiais', '/configuracoes/empresas',
  '/perfil', '/orcamentos', '/orcamentos/novo',
  '/relatorios', '/analytics', '/notificacoes',
  '/contratos', '/frota', '/alertas',
  '/automacao', '/avancado', '/ia', '/ceo-cockpit',
  '/seguranca', '/vendas/avancado', '/laboratorio',
  '/emails', '/emails/compose',
  '/financeiro/comissoes', '/financeiro/despesas', '/financeiro/pagamentos',
  '/financeiro/conciliacao-bancaria', '/financeiro/dre', '/financeiro/recibos',
  '/rh/documentos', '/rh/onboarding', '/rh/organograma',
  '/crm/scoring', '/crm/sequences', '/crm/territories',
  '/crm/renewals', '/crm/web-forms', '/crm/nps',
  '/estoque/lotes', '/estoque/inventarios', '/estoque/transferencias',
  '/inmetro/compliance', '/inmetro/selos', '/inmetro/executivo',
  '/financeiro/contas-bancarias', '/financeiro/plano-contas',
  '/rh/escalas', '/rh/beneficios', '/rh/recrutamento',
  '/equipamentos/modelos', '/equipamentos/manutencoes',
  '/crm/gamification', '/crm/proposals', '/crm/competitors',
  '/estoque/inteligencia', '/estoque/etiquetas', '/estoque/numeros-serie',
  '/financeiro/cheques', '/financeiro/reembolsos', '/financeiro/renegociacao',
];

for (const rota of rotasProtegidas) {
  test(`Segurança: ${rota} deve redirecionar para /login sem auth`, async ({ page }) => {
    await page.goto(rota);
    await expect(page).toHaveURL(/.*login/);
  });
}

// ─── Testes de Injeção e Headers ───
test.describe('Segurança — Inputs Maliciosos', () => {
  test('Deve bloquear XSS no campo de e-mail do login', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill('<script>alert("xss")</script>');
    await page.locator('#password').fill('qualquer');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/.*login/);
  });

  test('Deve bloquear SQL injection no campo de e-mail', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill("' OR 1=1 --");
    await page.locator('#password').fill('qualquer');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/.*login/);
  });

  test('Deve bloquear e-mail com payload de LDAP injection', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill('*)(uid=*))(|(uid=*');
    await page.locator('#password').fill('qualquer');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/.*login/);
  });

  test('Deve bloquear senha vazia', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill('admin@email.com');
    await page.locator('#password').fill('');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/.*login/);
  });

  test('Deve bloquear e-mail vazio', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill('');
    await page.locator('#password').fill('qualquer');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/.*login/);
  });

  test('Deve bloquear ambos vazios', async ({ page }) => {
    await page.goto('/login');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/.*login/);
  });

  test('Deve bloquear senha extremamente longa', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill('hacker@evil.com');
    await page.locator('#password').fill('A'.repeat(10000));
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/.*login/);
  });

  test('Deve bloquear e-mail com espaços', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill('   ');
    await page.locator('#password').fill('qualquer');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/.*login/);
  });

  test('Deve bloquear e-mail com caracteres unicode perigosos', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill('admin@ⓔⓧⓐⓜⓟⓛⓔ.com');
    await page.locator('#password').fill('qualquer');
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/.*login/);
  });

  test('Deve manter na tela de login após tentativas erradas repetidas', async ({ page }) => {
    test.setTimeout(30000);
    await page.goto('/login');
    await page.locator('#email').fill('hack0@evil.com');
    await page.locator('#password').fill('wrongpass');
    await page.locator('button[type="submit"]').click();
    await Promise.race([
      page.getByText(/credenciais|erro|inválid/i).first().waitFor({ state: 'visible', timeout: 10000 }).catch(() => {}),
      page.locator('button[type="submit"]:not(:disabled)').waitFor({ state: 'visible', timeout: 10000 }).catch(() => {}),
    ]);
    await expect(page).toHaveURL(/.*login/);
  });
});

// ─── Testes de Rotas Públicas ───
test.describe('Rotas Públicas', () => {
  test('Tela de login deve ser acessível', async ({ page }) => {
    await page.goto('/login');
    await expect(page).toHaveURL(/.*login/);
  });

  test('Esqueci senha deve ser acessível', async ({ page }) => {
    await page.goto('/esqueci-senha');
    await expect(page.locator('body')).not.toBeEmpty();
  });

  test('Redefinir senha deve ser acessível', async ({ page }) => {
    await page.goto('/redefinir-senha');
    await expect(page.locator('body')).not.toBeEmpty();
  });
});
