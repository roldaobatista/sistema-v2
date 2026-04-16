<?php

namespace Database\Seeders;

use App\Models\InssBracket;
use App\Models\IrrfBracket;
use Illuminate\Database\Seeder;

/**
 * Tax brackets for 2026 (Brazil).
 * INSS: Portaria MPS (progressive brackets).
 * IRRF: Receita Federal (progressive brackets).
 */
class TaxBrackets2026Seeder extends Seeder
{
    public function run(): void
    {
        $this->seedInss();
        $this->seedIrrf();
    }

    private function seedInss(): void
    {
        $brackets = [
            ['year' => 2026, 'min_salary' => 0,       'max_salary' => 1518.00,  'rate' => 7.50,  'deduction' => 0],
            ['year' => 2026, 'min_salary' => 1518.01,  'max_salary' => 2793.88,  'rate' => 9.00,  'deduction' => 0],
            ['year' => 2026, 'min_salary' => 2793.89,  'max_salary' => 4190.83,  'rate' => 12.00, 'deduction' => 0],
            ['year' => 2026, 'min_salary' => 4190.84,  'max_salary' => 8157.41,  'rate' => 14.00, 'deduction' => 0],
        ];

        foreach ($brackets as $bracket) {
            InssBracket::updateOrCreate(
                ['year' => $bracket['year'], 'min_salary' => $bracket['min_salary']],
                $bracket
            );
        }

        $this->command->info('INSS 2026 brackets seeded (4 faixas progressivas).');
    }

    private function seedIrrf(): void
    {
        $brackets = [
            ['year' => 2026, 'min_base' => 0,       'max_base' => 2259.20,  'rate' => 0,     'deduction' => 0],
            ['year' => 2026, 'min_base' => 2259.21,  'max_base' => 2826.65,  'rate' => 7.50,  'deduction' => 169.44],
            ['year' => 2026, 'min_base' => 2826.66,  'max_base' => 3751.05,  'rate' => 15.00, 'deduction' => 381.44],
            ['year' => 2026, 'min_base' => 3751.06,  'max_base' => 4664.68,  'rate' => 22.50, 'deduction' => 662.77],
            ['year' => 2026, 'min_base' => 4664.69,  'max_base' => 999999.99, 'rate' => 27.50, 'deduction' => 896.00],
        ];

        // Per dependent deduction: R$ 189.59

        foreach ($brackets as $bracket) {
            IrrfBracket::updateOrCreate(
                ['year' => $bracket['year'], 'min_base' => $bracket['min_base']],
                $bracket
            );
        }

        $this->command->info('IRRF 2026 brackets seeded (5 faixas, deducao dependente R$ 189,59).');
    }
}
