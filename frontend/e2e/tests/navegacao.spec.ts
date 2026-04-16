import { test, expect } from '../fixtures';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DE NAVEGAÇÃO ENTRE MÓDULOS                           ║
// ║  Verifica que navegar entre páginas não quebra o SPA          ║
// ╚═══════════════════════════════════════════════════════════════╝

const fluxosNavegacao = [
  { de: '/', para: '/cadastros/clientes', nome: 'Dashboard → Clientes' },
  { de: '/cadastros/clientes', para: '/os', nome: 'Clientes → OS' },
  { de: '/os', para: '/crm', nome: 'OS → CRM' },
  { de: '/crm', para: '/financeiro', nome: 'CRM → Financeiro' },
  { de: '/financeiro', para: '/estoque', nome: 'Financeiro → Estoque' },
  { de: '/estoque', para: '/rh', nome: 'Estoque → RH' },
  { de: '/rh', para: '/equipamentos', nome: 'RH → Equipamentos' },
  { de: '/equipamentos', para: '/inmetro', nome: 'Equipamentos → Inmetro' },
  { de: '/inmetro', para: '/qualidade', nome: 'Inmetro → Qualidade' },
  { de: '/qualidade', para: '/fiscal', nome: 'Qualidade → Fiscal' },
  { de: '/fiscal', para: '/configuracoes', nome: 'Fiscal → Configurações' },
  { de: '/configuracoes', para: '/', nome: 'Configurações → Dashboard' },
  { de: '/', para: '/os/nova', nome: 'Dashboard → Nova OS' },
  { de: '/os/nova', para: '/os/kanban', nome: 'Nova OS → Kanban OS' },
  { de: '/os/kanban', para: '/chamados', nome: 'Kanban OS → Chamados' },
  { de: '/chamados', para: '/chamados/novo', nome: 'Chamados → Novo Chamado' },
  { de: '/chamados/novo', para: '/orcamentos', nome: 'Novo Chamado → Orçamentos' },
  { de: '/orcamentos', para: '/orcamentos/novo', nome: 'Orçamentos → Novo Orçamento' },
  { de: '/financeiro/pagar', para: '/financeiro/receber', nome: 'Pagar → Receber' },
  { de: '/financeiro/receber', para: '/financeiro/fluxo-caixa', nome: 'Receber → Fluxo Caixa' },
  { de: '/financeiro/fluxo-caixa', para: '/financeiro/dre', nome: 'Fluxo Caixa → DRE' },
  { de: '/crm/pipeline', para: '/crm/forecast', nome: 'Pipeline → Forecast' },
  { de: '/crm/forecast', para: '/crm/goals', nome: 'Forecast → Goals' },
  { de: '/crm/goals', para: '/crm/nps', nome: 'Goals → NPS' },
  { de: '/estoque/armazens', para: '/estoque/movimentacoes', nome: 'Armazéns → Movimentações' },
  { de: '/estoque/movimentacoes', para: '/estoque/kardex', nome: 'Movimentações → Kardex' },
  { de: '/rh/ponto', para: '/rh/ferias', nome: 'Ponto → Férias' },
  { de: '/rh/ferias', para: '/rh/desempenho', nome: 'Férias → Desempenho' },
  { de: '/rh/desempenho', para: '/rh/recrutamento', nome: 'Desempenho → Recrutamento' },
  { de: '/inmetro/leads', para: '/inmetro/instrumentos', nome: 'Leads → Instrumentos' },
  { de: '/inmetro/instrumentos', para: '/inmetro/compliance', nome: 'Instrumentos → Compliance' },
  { de: '/inmetro/compliance', para: '/inmetro/selos', nome: 'Compliance → Selos' },
  { de: '/financeiro/comissoes', para: '/financeiro/despesas', nome: 'Comissões → Despesas' },
  { de: '/financeiro/despesas', para: '/financeiro/pagamentos', nome: 'Despesas → Pagamentos' },
  { de: '/financeiro/pagamentos', para: '/financeiro/conciliacao-bancaria', nome: 'Pagamentos → Conciliação' },
  { de: '/configuracoes/filiais', para: '/configuracoes/empresas', nome: 'Filiais → Empresas' },
  { de: '/cadastros/produtos', para: '/cadastros/servicos', nome: 'Produtos → Serviços' },
  { de: '/cadastros/servicos', para: '/cadastros/fornecedores', nome: 'Serviços → Fornecedores' },
  { de: '/os/dashboard', para: '/os/sla', nome: 'OS Dashboard → SLA' },
  { de: '/os/sla', para: '/os/checklists', nome: 'SLA → Checklists' },
];

for (const { de, para, nome } of fluxosNavegacao) {
  test(`Navegação: ${nome}`, async ({ page }) => {
    await page.goto(de);
    await page.goto(para);
    await expect(page).not.toHaveURL(/.*login/);
    await expect(page.locator('body')).not.toBeEmpty();
  });
}

// ─── Testes de botão voltar do navegador ───
test.describe('Navegação — Botão Voltar', () => {
  const paginasVoltar = [
    { rota1: '/cadastros/clientes', rota2: '/os', nome: 'Clientes ↔ OS' },
    { rota1: '/crm', rota2: '/financeiro', nome: 'CRM ↔ Financeiro' },
    { rota1: '/estoque', rota2: '/rh', nome: 'Estoque ↔ RH' },
    { rota1: '/inmetro', rota2: '/qualidade', nome: 'Inmetro ↔ Qualidade' },
    { rota1: '/configuracoes', rota2: '/perfil', nome: 'Config ↔ Perfil' },
    { rota1: '/financeiro/pagar', rota2: '/financeiro/receber', nome: 'Pagar ↔ Receber' },
    { rota1: '/os', rota2: '/os/kanban', nome: 'OS Lista ↔ Kanban' },
    { rota1: '/crm/pipeline', rota2: '/crm/forecast', nome: 'Pipeline ↔ Forecast' },
    { rota1: '/rh/ponto', rota2: '/rh/ferias', nome: 'Ponto ↔ Férias' },
    { rota1: '/estoque/armazens', rota2: '/estoque/kardex', nome: 'Armazéns ↔ Kardex' },
  ];

  for (const { rota1, rota2, nome } of paginasVoltar) {
    test(`Voltar: ${nome}`, async ({ page }) => {
      await page.goto(rota1);
      await page.goto(rota2);
      await page.goBack();
      await expect(page).not.toHaveURL(/.*login/);
    });
  }
});

// ─── Testes de refresh (F5) ───
test.describe('Navegação — Refresh (F5)', () => {
  const paginasRefresh = [
    '/', '/cadastros/clientes', '/os', '/crm', '/financeiro',
    '/estoque', '/rh', '/equipamentos', '/inmetro', '/qualidade',
    '/configuracoes', '/perfil', '/orcamentos', '/relatorios',
    '/financeiro/pagar', '/financeiro/receber', '/crm/pipeline',
    '/os/kanban', '/rh/ponto', '/estoque/armazens',
    '/calibracoes', '/contratos', '/frota', '/alertas',
    '/emails', '/seguranca', '/ia', '/ceo-cockpit',
    '/automacao', '/avancado', '/laboratorio',
  ];

  for (const rota of paginasRefresh) {
    test(`Refresh: ${rota} deve sobreviver ao F5`, async ({ page }) => {
      await page.goto(rota);
      await page.reload();
      await expect(page).not.toHaveURL(/.*login/);
      await expect(page.locator('body')).not.toBeEmpty();
    });
  }
});
