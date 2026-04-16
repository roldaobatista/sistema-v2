import { test, expect } from '../fixtures';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DE CONFIGURAÇÕES, ORÇAMENTOS, E-MAILS E EXTRAS      ║
// ╚═══════════════════════════════════════════════════════════════╝

const rotasConfiguracoes = [
  { rota: '/configuracoes', nome: 'Configurações Gerais' },
  { rota: '/configuracoes/cadastros-auxiliares', nome: 'Cadastros Auxiliares' },
  { rota: '/configuracoes/filiais', nome: 'Filiais' },
  { rota: '/configuracoes/empresas', nome: 'Gestão de Empresas' },
  { rota: '/configuracoes/auditoria', nome: 'Auditoria' },
  { rota: '/configuracoes/logs-auditoria', nome: 'Logs de Auditoria' },
  { rota: '/configuracoes/whatsapp', nome: 'WhatsApp Config' },
  { rota: '/configuracoes/whatsapp/logs', nome: 'WhatsApp Logs' },
  { rota: '/configuracoes/google-calendar', nome: 'Google Calendar' },
  { rota: '/configuracoes/limites-despesas', nome: 'Limites de Despesas' },
];

for (const { rota, nome } of rotasConfiguracoes) {
  test(`Configurações: ${nome} — deve carregar`, async ({ page }) => {
    await page.goto(rota);
    await expect(page).not.toHaveURL(/.*login/);
    await expect(page.locator('body')).not.toBeEmpty();
  });
}

const rotasOrcamentos = [
  { rota: '/orcamentos', nome: 'Lista de Orçamentos' },
  { rota: '/orcamentos/dashboard', nome: 'Dashboard Orçamentos' },
  { rota: '/orcamentos/novo', nome: 'Novo Orçamento' },
];

for (const { rota, nome } of rotasOrcamentos) {
  test(`Orçamentos: ${nome} — deve carregar`, async ({ page }) => {
    await page.goto(rota);
    await expect(page).not.toHaveURL(/.*login/);
    await expect(page.locator('body')).not.toBeEmpty();
  });
}

const rotasEmails = [
  { rota: '/emails', nome: 'Caixa de E-mails' },
  { rota: '/emails/compose', nome: 'Compor E-mail' },
  { rota: '/emails/configuracoes', nome: 'Config E-mails' },
];

for (const { rota, nome } of rotasEmails) {
  test(`E-mails: ${nome} — deve carregar`, async ({ page }) => {
    await page.goto(rota);
    await expect(page).not.toHaveURL(/.*login/);
    await expect(page.locator('body')).not.toBeEmpty();
  });
}

const rotasExtras = [
  { rota: '/relatorios', nome: 'Relatórios' },
  { rota: '/analytics', nome: 'Analytics Hub' },
  { rota: '/notificacoes', nome: 'Notificações' },
  { rota: '/importacao', nome: 'Importação' },
  { rota: '/integracao/auvo', nome: 'Integração Auvo' },
  { rota: '/contratos', nome: 'Contratos' },
  { rota: '/frota', nome: 'Frota' },
  { rota: '/alertas', nome: 'Alertas' },
  { rota: '/automacao', nome: 'Automação' },
  { rota: '/avancado', nome: 'Funcionalidades Avançadas' },
  { rota: '/ia', nome: 'IA Analytics' },
  { rota: '/ceo-cockpit', nome: 'CEO Cockpit' },
  { rota: '/tv/dashboard', nome: 'TV Dashboard' },
  { rota: '/tv/cameras', nome: 'TV Câmeras' },
  { rota: '/laboratorio', nome: 'Laboratório Avançado' },
  { rota: '/seguranca', nome: 'Segurança' },
  { rota: '/vendas/avancado', nome: 'Vendas Avançado' },
];

for (const { rota, nome } of rotasExtras) {
  test(`Extras: ${nome} — deve carregar`, async ({ page }) => {
    await page.goto(rota);
    await expect(page).not.toHaveURL(/.*login/);
    await expect(page.locator('body')).not.toBeEmpty();
  });
}

test.describe('Configurações — Comportamento', () => {
  test('Configurações gerais deve ter URL correta', async ({ page }) => {
    await page.goto('/configuracoes');
    await expect(page).toHaveURL(/.*configuracoes/);
  });

  test('Filiais deve exibir conteúdo', async ({ page }) => {
    await page.goto('/configuracoes/filiais');
    await expect(page.locator('body')).toBeVisible();
  });

  test('CEO Cockpit deve estar acessível', async ({ page }) => {
    await page.goto('/ceo-cockpit');
    await expect(page).not.toHaveURL(/.*login/);
  });

  test('Relatórios deve estar acessível', async ({ page }) => {
    await page.goto('/relatorios');
    await expect(page).not.toHaveURL(/.*login/);
  });
});
