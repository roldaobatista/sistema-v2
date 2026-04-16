import { test, expect } from '../fixtures';

// ╔═══════════════════════════════════════════════════════════════╗
// ║  TESTES DO MÓDULO DE EQUIPAMENTOS                            ║
// ║  Lista, Modelos, Criação, Pesos, Manutenções, Calibrações   ║
// ╚═══════════════════════════════════════════════════════════════╝

const rotasEquipamentos = [
  { rota: '/equipamentos', nome: 'Lista de Equipamentos' },
  { rota: '/equipamentos/modelos', nome: 'Modelos de Equipamento' },
  { rota: '/equipamentos/novo', nome: 'Novo Equipamento' },
  { rota: '/equipamentos/pesos-padrao', nome: 'Pesos Padrão' },
  { rota: '/equipamentos/atribuicao-pesos', nome: 'Atribuição de Pesos' },
  { rota: '/equipamentos/manutencoes', nome: 'Manutenções' },
  { rota: '/agenda-calibracoes', nome: 'Agenda de Calibrações' },
  { rota: '/calibracoes', nome: 'Lista de Calibrações' },
  { rota: '/calibracao/leituras', nome: 'Leituras de Calibração' },
  { rota: '/calibracao/templates', nome: 'Templates de Certificado' },
];

for (const { rota, nome } of rotasEquipamentos) {
  test(`Equipamentos: ${nome} — deve carregar`, async ({ page }) => {
    await page.goto(rota);
    await expect(page).not.toHaveURL(/.*login/);
    await expect(page.locator('body')).not.toBeEmpty();
  });
}

test.describe('Equipamentos — Comportamento', () => {
  test('Lista de equipamentos deve ter URL correta', async ({ page }) => {
    await page.goto('/equipamentos');
    await expect(page).toHaveURL(/.*equipamentos/);
  });

  test('Novo equipamento deve estar acessível', async ({ page }) => {
    await page.goto('/equipamentos/novo');
    await expect(page).not.toHaveURL(/.*login/);
  });

  test('Modelos deve exibir conteúdo', async ({ page }) => {
    await page.goto('/equipamentos/modelos');
    await expect(page.locator('body')).toBeVisible();
  });

  test('Calibrações deve estar acessível', async ({ page }) => {
    await page.goto('/calibracoes');
    await expect(page).not.toHaveURL(/.*login/);
  });

  test('Manutenções deve estar acessível', async ({ page }) => {
    await page.goto('/equipamentos/manutencoes');
    await expect(page).not.toHaveURL(/.*login/);
  });
});
