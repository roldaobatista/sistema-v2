import { test, expect } from '../fixtures';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DO MÓDULO INMETRO                                    ║
// ║  Dashboard, Leads, Instrumentos, Importação, Concorrentes    ║
// ╚═══════════════════════════════════════════════════════════════╝

const rotasInmetro = [
  { rota: '/inmetro', nome: 'Dashboard Inmetro' },
  { rota: '/inmetro/leads', nome: 'Leads Inmetro' },
  { rota: '/inmetro/instrumentos', nome: 'Instrumentos' },
  { rota: '/inmetro/importacao', nome: 'Importação' },
  { rota: '/inmetro/concorrentes', nome: 'Concorrentes' },
  { rota: '/inmetro/mapa', nome: 'Mapa Inmetro' },
  { rota: '/inmetro/mercado', nome: 'Mercado' },
  { rota: '/inmetro/prospeccao', nome: 'Prospecção' },
  { rota: '/inmetro/executivo', nome: 'Executivo' },
  { rota: '/inmetro/compliance', nome: 'Compliance' },
  { rota: '/inmetro/webhooks', nome: 'Webhooks' },
  { rota: '/inmetro/selos', nome: 'Selos' },
  { rota: '/inmetro/relatorio-selos', nome: 'Relatório de Selos' },
];

for (const { rota, nome } of rotasInmetro) {
  test(`Inmetro: ${nome} — deve carregar`, async ({ page }) => {
    await page.goto(rota);
    await expect(page).not.toHaveURL(/.*login/);
    await expect(page.locator('body')).not.toBeEmpty();
  });
}

test.describe('Inmetro — Comportamento', () => {
  test('Dashboard Inmetro deve ter URL correta', async ({ page }) => {
    await page.goto('/inmetro');
    await expect(page).toHaveURL(/.*inmetro/);
  });

  test('Leads deve exibir conteúdo', async ({ page }) => {
    await page.goto('/inmetro/leads');
    await expect(page.locator('body')).toBeVisible();
  });

  test('Instrumentos deve estar acessível', async ({ page }) => {
    await page.goto('/inmetro/instrumentos');
    await expect(page).not.toHaveURL(/.*login/);
  });

  test('Compliance deve estar acessível', async ({ page }) => {
    await page.goto('/inmetro/compliance');
    await expect(page).not.toHaveURL(/.*login/);
  });

  test('Selos deve estar acessível', async ({ page }) => {
    await page.goto('/inmetro/selos');
    await expect(page).not.toHaveURL(/.*login/);
  });
});

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DO MÓDULO DE QUALIDADE                               ║
// ╚═══════════════════════════════════════════════════════════════╝

const rotasQualidade = [
  { rota: '/qualidade', nome: 'Dashboard Qualidade' },
  { rota: '/qualidade/auditorias', nome: 'Auditorias' },
  { rota: '/qualidade/documentos', nome: 'Documentos' },
  { rota: '/qualidade/revisao-direcao', nome: 'Revisão da Direção' },
];

for (const { rota, nome } of rotasQualidade) {
  test(`Qualidade: ${nome} — deve carregar`, async ({ page }) => {
    await page.goto(rota);
    await expect(page).not.toHaveURL(/.*login/);
    await expect(page.locator('body')).not.toBeEmpty();
  });
}

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DO MÓDULO FISCAL                                     ║
// ╚═══════════════════════════════════════════════════════════════╝

const rotasFiscal = [
  { rota: '/fiscal', nome: 'Dashboard Fiscal' },
  { rota: '/fiscal/notas', nome: 'Notas Fiscais' },
  { rota: '/fiscal/configuracoes', nome: 'Configurações Fiscais' },
];

for (const { rota, nome } of rotasFiscal) {
  test(`Fiscal: ${nome} — deve carregar`, async ({ page }) => {
    await page.goto(rota);
    await expect(page).not.toHaveURL(/.*login/);
    await expect(page.locator('body')).not.toBeEmpty();
  });
}
