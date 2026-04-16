import { navigateToModule, test, expect } from '../fixtures';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DO MÓDULO CRM COMPLETO                               ║
// ║  Pipeline, Forecast, Goals, Calendar, Field Management, etc  ║
// ╚═══════════════════════════════════════════════════════════════╝

const rotasCrm = [
  { rota: '/crm', nome: 'Dashboard CRM' },
  { rota: '/crm/pipeline', nome: 'Pipeline' },
  { rota: '/crm/templates', nome: 'Templates de Mensagem' },
  { rota: '/crm/forecast', nome: 'Forecast' },
  { rota: '/crm/goals', nome: 'Metas' },
  { rota: '/crm/alerts', nome: 'Alertas' },
  { rota: '/crm/calendar', nome: 'Calendário' },
  { rota: '/crm/scoring', nome: 'Scoring' },
  { rota: '/crm/sequences', nome: 'Sequências' },
  { rota: '/crm/loss-analytics', nome: 'Análise de Perdas' },
  { rota: '/crm/territories', nome: 'Territórios' },
  { rota: '/crm/renewals', nome: 'Renovações' },
  { rota: '/crm/referrals', nome: 'Indicações' },
  { rota: '/crm/web-forms', nome: 'Formulários Web' },
  { rota: '/crm/revenue', nome: 'Inteligência de Receita' },
  { rota: '/crm/competitors', nome: 'Concorrentes' },
  { rota: '/crm/velocity', nome: 'Velocidade' },
  { rota: '/crm/cohort', nome: 'Coorte' },
  { rota: '/crm/proposals', nome: 'Propostas' },
  { rota: '/crm/visit-checkins', nome: 'Check-ins de Visita' },
  { rota: '/crm/visit-routes', nome: 'Rotas de Visita' },
  { rota: '/crm/visit-reports', nome: 'Relatórios de Visita' },
  { rota: '/crm/portfolio-map', nome: 'Mapa de Carteira' },
  { rota: '/crm/forgotten-clients', nome: 'Clientes Esquecidos' },
  { rota: '/crm/contact-policies', nome: 'Políticas de Contato' },
  { rota: '/crm/smart-agenda', nome: 'Agenda Inteligente' },
  { rota: '/crm/post-visit-workflow', nome: 'Pós-Visita' },
  { rota: '/crm/quick-notes', nome: 'Notas Rápidas' },
  { rota: '/crm/commitments', nome: 'Compromissos' },
  { rota: '/crm/negotiation-history', nome: 'Histórico Negociação' },
  { rota: '/crm/client-summary', nome: 'Resumo do Cliente' },
  { rota: '/crm/rfm', nome: 'Análise RFM' },
  { rota: '/crm/coverage', nome: 'Cobertura' },
  { rota: '/crm/productivity', nome: 'Produtividade' },
  { rota: '/crm/opportunities', nome: 'Oportunidades' },
  { rota: '/crm/important-dates', nome: 'Datas Importantes' },
  { rota: '/crm/visit-surveys', nome: 'Pesquisas de Visita' },
  { rota: '/crm/account-plans', nome: 'Planos de Conta' },
  { rota: '/crm/gamification', nome: 'Gamificação' },
  { rota: '/crm/nps', nome: 'NPS Dashboard' },
];

// Gera 1 teste de carregamento para cada rota CRM
for (const { rota, nome } of rotasCrm) {
  test(`CRM: ${nome} — deve carregar`, async ({ page }) => {
    await navigateToModule(page, rota);
    await expect(page).not.toHaveURL(/.*login/);
    await expect(page.locator('body')).not.toBeEmpty();
  });
}

// Testes extras de comportamento CRM
test.describe('CRM — Comportamento', () => {
  test('Dashboard CRM deve ter URL correta', async ({ page }) => {
    await navigateToModule(page, '/crm');
    await expect(page).toHaveURL(/.*crm/);
  });

  test('Pipeline deve estar acessível', async ({ page }) => {
    await navigateToModule(page, '/crm/pipeline');
    await expect(page.locator('body')).toBeVisible();
  });

  test('Forecast deve exibir conteúdo', async ({ page }) => {
    await navigateToModule(page, '/crm/forecast');
    await expect(page.locator('body')).toBeVisible();
  });

  test('NPS deve estar acessível', async ({ page }) => {
    await navigateToModule(page, '/crm/nps');
    await expect(page).not.toHaveURL(/.*login/);
  });

  test('Gamificação deve estar acessível', async ({ page }) => {
    await navigateToModule(page, '/crm/gamification');
    await expect(page).not.toHaveURL(/.*login/);
  });
});
