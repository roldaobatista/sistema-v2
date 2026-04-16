<?php

namespace Tests\Unit\Calibration;

use App\Services\Calibration\EmaCalculator;
use Tests\TestCase;

class EmaCalculatorEdgeCaseTest extends TestCase
{
    /**
     * @dataProvider boundaryProvider
     */
    public function test_ema_at_class_boundaries(string $class, float $eValue, float $load, string $verType, float $expectedEma): void
    {
        $ema = EmaCalculator::calculate($class, $eValue, $load, $verType);
        $this->assertEqualsWithDelta($expectedEma, $ema, 0.000001, "Class {$class}, e={$eValue}, load={$load}, type={$verType}");
    }

    public static function boundaryProvider(): array
    {
        return [
            // Class III, e=1: boundaries at 500e, 2000e, 10000e
            'III at exactly 500e initial' => ['III', 1.0, 500.0, 'initial', 0.5],
            'III at 501e initial (crosses to 1.0)' => ['III', 1.0, 501.0, 'initial', 1.0],
            'III at 2000e initial' => ['III', 1.0, 2000.0, 'initial', 1.0],
            'III at 2001e initial (crosses to 1.5)' => ['III', 1.0, 2001.0, 'initial', 1.5],
            'III at 10000e initial' => ['III', 1.0, 10000.0, 'initial', 1.5],
            // Class III in_use = 2x
            'III at 500e in_use' => ['III', 1.0, 500.0, 'in_use', 1.0],
            'III at 2001e in_use' => ['III', 1.0, 2001.0, 'in_use', 3.0],

            // Class I, e=0.001: boundaries at 50000e, 200000e
            'I at 50000e initial' => ['I', 0.001, 50.0, 'initial', 0.0005],
            'I at 50001e initial' => ['I', 0.001, 50.001, 'initial', 0.001],
            'I at 200000e initial' => ['I', 0.001, 200.0, 'initial', 0.001],
            'I at 200001e initial' => ['I', 0.001, 200.001, 'initial', 0.0015],

            // Class II, e=0.01: boundaries at 5000e, 20000e, 100000e
            'II at 5000e initial' => ['II', 0.01, 50.0, 'initial', 0.005],
            'II at 5001e initial' => ['II', 0.01, 50.01, 'initial', 0.01],
            'II at 20000e initial' => ['II', 0.01, 200.0, 'initial', 0.01],
            'II at 20001e initial' => ['II', 0.01, 200.01, 'initial', 0.015],

            // Class IIII, e=10: boundaries at 50e, 200e, 1000e
            'IIII at 50e initial' => ['IIII', 10.0, 500.0, 'initial', 5.0],
            'IIII at 51e initial' => ['IIII', 10.0, 510.0, 'initial', 10.0],
            'IIII at 200e initial' => ['IIII', 10.0, 2000.0, 'initial', 10.0],
            'IIII at 201e initial' => ['IIII', 10.0, 2010.0, 'initial', 15.0],
            'IIII at 200e in_use' => ['IIII', 10.0, 2000.0, 'in_use', 20.0],
        ];
    }

    public function test_conforming_at_exact_boundary(): void
    {
        // Error exactly equal to EMA should conform
        $ema = EmaCalculator::calculate('III', 1.0, 500.0, 'initial'); // 0.5
        $this->assertTrue(EmaCalculator::isConforming(0.5, $ema));
        $this->assertTrue(EmaCalculator::isConforming(-0.5, $ema));
    }

    public function test_non_conforming_just_over_boundary(): void
    {
        $ema = EmaCalculator::calculate('III', 1.0, 500.0, 'initial'); // 0.5
        $this->assertFalse(EmaCalculator::isConforming(0.500001, $ema));
    }

    public function test_invalid_class_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmaCalculator::calculate('V', 1.0, 100.0);
    }

    public function test_zero_e_value_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmaCalculator::calculate('III', 0.0, 100.0);
    }
}
