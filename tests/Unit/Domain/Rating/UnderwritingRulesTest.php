<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Rating;

use App\Domain\Rating\DTO\Driver;
use App\Domain\Rating\DTO\Risk;
use App\Domain\Rating\Rules\UnderwritingRules;
use PHPUnit\Framework\TestCase;

final class UnderwritingRulesTest extends TestCase
{
    private UnderwritingRules $rules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules = new UnderwritingRules();
    }

    public function test_three_fault_claims_are_declined(): void
    {
        $risk = $this->risk(
            faultClaims: 3
        );

        $result = $this->rules->evaluate($risk);

        self::assertSame('DECLINED', $result['decision']);
        self::assertContains('UW-D03', $result['codes']);
    }

    public function test_dr10_is_referred(): void
    {
        $risk = $this->risk(
            convictions: ['DR10']
        );

        $result = $this->rules->evaluate($risk);

        self::assertSame('REFERRED', $result['decision']);
        self::assertContains('UW-R02', $result['codes']);
    }

    public function test_mileage_above_40000_is_referred(): void
    {
        $risk = $this->risk(
            annualMileage: 40_001
        );

        $result = $this->rules->evaluate($risk);

        self::assertSame('REFERRED', $result['decision']);
        self::assertContains('UW-R07', $result['codes']);
    }

    public function test_ten_penalty_points_are_declined(): void
    {
        $risk = $this->risk(
            penaltyPoints: 10
        );

        $result = $this->rules->evaluate($risk);

        self::assertSame('DECLINED', $result['decision']);
        self::assertContains('UW-D04', $result['codes']);
    }

    public function test_licence_less_than_six_months_is_declined(): void
    {
        $risk = $this->risk(
            licenceMonths: 5
        );

        $result = $this->rules->evaluate($risk);

        self::assertSame('DECLINED', $result['decision']);
        self::assertContains('UW-D02', $result['codes']);
    }

    public function test_seventeen_year_old_driver_is_allowed_by_age_rule(): void
    {
        $risk = $this->risk(
            age: 17
        );

        $result = $this->rules->evaluate($risk);

        self::assertSame('QUOTE', $result['decision']);
    }

    public function test_six_months_licence_is_not_declined_by_d02(): void
    {
        $risk = $this->risk(
            licenceMonths: 6
        );

        $result = $this->rules->evaluate($risk);

        self::assertNotContains('UW-D02', $result['codes']);
    }

    public function test_decline_takes_precedence_over_referral(): void
    {
        $risk = $this->risk(
            faultClaims: 3,
            convictions: ['DR10']
        );

        $result = $this->rules->evaluate($risk);

        self::assertSame('DECLINED', $result['decision']);
        self::assertContains('UW-D03', $result['codes']);
        self::assertNotEmpty($result['codes']);
    }

    private function risk(
        int $age = 35,
        int $licenceMonths = 120,
        int $faultClaims = 0,
        int $penaltyPoints = 0,
        int $annualMileage = 10_000,
        array $convictions = [],
    ): Risk {
        return new Risk(
            vehicleGroup: 20,
            coverType: 'comprehensive',
            drivers: [
                new Driver(
                    age: $age,
                    licenceMonths: $licenceMonths,
                    convictions: $convictions,
                ),
            ],
            faultClaims: $faultClaims,
            penaltyPoints: $penaltyPoints,
            annualMileage: $annualMileage,
            postcode: 'BS7 9JX',
            classOfUse: 'sdp_commuting',
            voluntaryExcess: 250,
            ncdYears: 6,
        );
    }
}