<?php

declare(strict_types=1);

namespace App\Domain\Rating\Engine;

use App\Domain\Rating\DTO\BreakdownLine;
use App\Domain\Rating\DTO\CancellationResult;
use App\Domain\Rating\DTO\MidTermAdjustmentResult;
use App\Domain\Rating\DTO\PaymentPlan;
use App\Domain\Rating\DTO\RatingResult;
use App\Domain\Rating\DTO\RenewalPriceResult;
use App\Domain\Rating\DTO\Risk;
use App\Domain\Rating\Rules\UnderwritingRules;

final class RatingEngine
{
    private RatingBasis $basis;

    private UnderwritingRules $underwritingRules;

    public function __construct(
        RatingBasis $basis,
        ?UnderwritingRules $underwritingRules = null,
    ) {
        $this->basis = $basis;

        $this->underwritingRules =
            $underwritingRules ?? new UnderwritingRules();
    }

    /**
     * Rate a risk after applying underwriting rules.
     *
     * Underwriting is always performed before pricing.
     * Declined and referred risks receive no premium.
     */
    public function rate(Risk $risk): RatingResult
    {
        $underwriting = $this->underwritingRules->evaluate($risk);

        if ($underwriting['decision'] === 'DECLINED') {
            return new RatingResult(
                decision: 'DECLINED',
                reasonCodes: $underwriting['codes'],
            );
        }

        if ($underwriting['decision'] === 'REFERRED') {
            return new RatingResult(
                decision: 'REFERRED',
                reasonCodes: $underwriting['codes'],
            );
        }

        return $this->calculate($risk);
    }

    /**
     * Return the version of the rating basis used by this engine.
     */
    public function basisVersion(): string
    {
        return $this->basis->version;
    }

    /**
     * Calculate the premium for an accepted risk.
     */
    private function calculate(Risk $risk): RatingResult
    {
        $youngestDriver = $this->youngestDriver($risk);

        $breakdown = [];

        $baseRate = $this->baseRate(
            $risk->vehicleGroup
        );

        $subtotal = $baseRate;

        $breakdown[] = new BreakdownLine(
            name: 'Base rate',
            value: 'Vehicle group ' . $risk->vehicleGroup,
            multiplier: null,
            subtotal: $this->money($subtotal),
        );

        $coverFactor = $this->basis->coverFactors[
            $risk->coverType
        ];

        $subtotal = $this->multiply(
            $subtotal,
            $coverFactor
        );

        $breakdown[] = new BreakdownLine(
            name: 'Cover type',
            value: $risk->coverType,
            multiplier: $coverFactor,
            subtotal: $this->money($subtotal),
        );

        $ageFactor = $this->rangeFactor(
            $this->basis->ageFactors,
            $youngestDriver->age
        );

        $subtotal = $this->multiply(
            $subtotal,
            $ageFactor
        );

        $breakdown[] = new BreakdownLine(
            name: 'Age of youngest driver',
            value: (string) $youngestDriver->age,
            multiplier: $ageFactor,
            subtotal: $this->money($subtotal),
        );

        $licenceYears = intdiv(
            $youngestDriver->licenceMonths,
            12
        );

        $licenceFactor = $this->rangeFactor(
            $this->basis->licenceFactors,
            $licenceYears
        );

        $subtotal = $this->multiply(
            $subtotal,
            $licenceFactor
        );

        $breakdown[] = new BreakdownLine(
            name: 'Licence held',
            value: $youngestDriver->licenceMonths . ' months',
            multiplier: $licenceFactor,
            subtotal: $this->money($subtotal),
        );

        $claimsFactor = $this->basis->claimFactors[
            $risk->faultClaims
        ];

        $subtotal = $this->multiply(
            $subtotal,
            $claimsFactor
        );

        $breakdown[] = new BreakdownLine(
            name: 'Fault claims',
            value: (string) $risk->faultClaims,
            multiplier: $claimsFactor,
            subtotal: $this->money($subtotal),
        );

        $pointsFactor = $this->rangeFactor(
            $this->basis->pointFactors,
            $risk->penaltyPoints
        );

        $subtotal = $this->multiply(
            $subtotal,
            $pointsFactor
        );

        $breakdown[] = new BreakdownLine(
            name: 'Penalty points',
            value: (string) $risk->penaltyPoints,
            multiplier: $pointsFactor,
            subtotal: $this->money($subtotal),
        );

        $convictionsFactor = '1.00';

        $subtotal = $this->multiply(
            $subtotal,
            $convictionsFactor
        );

        $breakdown[] = new BreakdownLine(
            name: 'Convictions',
            value: 'No referral conviction',
            multiplier: $convictionsFactor,
            subtotal: $this->money($subtotal),
        );

        $mileageFactor = $this->rangeFactor(
            $this->basis->mileageFactors,
            $risk->annualMileage
        );

        $subtotal = $this->multiply(
            $subtotal,
            $mileageFactor
        );

        $breakdown[] = new BreakdownLine(
            name: 'Annual mileage',
            value: number_format($risk->annualMileage),
            multiplier: $mileageFactor,
            subtotal: $this->money($subtotal),
        );

        $postcodeBand = $this->postcodeBand(
            $risk->postcode
        );

        $postcodeFactor = $this->basis->postcodeFactors[
            $postcodeBand
        ];

        $subtotal = $this->multiply(
            $subtotal,
            $postcodeFactor
        );

        $breakdown[] = new BreakdownLine(
            name: 'Postcode risk band',
            value: $postcodeBand,
            multiplier: $postcodeFactor,
            subtotal: $this->money($subtotal),
        );

        $classFactor = $this->basis->classOfUseFactors[
            $risk->classOfUse
        ];

        $subtotal = $this->multiply(
            $subtotal,
            $classFactor
        );

        $breakdown[] = new BreakdownLine(
            name: 'Class of use',
            value: $risk->classOfUse,
            multiplier: $classFactor,
            subtotal: $this->money($subtotal),
        );

        $excessFactor = $this->basis->voluntaryExcessFactors[
            $risk->voluntaryExcess
        ];

        $subtotal = $this->multiply(
            $subtotal,
            $excessFactor
        );

        $breakdown[] = new BreakdownLine(
            name: 'Voluntary excess',
            value: '£' . number_format(
                $risk->voluntaryExcess,
                2
            ),
            multiplier: $excessFactor,
            subtotal: $this->money($subtotal),
        );

        $ncdYears = min(
            $risk->ncdYears,
            9
        );

        $ncdDiscount = $this->basis->ncdDiscounts[
            $ncdYears
        ];

        $discountMultiplier = $this->subtract(
            '1.00',
            $ncdDiscount
        );

        $discountedPremium = $this->multiply(
            $subtotal,
            $discountMultiplier
        );

        $breakdown[] = new BreakdownLine(
            name: 'No Claims Discount',
            value: $ncdDiscount . ' discount',
            multiplier: $discountMultiplier,
            subtotal: $this->money($discountedPremium),
        );

        $minimumApplied = ! $this->greaterThan(
            $discountedPremium,
            $this->basis->minimumPremium
        );

        $premiumBeforeFloor = $minimumApplied
            ? $this->basis->minimumPremium
            : $discountedPremium;

        $netPremium = $this->roundHalfUp(
            $premiumBeforeFloor
        );

        if ($minimumApplied) {
            $breakdown[] = new BreakdownLine(
                name: 'Minimum premium floor',
                value: '£' . $this->basis->minimumPremium,
                multiplier: null,
                subtotal: $netPremium,
            );
        }

        $ncdProtectionFee = '0.00';

        if ($risk->ncdProtected) {
            $ncdProtectionFee = $this->roundHalfUp(
                $this->multiply(
                    $netPremium,
                    '0.04'
                )
            );
        }

        $addOnsTotal = '0.00';

        foreach ($risk->addOns as $addOn) {
            $addOnsTotal = $this->add(
                $addOnsTotal,
                $this->basis->addOns[$addOn]
            );
        }

        $addOnsTotal = $this->money(
            $addOnsTotal
        );

        $taxableAmount = $this->add(
            $this->add(
                $netPremium,
                $ncdProtectionFee
            ),
            $addOnsTotal
        );

        $taxableAmount = $this->money(
            $taxableAmount
        );

        $ipt = $this->roundHalfUp(
            $this->multiply(
                $taxableAmount,
                $this->basis->iptRate
            )
        );

        $total = $this->add(
            $this->add(
                $taxableAmount,
                $ipt
            ),
            $this->basis->administrationFee
        );

        $total = $this->money(
            $total
        );

        $compulsoryExcess = $this->compulsoryExcess(
            $youngestDriver->age
        );

        $totalExcess =
            $compulsoryExcess +
            $risk->voluntaryExcess;

        return new RatingResult(
            decision: 'QUOTE',
            ratingBasisVersion: $this->basis->version,

            netPremium: $this->money(
                $netPremium
            ),

            ncdProtectionFee: $this->money(
                $ncdProtectionFee
            ),

            addOns: $this->money(
                $addOnsTotal
            ),

            taxableAmount: $this->money(
                $taxableAmount
            ),

            ipt: $this->money(
                $ipt
            ),

            administrationFee: $this->money(
                $this->basis->administrationFee
            ),

            total: $this->money(
                $total
            ),

            compulsoryExcess: number_format(
                $compulsoryExcess,
                2,
                '.',
                ''
            ),

            voluntaryExcess: number_format(
                $risk->voluntaryExcess,
                2,
                '.',
                ''
            ),

            totalExcess: number_format(
                $totalExcess,
                2,
                '.',
                ''
            ),

            breakdown: $breakdown,
        );
    }

    /**
     * Format a monetary decimal to exactly two decimal places.
     */
    private function money(string $value): string
    {
        return bcdiv(
            $value,
            '1',
            2
        );
    }

    /**
     * Find the base rate for a vehicle insurance group.
     */
    private function baseRate(
        int $vehicleGroup
    ): string {
        foreach (
            $this->basis->baseRates
            as [$min, $max, $rate]
        ) {
            if (
                $vehicleGroup >= $min &&
                $vehicleGroup <= $max
            ) {
                return $rate;
            }
        }

        throw new \InvalidArgumentException(
            'Vehicle insurance group must be between 1 and 50.'
        );
    }

    /**
     * Find a rating factor from a range-based table.
     */
    private function rangeFactor(
        array $table,
        int $value
    ): string {
        foreach (
            $table
            as [$min, $max, $factor]
        ) {
            if (
                $value >= $min &&
                $value <= $max
            ) {
                return $factor;
            }
        }

        throw new \InvalidArgumentException(
            "No rating factor exists for value {$value}."
        );
    }

    /**
     * Determine the postcode risk band.
     */
    private function postcodeBand(
        string $postcode
    ): string {
        $postcode = strtoupper(
            trim($postcode)
        );

        preg_match(
            '/^[A-Z]+/',
            $postcode,
            $matches
        );

        $outwardPrefix =
            $matches[0] ?? '';

        foreach (
            $this->basis->postcodeBands
            as $band => $prefixes
        ) {
            foreach ($prefixes as $prefix) {
                if (
                    str_starts_with(
                        $outwardPrefix,
                        $prefix
                    )
                ) {
                    return $band;
                }
            }
        }

        return 'C';
    }

    /**
     * Return the youngest driver on the risk.
     */
    private function youngestDriver(
        Risk $risk
    ) {
        return array_reduce(
            $risk->drivers,
            static function (
                $youngest,
                $driver
            ) {
                if (
                    $youngest === null ||
                    $driver->age < $youngest->age
                ) {
                    return $driver;
                }

                return $youngest;
            }
        );
    }

    /**
     * Determine compulsory excess based on driver age.
     */
    private function compulsoryExcess(
        int $age
    ): int {
        if (
            $age >= 17 &&
            $age <= 20
        ) {
            return 500;
        }

        if (
            $age >= 21 &&
            $age <= 24
        ) {
            return 350;
        }

        return 250;
    }

    /**
     * Multiply two decimal values using BCMath.
     */
    private function multiply(
        string $left,
        string $right
    ): string {
        return bcmul(
            $left,
            $right,
            12
        );
    }

    /**
     * Add two decimal values using BCMath.
     */
    private function add(
        string $left,
        string $right
    ): string {
        return bcadd(
            $left,
            $right,
            12
        );
    }

    /**
     * Subtract two decimal values using BCMath.
     */
    private function subtract(
        string $left,
        string $right
    ): string {
        return bcsub(
            $left,
            $right,
            12
        );
    }

    /**
     * Round a decimal value half-up to two decimal places.
     */
    private function roundHalfUp(
        string $value
    ): string {
        return bcdiv(
            bcadd(
                bcmul(
                    $value,
                    '100',
                    4
                ),
                '0.5',
                4
            ),
            '100',
            2
        );
    }

    /**
     * Determine whether the first value is greater than the second.
     */
    private function greaterThan(
        string $left,
        string $right
    ): bool {
        return bccomp(
            $left,
            $right,
            12
        ) === 1;
    }

    /**
     * Calculate the 20% deposit and 11-month instalment plan.
     *
     * The regular instalment is deliberately truncated to two
     * decimal places. Any remaining pence are explicitly allocated
     * to the first instalment so that all instalments reconcile
     * exactly to the financed amount.
     */
public function instalmentPlan(
    string $totalPayable
): PaymentPlan {
    $deposit = $this->roundHalfUp(
        $this->multiply(
            $totalPayable,
            '0.20'
        )
    );

    $balance = $this->subtract(
        $totalPayable,
        $deposit
    );

    $creditCharge = $this->roundHalfUp(
        $this->multiply(
            $balance,
            '0.125'
        )
    );

    $financedAmount = $this->add(
        $balance,
        $creditCharge
    );

    $regularInstalment = bcdiv(
        $financedAmount,
        '11',
        12
    );

    $regularInstalment = bcdiv(
        $regularInstalment,
        '1',
        2
    );

    $instalments = array_fill(
        0,
        11,
        $regularInstalment
    );

    $scheduledTotal = '0.00';

    foreach ($instalments as $instalment) {
        $scheduledTotal = $this->add(
            $scheduledTotal,
            $instalment
        );
    }

    $residual = $this->subtract(
        $financedAmount,
        $scheduledTotal
    );

    $residual = $this->roundHalfUp(
        $residual
    );

    $instalments[0] = $this->money(
        $this->add(
            $instalments[0],
            $residual
        )
    );

    foreach ($instalments as $index => $instalment) {
        $instalments[$index] = $this->money(
            $instalment
        );
    }

    $financedAmount = $this->money(
        $financedAmount
    );

    $totalPayableFromPlan = $this->money(
        $this->add(
            $deposit,
            $financedAmount
        )
    );

    return new PaymentPlan(
        deposit: $this->money(
            $deposit
        ),

        instalments: $instalments,

        financedAmount: $financedAmount,

        creditCharge: $this->money(
            $creditCharge
        ),

        totalPayable: $totalPayableFromPlan,
    );
}

    /**
     * Calculate a pro-rata mid-term adjustment.
     */
    public function calculateMidTermAdjustment(
        string $originalNetPremium,
        string $amendedAnnualPremium,
        int $dayOfAdjustment,
        string $adjustmentFee,
        string $iptRate,
    ): MidTermAdjustmentResult {
        $daysRemaining = 365 - $dayOfAdjustment;

        $unusedPremiumRefund = $this->roundHalfUp(
            bcdiv(
                bcmul(
                    $originalNetPremium,
                    (string) $daysRemaining,
                    10
                ),
                '365',
                10
            )
        );

        $amendedRiskPremium = $this->roundHalfUp(
            bcdiv(
                bcmul(
                    $amendedAnnualPremium,
                    (string) $daysRemaining,
                    10
                ),
                '365',
                10
            )
        );

        $additionalPremium = $this->money(
            $this->subtract(
                $amendedRiskPremium,
                $unusedPremiumRefund
            )
        );

        $ipt = $this->roundHalfUp(
            bcmul(
                $additionalPremium,
                $iptRate,
                10
            )
        );

        $totalCharged = $this->money(
            $this->add(
                $this->add(
                    $additionalPremium,
                    $ipt
                ),
                $adjustmentFee
            )
        );

        return new MidTermAdjustmentResult(
            unusedPremiumRefund: $unusedPremiumRefund,
            amendedRiskPremium: $amendedRiskPremium,
            additionalPremium: $additionalPremium,
            ipt: $ipt,
            adjustmentFee: $adjustmentFee,
            totalCharged: $totalCharged,
        );
    }

    /**
     * Calculate a cancellation refund.
     */
    public function calculateCancellation(
        string $netPremium,
        string $addOns,
        int $dayOfCancellation,
        string $cancellationFee,
        string $administrationFee,
        string $iptRate,
        bool $hasFaultClaim,
    ): CancellationResult {
        if ($hasFaultClaim) {
            return new CancellationResult(
                premiumRefund: '0.00',
                addOnRefund: '0.00',
                iptRefund: '0.00',
                cancellationFee: '0.00',
                netRefund: '0.00',
                administrationFeeRetained: $administrationFee,
            );
        }

        $daysRemaining = 365 - $dayOfCancellation;

        $premiumRefund = $this->roundHalfUp(
            bcdiv(
                bcmul(
                    $netPremium,
                    (string) $daysRemaining,
                    10
                ),
                '365',
                10
            )
        );

        $addOnRefund = $this->roundHalfUp(
            bcdiv(
                bcmul(
                    $addOns,
                    (string) $daysRemaining,
                    10
                ),
                '365',
                10
            )
        );

        $refundTaxableAmount = $this->add(
            $premiumRefund,
            $addOnRefund
        );

        $iptRefund = $this->roundHalfUp(
            $this->multiply(
                $refundTaxableAmount,
                $iptRate
            )
        );

        $netRefund = $this->money(
            $this->subtract(
                $this->add(
                    $refundTaxableAmount,
                    $iptRefund
                ),
                $cancellationFee
            )
        );

        return new CancellationResult(
            premiumRefund: $premiumRefund,
            addOnRefund: $addOnRefund,
            iptRefund: $iptRefund,
            cancellationFee: $cancellationFee,
            netRefund: $netRefund,
            administrationFeeRetained: $administrationFee,
        );
    }

    /**
     * Calculate the renewal price.
     *
     * The renewal price cannot exceed the equivalent
     * new-business price.
     */
    public function calculateRenewalPrice(
        string $technicalRenewalPrice,
        string $equivalentNewBusinessPrice,
    ): RenewalPriceResult {
        $capApplied = bccomp(
            $technicalRenewalPrice,
            $equivalentNewBusinessPrice,
            2
        ) > 0;

        $priceToOffer = $capApplied
            ? $equivalentNewBusinessPrice
            : $technicalRenewalPrice;

        return new RenewalPriceResult(
            technicalRenewalPrice: $technicalRenewalPrice,
            equivalentNewBusinessPrice: $equivalentNewBusinessPrice,
            priceToOffer: $priceToOffer,
            capApplied: $capApplied,
        );
    }
}