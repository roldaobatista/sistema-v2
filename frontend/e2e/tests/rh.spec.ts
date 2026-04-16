import { test, expect } from '../fixtures';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DO MÓDULO DE RH                                      ║
// ║  Ponto, Férias, Documentos, Onboarding, Skills, etc          ║
// ╚═══════════════════════════════════════════════════════════════╝

const rotasRh = [
  { rota: '/rh', nome: 'Dashboard RH' },
  { rota: '/rh/ponto', nome: 'Ponto' },
  { rota: '/rh/geofences', nome: 'Geofences' },
  { rota: '/rh/ajustes-ponto', nome: 'Ajustes de Ponto' },
  { rota: '/rh/jornada', nome: 'Jornada' },
  { rota: '/rh/jornada/regras', nome: 'Regras de Jornada' },
  { rota: '/rh/feriados', nome: 'Feriados' },
  { rota: '/rh/ferias', nome: 'Férias' },
  { rota: '/rh/saldo-ferias', nome: 'Saldo de Férias' },
  { rota: '/rh/documentos', nome: 'Documentos' },
  { rota: '/rh/onboarding', nome: 'Onboarding' },
  { rota: '/rh/organograma', nome: 'Organograma' },
  { rota: '/rh/skills', nome: 'Matriz de Skills' },
  { rota: '/rh/desempenho', nome: 'Desempenho' },
  { rota: '/rh/beneficios', nome: 'Benefícios' },
  { rota: '/rh/recrutamento', nome: 'Recrutamento' },
  { rota: '/rh/analytics', nome: 'People Analytics' },
  { rota: '/rh/relatorios', nome: 'Relatórios Contábeis' },
  { rota: '/rh/escalas', nome: 'Escalas de Trabalho' },
];

for (const { rota, nome } of rotasRh) {
  test(`RH: ${nome} — deve carregar`, async ({ page }) => {
    await page.goto(rota);
    await expect(page).not.toHaveURL(/.*login/);
    await expect(page.locator('body')).not.toBeEmpty();
  });
}

test.describe('RH — Comportamento', () => {
  test('Dashboard RH deve ter URL correta', async ({ page }) => {
    await page.goto('/rh');
    await expect(page).toHaveURL(/.*rh/);
  });

  test('Ponto deve exibir conteúdo', async ({ page }) => {
    await page.goto('/rh/ponto');
    await expect(page.locator('body')).toBeVisible();
  });

  test('Férias deve estar acessível', async ({ page }) => {
    await page.goto('/rh/ferias');
    await expect(page).not.toHaveURL(/.*login/);
  });

  test('Organograma deve estar acessível', async ({ page }) => {
    await page.goto('/rh/organograma');
    await expect(page).not.toHaveURL(/.*login/);
  });

  test('Recrutamento deve estar acessível', async ({ page }) => {
    await page.goto('/rh/recrutamento');
    await expect(page).not.toHaveURL(/.*login/);
  });

  test('People Analytics deve estar acessível', async ({ page }) => {
    await page.goto('/rh/analytics');
    await expect(page).not.toHaveURL(/.*login/);
  });
});
