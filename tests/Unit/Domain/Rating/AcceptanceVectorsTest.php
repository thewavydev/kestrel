<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Rating;

use App\Domain\Rating\DTO\Driver;
use App\Domain\Rating\DTO\Risk;
use App\Domain\Rating\Engine\RatingBasis;
use App\Domain\Rating\Engine\RatingEngine;
use PHPUnit\Framework\TestCase;

final class AcceptanceVectorsTest extends TestCase
{
    private RatingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RatingEngine(
            RatingBasis::v1()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AT-01
    |--------------------------------------------------------------------------
    |
    | Standard risk, annual payment, one add-on.
    |
    | Expected:
    | Net premium:       £222.38
    | Add-ons:           £39.00
    | Taxable amount:    £261.38
    | IPT:               £31.37
    | Administration:    £35.00
    | Total:             £327.75
    |
    */

    public function test_at_01_standard_risk(): void
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
            addOns: [
                'roadside',
            ],
        );

        $result = $this->engine->rate($risk);

        self::assertSame(
            'QUOTE',
            $result->decision
        );

        self::assertSame(
            '222.38',
            $result->netPremium
        );

        self::assertSame(
            '39.00',
            $result->addOns
        );

        self::assertSame(
            '261.38',
            $result->taxableAmount
        );

        self::assertSame(
            '31.37',
            $result->ipt
        );

        self::assertSame(
            '0.00',
            $result->ncdProtectionFee
        );

        self::assertSame(
            '35.00',
            $result->administrationFee
        );

        self::assertSame(
            '327.75',
            $result->total
        );

        self::assertSame(
            '250.00',
            $result->compulsoryExcess
        );

        self::assertSame(
            '250.00',
            $result->voluntaryExcess
        );

        self::assertSame(
            '500.00',
            $result->totalExcess
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AT-02
    |--------------------------------------------------------------------------
    |
    | Young driver, TPFT, penalty points, inner-urban postcode.
    |
    | Factor chain:
    |
    | 480
    | × 0.92
    | × 2.75
    | × 1.20
    | × 1.00
    | × 1.05
    | × 1.00
    | × 1.45
    | × 1.00
    | × 0.88
    | × 0.70
    |
    | = 1366.7246208
    |
    | Expected:
    | Net premium:       £1,366.72
    | Add-ons:           £0.00
    | Taxable amount:    £1,366.72
    | IPT:               £164.01
    | Administration:    £35.00
    | Total:             £1,565.73
    |
    */

    public function test_at_02_young_driver_tpft(): void
    {
        $risk = new Risk(
            vehicleGroup: 9,
            coverType: 'tpft',

            drivers: [
                new Driver(
                    age: 19,
                    licenceMonths: 12,
                ),
            ],

            faultClaims: 0,
            penaltyPoints: 3,
            annualMileage: 6000,
            postcode: 'M14 5TP',
            classOfUse: 'sdp',
            voluntaryExcess: 500,
            ncdYears: 1,
            ncdProtected: false,
            addOns: [],
        );

        $result = $this->engine->rate($risk);

        self::assertSame(
            'QUOTE',
            $result->decision
        );

        self::assertSame(
            '1366.72',
            $result->netPremium
        );

        self::assertSame(
            '0.00',
            $result->addOns
        );

        self::assertSame(
            '1366.72',
            $result->taxableAmount
        );

        self::assertSame(
            '164.01',
            $result->ipt
        );

        self::assertSame(
            '35.00',
            $result->administrationFee
        );

        self::assertSame(
            '1565.73',
            $result->total
        );

        self::assertSame(
            '500.00',
            $result->compulsoryExcess
        );

        self::assertSame(
            '500.00',
            $result->voluntaryExcess
        );

        self::assertSame(
            '1000.00',
            $result->totalExcess
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AT-03
    |--------------------------------------------------------------------------
    |
    | Minimum premium floor engages.
    | NCD protection is selected.
    |
    | Premium before floor: £81.08
    | Minimum premium:      £180.00
    | NCD protection:       £7.20
    | Taxable amount:       £187.20
    | IPT:                   £22.46
    | Administration:        £35.00
    | Total:                 £244.66
    |
    */

    public function test_at_03_minimum_premium_and_ncd_protection(): void
    {
        $risk = new Risk(
            vehicleGroup: 3,
            coverType: 'comprehensive',

            drivers: [
                new Driver(
                    age: 58,
                    licenceMonths: 456,
                ),
            ],

            faultClaims: 0,
            penaltyPoints: 0,
            annualMileage: 4000,
            postcode: 'TR11 2QG',
            classOfUse: 'sdp',
            voluntaryExcess: 1000,
            ncdYears: 9,
            ncdProtected: true,
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

        self::assertSame(
            '0.00',
            $result->addOns
        );

        self::assertSame(
            '7.20',
            $result->ncdProtectionFee
        );

        self::assertSame(
            '187.20',
            $result->taxableAmount
        );

        self::assertSame(
            '22.46',
            $result->ipt
        );

        self::assertSame(
            '35.00',
            $result->administrationFee
        );

        self::assertSame(
            '244.66',
            $result->total
        );

        self::assertSame(
            '250.00',
            $result->compulsoryExcess
        );

        self::assertSame(
            '1000.00',
            $result->voluntaryExcess
        );

        self::assertSame(
            '1250.00',
            $result->totalExcess
        );

        $minimumPremiumLine = $this->findBreakdownLine(
            $result->breakdown,
            'Minimum premium floor'
        );

        self::assertNotNull(
            $minimumPremiumLine
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AT-04
    |--------------------------------------------------------------------------
    |
    | Three fault claims in five years.
    |
    | Expected:
    | DECLINED
    | UW-D03
    |
    | No premium must be returned.
    |
    */

    public function test_at_04_three_fault_claims_are_declined_without_price(): void
    {
        $risk = new Risk(
            vehicleGroup: 15,
            coverType: 'comprehensive',

            drivers: [
                new Driver(
                    age: 41,
                    licenceMonths: 240,
                ),
            ],

            faultClaims: 3,
            penaltyPoints: 0,
            annualMileage: 10000,
            postcode: 'BS7 9JX',
            classOfUse: 'sdp',
            voluntaryExcess: 250,
            ncdYears: 5,
            ncdProtected: false,
            addOns: [],
        );

        $result = $this->engine->rate($risk);

        self::assertSame(
            'DECLINED',
            $result->decision
        );

        self::assertContains(
            'UW-D03',
            $result->reasonCodes
        );

        self::assertNull(
            $result->netPremium
        );

        self::assertNull(
            $result->ncdProtectionFee
        );

        self::assertNull(
            $result->addOns
        );

        self::assertNull(
            $result->taxableAmount
        );

        self::assertNull(
            $result->ipt
        );

        self::assertNull(
            $result->administrationFee
        );

        self::assertNull(
            $result->total
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AT-05
    |--------------------------------------------------------------------------
    |
    | Six points including DR10 conviction.
    |
    | Expected:
    | REFERRED
    | UW-R02
    |
    | No premium must be returned.
    |
    */

    public function test_at_05_dr10_is_referred_without_price(): void
    {
        $risk = new Risk(
            vehicleGroup: 25,
            coverType: 'comprehensive',

            drivers: [
                new Driver(
                    age: 46,
                    licenceMonths: 240,
                    convictions: [
                        'DR10',
                    ],
                ),
            ],

            faultClaims: 0,
            penaltyPoints: 6,
            annualMileage: 10000,
            postcode: 'BS7 9JX',
            classOfUse: 'sdp',
            voluntaryExcess: 250,
            ncdYears: 5,
            ncdProtected: false,
            addOns: [],
        );

        $result = $this->engine->rate($risk);

        self::assertSame(
            'REFERRED',
            $result->decision
        );

        self::assertContains(
            'UW-R02',
            $result->reasonCodes
        );

        /*No customer-facing pricing.*/
        self::assertNull(
            $result->netPremium
        );

        self::assertNull(
            $result->ncdProtectionFee
        );

        self::assertNull(
            $result->addOns
        );

        self::assertNull(
            $result->taxableAmount
        );

        self::assertNull(
            $result->ipt
        );

        self::assertNull(
            $result->administrationFee
        );

        self::assertNull(
            $result->total
        );
    }

    /*
        --------------------------------------------------------------------------
         AT-06
        ------------    --------------------------------------------------------------

         Mid-term adjustment:

         Day 120 of 365
         Days remaining = 245

         Original premium:
         £222.38

         Refund unused original premium:
         £222.38 × 245 / 365 = £149.27

         Amended risk premium:
         £310.00 × 245 / 365 = £208.08

         Additional premium:
         £208.08 - £149.27 = £58.81

         IPT:
         £58.81 × 12% = £7.06

         Adjustment fee:
         £25.00

         Total:
         £90.87

    */

    public function test_at_06_mid_term_vehicle_change(): void
    {
        $result = $this->engine->calculateMidTermAdjustment(
            originalNetPremium: '222.38',
            amendedAnnualPremium: '310.00',
            dayOfAdjustment: 120,
            adjustmentFee: '25.00',
            iptRate: '0.12',
        );

        self::assertSame(
            '149.27',
            $result->unusedPremiumRefund
        );

        self::assertSame(
            '208.08',
            $result->amendedRiskPremium
        );

        self::assertSame(
            '58.81',
            $result->additionalPremium
        );

        self::assertSame(
            '7.06',
            $result->ipt
        );

        self::assertSame(
            '25.00',
            $result->adjustmentFee
        );

        self::assertSame(
            '90.87',
            $result->totalCharged
        );
    }


    /*
    |--------------------------------------------------------------------------
    | AT-07
    |--------------------------------------------------------------------------
    |
    | Cancellation on day 200 of 365.
    |
    | Days remaining = 165
    |
    | Premium refund:
    | £222.38 × 165 / 365 = £100.53
    |
    | Add-on refund:
    | £39.00 × 165 / 365 = £17.63
    |
    | IPT refund:
    | (£100.53 + £17.63) × 12% = £14.18
    |
    | Cancellation fee:
    | £45.00
    |
    | Net refund:
    | £100.53 + £17.63 + £14.18 - £45.00
    | = £87.34
    |
    | Administration fee remains retained.
    |
    */

    public function test_at_07_cancellation_no_fault_claim(): void
    {
        $result = $this->engine->calculateCancellation(
            netPremium: '222.38',
            addOns: '39.00',
            dayOfCancellation: 200,
            cancellationFee: '45.00',
            administrationFee: '35.00',
            iptRate: '0.12',
            hasFaultClaim: false,
        );

        self::assertSame(
            '100.53',
            $result->premiumRefund
        );

        self::assertSame(
            '17.63',
            $result->addOnRefund
        );

        self::assertSame(
            '14.18',
            $result->iptRefund
        );

        self::assertSame(
            '45.00',
            $result->cancellationFee
        );

        self::assertSame(
            '87.34',
            $result->netRefund
        );

        self::assertSame(
            '35.00',
            $result->administrationFeeRetained
        );
    }


    /*
    |--------------------------------------------------------------------------
    | AT-08
    |--------------------------------------------------------------------------
    |
    | Renewal pricing:
    |
    | Technical renewal price:
    | £268.40
    |
    | Equivalent new business price:
    | £241.90
    |
    | Renewal cannot exceed equivalent
    | new business price.
    |
    | Therefore:
    | £241.90
    |
    */

    public function test_at_08_renewal_price_is_capped_at_new_business_price(): void
    {
        $result = $this->engine->calculateRenewalPrice(
            technicalRenewalPrice: '268.40',
            equivalentNewBusinessPrice: '241.90',
        );

        self::assertSame(
            '241.90',
            $result->priceToOffer
        );

        self::assertTrue(
            $result->capApplied
        );

        self::assertSame(
            '268.40',
            $result->technicalRenewalPrice
        );

        self::assertSame(
            '241.90',
            $result->equivalentNewBusinessPrice
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function findBreakdownLine(
        array $breakdown,
        string $name
    ): mixed {
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