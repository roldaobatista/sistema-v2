<?php

use App\Services\LaborCalculationService;
use Database\Seeders\LaborTaxTablesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(LaborTaxTablesSeeder::class);
    $this->service = new LaborCalculationService;
});

// ── INSS Tests ──

test('inss progressive calculation with salary R$ 5000', function () {
    $result = $this->service->calculateINSS(5000.00, 2026);

    // 1ª faixa: 1518.00 * 7.5% = 113.85
    // 2ª faixa: (2793.88 - 1518.01) * 9% = 1275.87 * 9% = 114.83
    // 3ª faixa: (4190.83 - 2793.89) * 12% = 1396.94 * 12% = 167.63
    // 4ª faixa: (5000.00 - 4190.84) * 14% = 809.16 * 14% = 113.28
    // Total = 113.85 + 114.83 + 167.63 + 113.28 = 509.59

    expect($result['gross_salary'])->toBe(5000.00);
    expect($result['total_deduction'])->toBeGreaterThan(500);
    expect($result['total_deduction'])->toBeLessThan(520);
    expect($result['details'])->toHaveCount(4);
    expect($result['details'][0]['rate'])->toBe(7.5);
    expect($result['details'][1]['rate'])->toBe(9.0);
    expect($result['details'][2]['rate'])->toBe(12.0);
    expect($result['details'][3]['rate'])->toBe(14.0);
});

test('inss calculation with minimum wage R$ 1518', function () {
    $result = $this->service->calculateINSS(1518.00, 2026);

    // Only first bracket: 1518.00 * 7.5% = 113.85
    expect($result['total_deduction'])->toBe(113.85);
    expect($result['details'])->toHaveCount(1);
    expect($result['details'][0]['rate'])->toBe(7.5);
});

// ── IRRF Tests ──

test('irrf exempt salary below threshold', function () {
    // Salary R$ 2400
    // INSS: 1518*7.5% = 113.85 + (2400-1518.01)*9% = 881.99*9% = 79.38 = total ~193.23
    // Base = 2400 - 193.23 = 2206.77, below 2259.20, so exempt
    $inss = $this->service->calculateINSS(2400.00, 2026);
    $result = $this->service->calculateIRRF(2400.00, $inss['total_deduction'], 0, 2026);

    expect($result['exempt'])->toBeTrue();
    expect($result['value'])->toBe(0);
});

test('irrf with dependents reduces base', function () {
    // Salary R$ 5000, with 2 dependents
    $inss = $this->service->calculateINSS(5000.00, 2026);
    $resultNoDep = $this->service->calculateIRRF(5000.00, $inss['total_deduction'], 0, 2026);
    $resultWithDep = $this->service->calculateIRRF(5000.00, $inss['total_deduction'], 2, 2026);

    // With dependents, base is lower, so IRRF should be lower
    expect($resultWithDep['value'])->toBeLessThan($resultNoDep['value']);
    expect($resultWithDep['dependents_deduction'])->toBe(189.59 * 2);
});

test('irrf highest bracket with salary R$ 10000', function () {
    $inss = $this->service->calculateINSS(10000.00, 2026);
    $result = $this->service->calculateIRRF(10000.00, $inss['total_deduction'], 0, 2026);

    // Base = 10000 - INSS(~713.10 capped at ceiling) => ~9286.90
    // Should hit 27.5% bracket
    expect($result['exempt'])->toBeFalse();
    expect($result['rate'])->toBe(27.5);
    expect($result['value'])->toBeGreaterThan(0);
});

// ── FGTS Tests ──

test('fgts calculation always 8 percent', function () {
    $result = $this->service->calculateFGTS(5000.00);

    expect($result['base'])->toBe(5000.00);
    expect($result['rate'])->toBe(8.0);
    expect($result['value'])->toBe(400.00);
});

// ── Vacation Pay Tests ──

test('vacation pay with constitutional bonus 30 days', function () {
    $result = $this->service->calculateVacationPay(3000.00, 30, 0);

    expect($result['vacation_salary'])->toBe(3000.00);
    expect($result['constitutional_bonus'])->toBe(1000.00);
    expect($result['sold_days'])->toBe(0);
    expect((float) $result['abono_pecuniario'])->toBe(0.0);
    expect($result['gross_total'])->toBe(4000.00);
});

test('vacation pay with sold days abono pecuniario', function () {
    $result = $this->service->calculateVacationPay(3000.00, 20, 10);

    $dailyRate = 3000.00 / 30;
    $vacationSalary = round($dailyRate * 20, 2);
    $constitutionalBonus = round($vacationSalary / 3, 2);
    $abono = round($dailyRate * 10, 2);
    $abonoBonus = round($abono / 3, 2);

    expect($result['vacation_days'])->toBe(20);
    expect($result['sold_days'])->toBe(10);
    expect($result['vacation_salary'])->toBe($vacationSalary);
    expect($result['constitutional_bonus'])->toBe($constitutionalBonus);
    expect($result['abono_pecuniario'])->toBe($abono);
    expect($result['abono_pecuniario_bonus'])->toBe($abonoBonus);
    expect($result['gross_total'])->toBe($vacationSalary + $constitutionalBonus + $abono + $abonoBonus);
});

// ── 13th Salary Tests ──

test('thirteenth salary first installment 50 percent no deductions', function () {
    $result = $this->service->calculateThirteenthSalary(6000.00, 12);

    expect($result['months_worked'])->toBe(12);
    expect($result['gross_value'])->toBe(6000.00);
    expect($result['installment_value'])->toBe(3000.00);
    expect($result['deductions'])->toBe(0);
    expect($result['net_value'])->toBe(3000.00);
});

test('thirteenth salary second installment with inss and irrf deductions', function () {
    $result = $this->service->calculateThirteenthSalary(6000.00, 12, true);

    expect($result['months_worked'])->toBe(12);
    expect($result['gross_value'])->toBe(6000.00);
    expect($result['first_installment_paid'])->toBe(3000.00);
    expect($result['inss'])->toBeGreaterThan(0);
    expect($result['irrf'])->toBeGreaterThanOrEqual(0);
    // Net = gross - first installment - INSS - IRRF
    $expectedNet = round(6000.00 - 3000.00 - $result['inss'] - $result['irrf'], 2);
    expect($result['net_value'])->toBe($expectedNet);
});

// ── DSR Tests ──

test('dsr calculation on overtime and commissions', function () {
    $result = $this->service->calculateDSR(500.00, 300.00, 22, 8);

    // base = 500 + 300 = 800
    // DSR = (800 / 22) * 8 = 290.91
    expect($result['base'])->toBe(800.00);
    expect($result['value'])->toBe(290.91);
    expect($result['work_days'])->toBe(22);
    expect($result['sundays_and_holidays'])->toBe(8);
});

test('dsr calculation with zero work days returns zero', function () {
    $result = $this->service->calculateDSR(500.00, 300.00, 0, 8);

    expect($result['base'])->toBe(0);
    expect($result['value'])->toBe(0);
});

// ── Overtime & Night Shift Tests ──

test('overtime pay calculation', function () {
    $hourlyRate = 25.00;
    $result = $this->service->calculateOvertimePay($hourlyRate, 2, 50);

    // 25 * 2 * 1.5 = 75
    expect($result)->toBe(75.00);
});

test('overtime pay 100 percent on weekends', function () {
    $hourlyRate = 25.00;
    $result = $this->service->calculateOvertimePay($hourlyRate, 2, 100);

    // 25 * 2 * 2.0 = 100
    expect($result)->toBe(100.00);
});

test('night shift pay calculation', function () {
    $hourlyRate = 25.00;
    $result = $this->service->calculateNightShiftPay($hourlyRate, 7, 20);

    // 25 * 7 * 0.20 = 35
    expect($result)->toBe(35.00);
});

test('hourly rate from monthly salary', function () {
    $result = $this->service->getHourlyRate(3300.00, 220);

    expect($result)->toBe(15.00);
});
