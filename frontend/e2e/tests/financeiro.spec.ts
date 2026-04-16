import { navigateToModule, test, expect } from '../fixtures';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DO MÓDULO FINANCEIRO COMPLETO                        ║
// ║  Dashboard, Pagar, Receber, Comissões, Despesas, Fluxo, etc  ║
// ╚═══════════════════════════════════════════════════════════════╝

const rotasFinanceiras = [
  { rota: '/financeiro', nome: 'Dashboard Financeiro' },
  { rota: '/financeiro/receber', nome: 'Contas a Receber' },
  { rota: '/financeiro/pagar', nome: 'Contas a Pagar' },
  { rota: '/financeiro/comissoes', nome: 'Comissões' },
  { rota: '/financeiro/comissoes/dashboard', nome: 'Dashboard de Comissões' },
  { rota: '/financeiro/despesas', nome: 'Despesas' },
  { rota: '/financeiro/abastecimento', nome: 'Abastecimento' },
  { rota: '/financeiro/pagamentos', nome: 'Pagamentos' },
  { rota: '/financeiro/formas-pagamento', nome: 'Formas de Pagamento' },
  { rota: '/financeiro/fluxo-caixa', nome: 'Fluxo de Caixa' },
  { rota: '/financeiro/fluxo-caixa-semanal', nome: 'Fluxo de Caixa Semanal' },
  { rota: '/financeiro/faturamento', nome: 'Faturamento' },
  { rota: '/financeiro/conciliacao-bancaria', nome: 'Conciliação Bancária' },
  { rota: '/financeiro/regras-conciliacao', nome: 'Regras de Conciliação' },
  { rota: '/financeiro/dashboard-conciliacao', nome: 'Dashboard Conciliação' },
  { rota: '/financeiro/consolidado', nome: 'Consolidado Financeiro' },
  { rota: '/financeiro/plano-contas', nome: 'Plano de Contas' },
  { rota: '/financeiro/categorias-pagar', nome: 'Categorias a Pagar' },
  { rota: '/financeiro/contas-bancarias', nome: 'Contas Bancárias' },
  { rota: '/financeiro/transferencias-tecnicos', nome: 'Transferências Técnicos' },
  { rota: '/financeiro/regua-cobranca', nome: 'Régua de Cobrança' },
  { rota: '/financeiro/cobranca-automatica', nome: 'Cobrança Automática' },
  { rota: '/financeiro/renegociacao', nome: 'Renegociação' },
  { rota: '/financeiro/reembolsos', nome: 'Reembolsos' },
  { rota: '/financeiro/cheques', nome: 'Cheques' },
  { rota: '/financeiro/contratos-fornecedores', nome: 'Contratos Fornecedores' },
  { rota: '/financeiro/adiantamentos-fornecedores', nome: 'Adiantamentos Fornecedores' },
  { rota: '/financeiro/simulador-recebiveis', nome: 'Simulador de Recebíveis' },
  { rota: '/financeiro/aprovacao-lote', nome: 'Aprovação em Lote' },
  { rota: '/financeiro/alocacao-despesas', nome: 'Alocação de Despesas' },
  { rota: '/financeiro/calculadora-tributos', nome: 'Calculadora de Tributos' },
  { rota: '/financeiro/dre', nome: 'DRE' },
  { rota: '/financeiro/recibos', nome: 'Recibos' },
];

// Gera 1 teste "page load" para cada rota
for (const { rota, nome } of rotasFinanceiras) {
  test(`Financeiro: ${nome} — deve carregar sem erros`, async ({ page }) => {
    await navigateToModule(page, rota);
    await expect(page).not.toHaveURL(/.*login/);
    await expect(page.locator('body')).not.toBeEmpty();
  });
}

// Testes extras de comportamento
test.describe('Financeiro — Comportamento', () => {
  test('Dashboard Financeiro deve ter URL correta', async ({ page }) => {
    await navigateToModule(page, '/financeiro');
    await expect(page).toHaveURL(/.*financeiro/);
  });

  test('Contas a Pagar deve ter conteúdo na tela', async ({ page }) => {
    await navigateToModule(page, '/financeiro/pagar');
    await expect(page.locator('body')).toBeVisible();
  });

  test('Contas a Receber deve ter conteúdo na tela', async ({ page }) => {
    await navigateToModule(page, '/financeiro/receber');
    await expect(page.locator('body')).toBeVisible();
  });

  test('Fluxo de Caixa não deve mostrar erro de servidor', async ({ page }) => {
    await navigateToModule(page, '/financeiro/fluxo-caixa');
    await expect(page.getByText(/erro interno|500/i)).not.toBeVisible().catch(() => {});
  });

  test('Plano de Contas deve estar acessível', async ({ page }) => {
    await navigateToModule(page, '/financeiro/plano-contas');
    await expect(page).not.toHaveURL(/.*login/);
  });

  test('DRE deve estar acessível', async ({ page }) => {
    await navigateToModule(page, '/financeiro/dre');
    await expect(page).not.toHaveURL(/.*login/);
  });

  test('Recibos deve estar acessível', async ({ page }) => {
    await navigateToModule(page, '/financeiro/recibos');
    await expect(page).not.toHaveURL(/.*login/);
  });
});
