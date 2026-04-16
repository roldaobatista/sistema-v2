<?php

namespace Tests\Unit\Services;

use App\Services\Calibration\EmaCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Testes profundos do EmaCalculator real:
 * calculate(), calculateForPoints(), suggestPoints(),
 * suggestEccentricityLoad(), suggestRepeatabilityLoad(),
 * isConforming(), availableClasses().
 * Baseado na Portaria INMETRO nº 157/2022 e OIML R76-1:2006.
 */
class EmaCalculatorRealTest extends TestCase
{
    // ═══ calculate() — Class III (typical commercial balance) ═══

    public function test_class_iii_low_load_within_500e(): void
    {
        // e=1g, load=200g → 200 multiples_of_e → ≤500 → EMA = 0.5*e = 0.5g
        $ema = EmaCalculator::calculate('III', 1.0, 200.0, 'initial');
        $this->assertEquals(0.5, $ema);
    }

    public function test_class_iii_medium_load_within_2000e(): void
    {
        // e=1g, load=1000g → 1000 multiples → ≤2000 → EMA = 1.0*e = 1.0g
        $ema = EmaCalculator::calculate('III', 1.0, 1000.0, 'initial');
        $this->assertEquals(1.0, $ema);
    }

    public function test_class_iii_high_load_above_2000e(): void
    {
        // e=1g, load=5000g → 5000 multiples → ≤10000 → EMA = 1.5*e = 1.5g
        $ema = EmaCalculator::calculate('III', 1.0, 5000.0, 'initial');
        $this->assertEquals(1.5, $ema);
    }

    public function test_class_iii_in_use_doubles_ema(): void
    {
        // In-use verification doubles the EMA per Portaria 157/2022
        $emaInitial = EmaCalculator::calculate('III', 1.0, 200.0, 'initial');
        $emaInUse = EmaCalculator::calculate('III', 1.0, 200.0, 'in_use');
        $this->assertEquals($emaInitial * 2, $emaInUse);
    }

    // ═══ calculate() — Class I (analytical balance) ═══

    public function test_class_i_low_load(): void
    {
        // e=0.001g, load=10g → 10000 multiples → ≤50000 → EMA = 0.5*e = 0.0005g
        $ema = EmaCalculator::calculate('I', 0.001, 10.0, 'initial');
        $this->assertEquals(0.0005, $ema);
    }

    public function test_class_i_high_load(): void
    {
        // e=0.001g, load=300g → 300000 multiples → >200000 → EMA = 1.5*e = 0.0015g
        $ema = EmaCalculator::calculate('I', 0.001, 300.0, 'initial');
        $this->assertEquals(0.0015, $ema);
    }

    // ═══ calculate() — Class II (semi-analytical) ═══

    public function test_class_ii_low_load(): void
    {
        // e=0.01g, load=10g → 1000 multiples → ≤5000 → EMA = 0.5*e = 0.005g
        $ema = EmaCalculator::calculate('II', 0.01, 10.0, 'initial');
        $this->assertEquals(0.005, $ema);
    }

    // ═══ calculate() — Class IIII (road bridges) ═══

    public function test_class_iiii_low_load(): void
    {
        // e=50kg, load=1000kg → 20 multiples → ≤50 → EMA = 0.5*e = 25kg
        $ema = EmaCalculator::calculate('IIII', 50.0, 1000.0, 'initial');
        $this->assertEquals(25.0, $ema);
    }

    // ═══ Invalid inputs ═══

    public function test_invalid_class_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmaCalculator::calculate('X', 1.0, 100.0);
    }

    public function test_zero_e_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmaCalculator::calculate('III', 0.0, 100.0);
    }

    public function test_negative_e_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmaCalculator::calculate('III', -1.0, 100.0);
    }

    // ═══ calculateForPoints() ═══

    public function test_calculate_for_points_returns_array(): void
    {
        $result = EmaCalculator::calculateForPoints('III', 1.0, [100.0, 500.0, 1000.0]);
        $this->assertCount(3, $result);
        $this->assertArrayHasKey('load', $result[0]);
        $this->assertArrayHasKey('ema', $result[0]);
        $this->assertArrayHasKey('multiples_of_e', $result[0]);
    }

    public function test_calculate_for_points_correct_emas(): void
    {
        $result = EmaCalculator::calculateForPoints('III', 1.0, [200.0, 1000.0, 5000.0]);
        $this->assertEquals(0.5, $result[0]['ema']);
        $this->assertEquals(1.0, $result[1]['ema']);
        $this->assertEquals(1.5, $result[2]['ema']);
    }

    // ═══ suggestPoints() ═══

    public function test_suggest_points_returns_5(): void
    {
        $points = EmaCalculator::suggestPoints('III', 1.0, 10000.0);
        $this->assertCount(5, $points);
    }

    public function test_suggest_points_first_is_zero(): void
    {
        $points = EmaCalculator::suggestPoints('III', 1.0, 10000.0);
        $this->assertEquals(0, $points[0]['load']);
        $this->assertEquals(0, $points[0]['percentage']);
        $this->assertEquals(0.0, $points[0]['ema']);
    }

    public function test_suggest_points_last_is_100_percent(): void
    {
        $points = EmaCalculator::suggestPoints('III', 1.0, 10000.0);
        $this->assertEquals(100, $points[4]['percentage']);
        $this->assertEquals(10000.0, $points[4]['load']);
    }

    public function test_suggest_points_percentages(): void
    {
        $points = EmaCalculator::suggestPoints('III', 1.0, 10000.0);
        $percentages = array_column($points, 'percentage');
        $this->assertEquals([0, 25, 50, 75, 100], $percentages);
    }

    // ═══ suggestEccentricityLoad() ═══

    public function test_eccentricity_load_third_of_max(): void
    {
        $load = EmaCalculator::suggestEccentricityLoad(30000.0);
        $this->assertEquals(10000.0, $load);
    }

    public function test_eccentricity_load_precision(): void
    {
        $load = EmaCalculator::suggestEccentricityLoad(100.0);
        $this->assertEqualsWithDelta(33.3333, $load, 0.001);
    }

    // ═══ suggestRepeatabilityLoad() ═══

    public function test_repeatability_load_half_of_max(): void
    {
        $load = EmaCalculator::suggestRepeatabilityLoad(10000.0);
        $this->assertEquals(5000.0, $load);
    }

    // ═══ isConforming() ═══

    public function test_conforming_within_ema(): void
    {
        $this->assertTrue(EmaCalculator::isConforming(0.3, 0.5));
    }

    public function test_conforming_at_ema_boundary(): void
    {
        $this->assertTrue(EmaCalculator::isConforming(0.5, 0.5));
    }

    public function test_non_conforming_exceeds_ema(): void
    {
        $this->assertFalse(EmaCalculator::isConforming(0.6, 0.5));
    }

    public function test_conforming_negative_error(): void
    {
        $this->assertTrue(EmaCalculator::isConforming(-0.3, 0.5));
    }

    public function test_non_conforming_negative_error(): void
    {
        $this->assertFalse(EmaCalculator::isConforming(-0.6, 0.5));
    }

    // ═══ availableClasses() ═══

    public function test_available_classes(): void
    {
        $classes = EmaCalculator::availableClasses();
        $this->assertContains('I', $classes);
        $this->assertContains('II', $classes);
        $this->assertContains('III', $classes);
        $this->assertContains('IIII', $classes);
        $this->assertCount(4, $classes);
    }

    // ═══ Case insensitive class ═══

    public function test_lowercase_class_works(): void
    {
        $ema = EmaCalculator::calculate('iii', 1.0, 200.0);
        $this->assertEquals(0.5, $ema);
    }
}
