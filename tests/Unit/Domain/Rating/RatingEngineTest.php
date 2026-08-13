<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Rating;

use App\Domain\Rating\DTO\BreakdownLine;
use App\Domain\Rating\DTO\Driver;
use App\Domain\Rating\DTO\Risk;
use App\Domain\Rating\Engine\RatingBasis;
use App\Domain\Rating\Engine\RatingEngine;
use PHPUnit\Framework\TestCase;

final class RatingEngineTest extends TestCase
{
    private RatingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RatingEngine(
            RatingBasis::v1()
        );
    }

    public function test_at_01_standard_risk_produces_expected_premium(): void
    {
        $risk = new Risk(
            vehicleGroup: 20,
            coverType: 'comprehensive',
            drivers: [
                new Driver(
                    age: 34,
                    licenceMonths: 180,
                ),
            ],
            faultClaims: 0,
            penaltyPoints: 0,
            annualMileage: 9000,
            postcode: 'BS7 9JX',
            classOfUse: 'sdp_commuting',
            voluntaryExcess: 250,
            ncdYears: 6,
            ncdProtected: false,
            addOns: ['roadside'],
        );

        $result = $this->engine->rate($risk);

        self::assertSame('QUOTE', $result->decision);
        self::assertSame([], $result->reasonCodes);

        self::assertSame('222.38', $result->netPremium);
        self::assertSame('39.00', $result->addOns);
        self::assertSame('261.38', $result->taxableAmount);
        self::assertSame('31.37', $result->ipt);
        self::assertSame('35.00', $result->administrationFee);
        self::assertSame('327.75', $result->total);

        self::assertSame('250.00', $result->compulsoryExcess);
        self::assertSame('250.00', $result->voluntaryExcess);
        self::assertSame('500.00', $result->totalExcess);
    }

    public function test_three_fault_claims_return_no_price(): void
    {
        $risk = $this->risk(
            faultClaims: 3
        );

        $result = $this->engine->rate($risk);

        self::assertSame('DECLINED', $result->decision);
        self::assertContains('UW-D03', $result->reasonCodes);

        self::assertNull($result->netPremium);
        self::assertNull($result->total);
    }

    public function test_quote_records_rating_basis_version(): void
    {
        $risk = $this->risk();

        $result = $this->engine->rate($risk);

        self::assertSame(
            'v1',
            $result->ratingBasisVersion
        );
    }

    public function test_v1_quote_can_be_reproduced_after_v2_is_created(): void
    {
        $risk = $this->risk();

        $v1 = RatingBasis::v1();

        $engineV1 = new RatingEngine($v1);

        $original = $engineV1->rate($risk);

        /*
         * Simulate publishing a new rating basis.
         *
         * v1 remains unchanged and can still be used
         * to reproduce the original quote.
         */
        $v2 = new RatingBasis(
            version: 'v2',

            baseRates: [
                [1, 10, '500.00'],
                [11, 20, '700.00'],
                [21, 30, '950.00'],
                [31, 40, '1400.00'],
                [41, 50, '2000.00'],
            ],

            coverFactors: $v1->coverFactors,
            classOfUseFactors: $v1->classOfUseFactors,
            voluntaryExcessFactors: $v1->voluntaryExcessFactors,
            ageFactors: $v1->ageFactors,
            licenceFactors: $v1->licenceFactors,
            claimFactors: $v1->claimFactors,
            pointFactors: $v1->pointFactors,
            mileageFactors: $v1->mileageFactors,
            ncdDiscounts: $v1->ncdDiscounts,
            postcodeFactors: $v1->postcodeFactors,
            postcodeBands: $v1->postcodeBands,
            addOns: $v1->addOns,
            minimumPremium: $v1->minimumPremium,
            iptRate: $v1->iptRate,
            administrationFee: $v1->administrationFee,
        );

        $engineV2 = new RatingEngine($v2);

        $newQuote = $engineV2->rate($risk);

        // Re-rate the original risk using the original v1 basis.
        $reproduced = $engineV1->rate($risk);

        self::assertSame(
            'v1',
            $reproduced->ratingBasisVersion
        );

        self::assertSame(
            $original->total,
            $reproduced->total
        );

        self::assertNotSame(
            $original->total,
            $newQuote->total
        );
    }

    public function test_instalments_sum_exactly_to_financed_amount(): void
    {
        $plan = $this->engine->instalmentPlan(
            '327.75'
        );

        $sum = '0.00';

        foreach ($plan->instalments as $instalment) {
            $sum = bcadd(
                $sum,
                $instalment,
                2
            );
        }

        self::assertSame(
            $plan->financedAmount,
            $sum
        );

        self::assertCount(
            11,
            $plan->instalments
        );

        self::assertSame(
            $plan->totalPayable,
            bcadd(
                $plan->deposit,
                $plan->financedAmount,
                2
            )
        );
    }

    public function test_dr10_returns_refer_without_price(): void
    {
        $risk = $this->risk(
            convictions: ['DR10']
        );

        $result = $this->engine->rate($risk);

        self::assertSame('REFERRED', $result->decision);
        self::assertContains('UW-R02', $result->reasonCodes);

        self::assertNull($result->netPremium);
        self::assertNull($result->total);
    }

    public function test_age_25_uses_25_to_29_factor(): void
    {
        $risk = $this->risk(
            age: 25
        );

        $result = $this->engine->rate($risk);

        self::assertSame('QUOTE', $result->decision);

        $ageLine = $this->findBreakdownLine(
            $result->breakdown,
            'Age of youngest driver'
        );

        self::assertSame(
            '1.35',
            $ageLine->multiplier
        );
    }

    public function test_mileage_10000_uses_5001_to_10000_factor(): void
    {
        $risk = $this->risk(
            annualMileage: 10_000
        );

        $result = $this->engine->rate($risk);

        $mileageLine = $this->findBreakdownLine(
            $result->breakdown,
            'Annual mileage'
        );

        self::assertSame(
            '1.00',
            $mileageLine->multiplier
        );
    }

    public function test_licence_six_years_uses_six_plus_factor(): void
    {
        $risk = $this->risk(
            licenceMonths: 72
        );

        $result = $this->engine->rate($risk);

        $licenceLine = $this->findBreakdownLine(
            $result->breakdown,
            'Licence held'
        );

        self::assertSame(
            '1.00',
            $licenceLine->multiplier
        );
    }

    public function test_ncd_protection_costs_four_percent_of_net_premium(): void
    {
        $risk = $this->risk(
            ncdProtected: true
        );

        $result = $this->engine->rate($risk);

        self::assertSame(
            '222.38',
            $result->netPremium
        );

        self::assertSame(
            '8.90',
            $result->ncdProtectionFee
        );
    }

    public function test_add_ons_are_added_to_taxable_amount(): void
    {
        $risk = $this->risk(
            addOns: [
                'roadside',
                'legal_expenses',
                'courtesy_car',
                'key_protection',
                'excess_protection',
            ]
        );

        $result = $this->engine->rate($risk);

        self::assertSame(
            '139.00',
            $result->addOns
        );

        self::assertSame(
            '361.38',
            $result->taxableAmount
        );
    }

    

    public function test_minimum_premium_is_180_pounds(): void
    {
        $risk = $this->risk(
            vehicleGroup: 1,
            age: 40,
            licenceMonths: 240,
            annualMileage: 5_000,
            postcode: 'TR1 1AA',
            classOfUse: 'sdp',
            voluntaryExcess: 1000,
            ncdYears: 9,
            addOns: [],
        );

        $result = $this->engine->rate($risk);

        self::assertSame(
            'QUOTE',
            $result->decision
        );

        self::assertSame(
            '180.00',
            $result->netPremium
        );

        $floorLine = $this->findBreakdownLine(
            $result->breakdown,
            'Minimum premium floor'
        );

        self::assertSame(
            '£180.00',
            $floorLine->value
        );

        self::assertSame(
            '180.00',
            $floorLine->subtotal
        );
    }

    private function risk(
        int $vehicleGroup = 20,
        int $age = 34,
        int $licenceMonths = 180,
        int $faultClaims = 0,
        int $penaltyPoints = 0,
        int $annualMileage = 9000,
        string $postcode = 'BS7 9JX',
        string $classOfUse = 'sdp_commuting',
        int $voluntaryExcess = 250,
        int $ncdYears = 6,
        bool $ncdProtected = false,
        array $convictions = [],
        array $addOns = ['roadside'],
    ): Risk {
        return new Risk(
            vehicleGroup: $vehicleGroup,
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
            postcode: $postcode,
            classOfUse: $classOfUse,
            voluntaryExcess: $voluntaryExcess,
            ncdYears: $ncdYears,
            ncdProtected: $ncdProtected,
            addOns: $addOns,
        );
    }

    private function findBreakdownLine(
        array $breakdown,
        string $name
    ): BreakdownLine {
        foreach ($breakdown as $line) {
            if ($line->name === $name) {
                return $line;
            }
        }

        self::fail(
            "Breakdown line [{$name}] was not found."
        );
    }
}