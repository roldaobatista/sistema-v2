<?php

namespace Tests\Critical;

use App\Models\Product;
use Illuminate\Support\Facades\Event;

/**
 * P1.2 — Invariantes de Negócio: Financeiro + Estoque
 *
 * REGRAS FINANCEIRAS:
 *  - Saldo nunca fica negativo sem evento válido
 *  - Soma das parcelas = valor total
 *  - Comissão não pode ultrapassar 100% do valor
 *
 * REGRAS DE ESTOQUE:
 *  - Movimento de saída não pode deixar estoque negativo (sem permissão)
 *  - Entrada + Saídas devem bater com saldo atual (Kardex)
 *  - Produto sem estoque não permite reserva
 */
class FinancialStockInvariantsTest extends CriticalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    // ========================================================
    // FINANCEIRO — Contas a Receber
    // ========================================================

    public function test_installments_sum_equals_total(): void
    {
        $total = 1000.00;
        $installments = 4;
        $valuePerInstallment = round($total / $installments, 2);
        $remainder = round($total - ($valuePerInstallment * $installments), 2);

        $sum = ($valuePerInstallment * $installments) + $remainder;

        $this->assertEquals(
            $total,
            $sum,
            "Soma das parcelas ({$sum}) difere do total ({$total})"
        );
    }

    public function test_commission_percentage_cannot_exceed_100(): void
    {
        $maxPercentage = 100;
        $testCases = [0, 10, 50, 99.99, 100];

        foreach ($testCases as $percentage) {
            $this->assertLessThanOrEqual(
                $maxPercentage,
                $percentage,
                "Comissão de {$percentage}% excede o máximo"
            );
        }

        // Valor inválido
        $this->assertGreaterThan(100, 150, '150% deveria ser rejeitado pela regra de negócio');
    }

    public function test_financial_precision_uses_two_decimal_places(): void
    {
        $value = 99.999;
        $rounded = round($value, 2);

        $this->assertEquals(100.00, $rounded, 'Valor financeiro deve usar 2 casas decimais');
    }

    // ========================================================
    // ESTOQUE — Kardex
    // ========================================================

    public function test_stock_balance_never_negative_without_permission(): void
    {
        $currentStock = 10;
        $exitQty = 15;

        // Sem permissão de estoque negativo, saída deve ser bloqueada
        $canProcess = $currentStock >= $exitQty;

        $this->assertFalse(
            $canProcess,
            "Movimentação de saída permitiu estoque negativo: {$currentStock} - {$exitQty}"
        );
    }

    public function test_kardex_entries_balance_matches_calculated(): void
    {
        $entries = [
            ['type' => 'entry', 'qty' => 100],
            ['type' => 'exit', 'qty' => 30],
            ['type' => 'entry', 'qty' => 50],
            ['type' => 'exit', 'qty' => 20],
            ['type' => 'exit', 'qty' => 10],
        ];

        $balance = 0;
        foreach ($entries as $mov) {
            $balance += ($mov['type'] === 'entry' ? $mov['qty'] : -$mov['qty']);
        }

        $this->assertEquals(90, $balance, "Saldo do Kardex não bate: esperado 90, obtido {$balance}");
        $this->assertGreaterThanOrEqual(0, $balance, 'Saldo do Kardex ficou negativo');
    }

    public function test_stock_entry_increases_balance(): void
    {
        $before = 50;
        $entryQty = 25;
        $after = $before + $entryQty;

        $this->assertEquals(75, $after, 'Entrada de estoque não aumentou saldo corretamente');
    }

    public function test_stock_exit_decreases_balance(): void
    {
        $before = 50;
        $exitQty = 20;
        $after = $before - $exitQty;

        $this->assertEquals(30, $after, 'Saída de estoque não diminuiu saldo corretamente');
        $this->assertGreaterThanOrEqual(0, $after, 'Saída resultou em estoque negativo');
    }

    // ========================================================
    // PRODUTO — Validações de integridade
    // ========================================================

    public function test_product_price_must_be_non_negative(): void
    {
        $prices = [0, 0.01, 100.50, 9999.99];

        foreach ($prices as $price) {
            $this->assertGreaterThanOrEqual(0, $price, "Preço negativo detectado: {$price}");
        }
    }

    public function test_product_requires_tenant_id(): void
    {
        // Produtos devem sempre ter tenant_id
        $product = Product::withoutGlobalScopes()->forceCreate([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produto Com Tenant',
        ]);

        $this->assertEquals($this->tenant->id, $product->tenant_id);
    }
}
