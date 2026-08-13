<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Rating;

use App\Domain\Rating\DTO\PaymentPlan;
use App\Domain\Rating\Engine\RatingBasis;
use App\Domain\Rating\Engine\RatingEngine;
use PHPUnit\Framework\TestCase;

final class InstalmentPlanTest extends TestCase
{
    private RatingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RatingEngine(
            RatingBasis::v1()
        );
    }

    public function test_at_01_instalment_calculation(): void
    {
        $plan = $this->engine->instalmentPlan('327.75');

        self::assertInstanceOf(
            PaymentPlan::class,
            $plan
        );

        self::assertSame(
            '65.55',
            $plan->deposit
        );

        self::assertSame(
            '32.78',
            $plan->creditCharge
        );

        self::assertSame(
            '294.98',
            $plan->financedAmount
        );

        self::assertCount(
            11,
            $plan->instalments
        );

        $instalmentTotal = '0.00';

        foreach ($plan->instalments as $instalment) {
            $instalmentTotal = bcadd(
                $instalmentTotal,
                $instalment,
                2
            );
        }

        self::assertSame(
            $plan->financedAmount,
            $instalmentTotal
        );

        self::assertSame(
            '26.88',
            $plan->instalments[0]
        );

        self::assertSame(
            '26.81',
            $plan->instalments[1]
        );

        self::assertSame(
            '26.81',
            $plan->instalments[10]
        );

        self::assertSame(
            '360.53',
            $plan->totalPayable
        );
    }

    public function test_residual_is_always_allocated_to_instalments(): void
    {
        $plan = $this->engine->instalmentPlan('100.01');

        $instalmentTotal = '0.00';

        foreach ($plan->instalments as $instalment) {
            $instalmentTotal = bcadd(
                $instalmentTotal,
                $instalment,
                2
            );
        }

        self::assertSame(
            $plan->financedAmount,
            $instalmentTotal
        );

        self::assertGreaterThan(
            0,
            (float) $plan->deposit
        );

        self::assertGreaterThan(
            0,
            (float) $instalmentTotal
        );
    }
}