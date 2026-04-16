import { test, expect } from '../fixtures';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DE ESTADO VAZIO E ERROS DE REDE                      ║
// ║  Verifica Empty States, Loading States, Error Boundaries      ║
// ╚═══════════════════════════════════════════════════════════════╝

const todasAsPaginas = [
  '/', '/cadastros/clientes', '/cadastros/produtos', '/cadastros/servicos',
  '/cadastros/fornecedores', '/os', '/os/kanban', '/os/nova',
  '/os/dashboard', '/os/mapa', '/chamados', '/chamados/kanban',
  '/chamados/dashboard', '/crm', '/crm/pipeline', '/crm/forecast',
  '/crm/goals', '/crm/nps', '/crm/proposals', '/crm/scoring',
  '/financeiro', '/financeiro/pagar', '/financeiro/receber',
  '/financeiro/fluxo-caixa', '/financeiro/dre',
  '/financeiro/comissoes', '/financeiro/conciliacao-bancaria',
  '/estoque', '/estoque/armazens', '/estoque/movimentacoes',
  '/estoque/kardex', '/estoque/transferencias',
  '/rh', '/rh/ponto', '/rh/ferias', '/rh/desempenho',
  '/rh/recrutamento', '/rh/beneficios',
  '/equipamentos', '/equipamentos/modelos', '/calibracoes',
  '/inmetro', '/inmetro/leads', '/inmetro/instrumentos',
  '/qualidade', '/qualidade/auditorias',
  '/configuracoes', '/configuracoes/filiais', '/configuracoes/empresas',
  '/orcamentos', '/relatorios', '/analytics',
  '/perfil', '/iam/usuarios', '/iam/roles',
  '/contratos', '/frota', '/alertas',
  '/ceo-cockpit', '/ia', '/automacao',
];

// ─── Teste: Nenhuma página deve exibir tela branca completa ───
test.describe('Empty State — Nenhuma Tela em Branco', () => {
  for (const rota of todasAsPaginas) {
    test(`${rota} deve ter conteúdo visível (não tela branca)`, async ({ page }) => {
      await page.goto(rota);
      await expect(page.locator('body')).not.toBeEmpty({ timeout: 10000 });
      const bodyText = await page.locator('body').innerText();
      expect(bodyText.trim().length).toBeGreaterThan(0);
    });
  }
});

// ─── Teste: Nenhuma página crashou com Error Boundary ───
test.describe('Error Boundary — Sem Crashes React', () => {
  for (const rota of todasAsPaginas) {
    test(`${rota} não deve exibir Error Boundary`, async ({ page }) => {
      await page.goto(rota);
      await expect(page.locator('body')).not.toBeEmpty({ timeout: 10000 });
      // Textos comuns de Error Boundary React
      const errorBoundary = page.getByText(/something went wrong|erro inesperado|falha ao renderizar|chunk failed/i);
      const count = await errorBoundary.count();
      expect(count).toBe(0);
    });
  }
});
