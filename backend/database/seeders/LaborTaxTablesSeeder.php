<?php

namespace Database\Seeders;

use App\Models\InssBracket;
use App\Models\IrrfBracket;
use App\Models\MinimumWage;
use Illuminate\Database\Seeder;

class LaborTaxTablesSeeder extends Seeder
{
    public function run(): void
    {
        $year = 2026;

        // INSS 2026 brackets (progressive)
        $inssBrackets = [
            ['year' => $year, 'min_salary' => 0.00,    'max_salary' => 1518.00,  'rate' => 7.50,  'deduction' => 0],
            ['year' => $year, 'min_salary' => 1518.01, 'max_salary' => 2793.88,  'rate' => 9.00,  'deduction' => 0],
            ['year' => $year, 'min_salary' => 2793.89, 'max_salary' => 4190.83,  'rate' => 12.00, 'deduction' => 0],
            ['year' => $year, 'min_salary' => 4190.84, 'max_salary' => 8157.41,  'rate' => 14.00, 'deduction' => 0],
        ];

        foreach ($inssBrackets as $bracket) {
            InssBracket::updateOrCreate(
                ['year' => $bracket['year'], 'min_salary' => $bracket['min_salary']],
                $bracket
            );
        }

        // IRRF 2026 brackets
        $irrfBrackets = [
            ['year' => $year, 'min_base' => 0.00,    'max_base' => 2259.20,  'rate' => 0.00,  'deduction' => 0.00],
            ['year' => $year, 'min_base' => 2259.21, 'max_base' => 2826.65,  'rate' => 7.50,  'deduction' => 169.44],
            ['year' => $year, 'min_base' => 2826.66, 'max_base' => 3751.05,  'rate' => 15.00, 'deduction' => 381.44],
            ['year' => $year, 'min_base' => 3751.06, 'max_base' => 4664.68,  'rate' => 22.50, 'deduction' => 662.77],
            ['year' => $year, 'min_base' => 4664.69, 'max_base' => null,     'rate' => 27.50, 'deduction' => 896.00],
        ];

        foreach ($irrfBrackets as $bracket) {
            IrrfBracket::updateOrCreate(
                ['year' => $bracket['year'], 'min_base' => $bracket['min_base']],
                $bracket
            );
        }

        // Minimum wage 2026 (all months)
        for ($month = 1; $month <= 12; $month++) {
            MinimumWage::updateOrCreate(
                ['year' => $year, 'month' => $month],
                ['value' => 1518.00]
            );
        }
    }
}
