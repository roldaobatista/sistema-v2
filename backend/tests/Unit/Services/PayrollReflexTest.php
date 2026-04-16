<?php

namespace Tests\Unit\Services;

use App\Services\LaborCalculationService;
use Database\Seeders\LaborTaxTablesSeeder;
use Tests\TestCase;

class PayrollReflexTest extends TestCase
{
    private LaborCalculationService $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new LaborCalculationService;
    }

    public function test_thirteenth_salary_includes_overtime_reflex(): void
    {
        $salary = 3000.00;
        $monthsWorked = 12;
        $overtimeAvg = 500.00;
        $dsrAvg = 100.00;
        $nightShiftAvg = 50.00;
        $commissionAvg = 200.00;

        $result = $this->calc->calculateThirteenthSalary(
            $salary, $monthsWorked, false,
            $overtimeAvg, $dsrAvg, $nightShiftAvg, $commissionAvg
        );

        // Total monthly = 3000 + 500 + 100 + 50 + 200 = 3850
        // Proportional = (3850 / 12) * 12 = 3850
        $this->assertEquals(3850.00, $result['gross_value']);
        $this->assertEquals(500.00, $result['overtime_avg']);
        $this->assertEquals(100.00, $result['dsr_avg']);
    }

    public function test_thirteenth_without_reflexes_uses_base_salary_only(): void
    {
        $result = $this->calc->calculateThirteenthSalary(3000.00, 12, false);
        $this->assertEquals(3000.00, $result['gross_value']);
    }

    public function test_thirteenth_second_installment_deducts_first(): void
    {
        // Need InssBracket and IrrfBracket seeded
        $this->seed(LaborTaxTablesSeeder::class);

        $result = $this->calc->calculateThirteenthSalary(3000.00, 12, true);
        $firstInstallment = round(3000.00 / 2, 2);
        $this->assertEquals($firstInstallment, $result['first_installment_paid']);
        $this->assertGreaterThan(0, $result['inss']);
    }

    public function test_vacation_pay_includes_overtime_reflex(): void
    {
        $salary = 3000.00;
        $overtimeReflex = 400.00;
        $dsrReflex = 80.00;
        $nightShiftReflex = 60.00;

        $result = $this->calc->calculateVacationPay(
            $salary, 30, 0,
            $overtimeReflex, $dsrReflex, $nightShiftReflex
        );

        // Total monthly = 3000 + 400 + 80 + 60 = 3540
        // Vacation salary = 3540 (30 days)
        // Constitutional bonus = 3540 / 3 = 1180
        // Gross = 3540 + 1180 = 4720
        $this->assertEquals(3540.00, $result['vacation_salary']);
        $this->assertEquals(1180.00, $result['constitutional_bonus']);
        $this->assertEquals(4720.00, $result['gross_total']);
    }

    public function test_vacation_without_reflexes_uses_base_salary(): void
    {
        $result = $this->calc->calculateVacationPay(3000.00, 30, 0);
        $this->assertEquals(3000.00, $result['vacation_salary']);
        $this->assertEquals(1000.00, $result['constitutional_bonus']);
        $this->assertEquals(4000.00, $result['gross_total']);
    }

    public function test_vacation_with_sold_days(): void
    {
        $result = $this->calc->calculateVacationPay(3000.00, 20, 10);
        // Daily rate = 3000/30 = 100
        // 20 days vacation = 2000
        // 1/3 of 2000 = 666.67
        // 10 days abono = 1000
        // 1/3 abono = 333.33
        $this->assertEquals(20, $result['vacation_days']);
        $this->assertEquals(10, $result['sold_days']);
        $this->assertGreaterThan(0, $result['abono_pecuniario']);
    }

    public function test_inss_progressive_brackets(): void
    {
        $this->seed(LaborTaxTablesSeeder::class);

        // Test with salary in first bracket
        $result = $this->calc->calculateINSS(1500.00);
        $this->assertGreaterThan(0, $result['total_deduction']);
        $this->assertLessThan(1500 * 0.14, $result['total_deduction']); // Less than max rate on full amount

        // Test ceiling
        $result = $this->calc->calculateINSS(10000.00);
        $this->assertGreaterThan(0, $result['total_deduction']);
    }

    public function test_irrf_with_dependents(): void
    {
        $this->seed(LaborTaxTablesSeeder::class);

        $inss = $this->calc->calculateINSS(5000.00);

        // Without dependents
        $irrf0 = $this->calc->calculateIRRF(5000.00, $inss['total_deduction'], 0);

        // With 3 dependents
        $irrf3 = $this->calc->calculateIRRF(5000.00, $inss['total_deduction'], 3);

        // More dependents = less IRRF
        $this->assertGreaterThanOrEqual($irrf3['value'], $irrf0['value']);
    }

    public function test_fgts_always_8_percent(): void
    {
        $result = $this->calc->calculateFGTS(5000.00);
        $this->assertEquals(400.00, $result['value']);
        $this->assertEquals(8.0, $result['rate']);
    }

    public function test_dsr_formula(): void
    {
        // Overtime total: 500, Commission: 200, Work days: 22, Sundays+holidays: 8
        $result = $this->calc->calculateDSR(500.00, 200.00, 22, 8);
        // DSR = (700 / 22) * 8 = 254.55
        $this->assertEquals(254.55, $result['value']);
    }
}
