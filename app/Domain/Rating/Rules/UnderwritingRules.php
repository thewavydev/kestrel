<?php

declare(strict_types=1);

namespace App\Domain\Rating\Rules;

use App\Domain\Rating\DTO\Risk;

final class UnderwritingRules
{
    /**
     * @return array{
     *     decision: 'QUOTE'|'REFERRED'|'DECLINED',
     *     codes: string[]
     * }
     */
    public function evaluate(Risk $risk): array
    {
        $declines = [];
        $referrals = [];

        $youngestDriver = $this->youngestDriver($risk);

        if ($youngestDriver->age < 17) {
            $declines[] = 'UW-D01';
        }

        if ($youngestDriver->licenceMonths < 6) {
            $declines[] = 'UW-D02';
        }

        if ($risk->faultClaims >= 3) {
            $declines[] = 'UW-D03';
        }

        if ($risk->penaltyPoints >= 10) {
            $declines[] = 'UW-D04';
        }

        if ($risk->vehicleGroup > 50) {
            $declines[] = 'UW-D05';
        }

        foreach ($risk->drivers as $driver) {
            foreach ($driver->convictions as $conviction) {
                if ($this->isReferralConviction($conviction)) {
                    $referrals[] = 'UW-R02';
                    break 2;
                }
            }
        }

        if ($risk->annualMileage > 40_000) {
            $referrals[] = 'UW-R07';
        }

        $declines = array_values(array_unique($declines));
        $referrals = array_values(array_unique($referrals));

        if ($declines !== []) {
            return [
                'decision' => 'DECLINED',
                'codes' => $declines,
            ];
        }

        if ($referrals !== []) {
            return [
                'decision' => 'REFERRED',
                'codes' => $referrals,
            ];
        }

        return [
            'decision' => 'QUOTE',
            'codes' => [],
        ];
    }

    private function isReferralConviction(string $code): bool
    {
        $code = strtoupper(trim($code));

        if (str_starts_with($code, 'DR')) {
            return true;
        }

        if ($code === 'IN10') {
            return true;
        }

        if (preg_match('/^CD(40|41|42|43|44|45|46|47|48|49|50|51|52|53|54|55|56|57|58|59|60|61|62|63|64|65|66|67|68|69|70)$/', $code)) {
            return true;
        }

        if (str_starts_with($code, 'DD')) {
            return true;
        }

        return $code === 'UT50';
    }

    private function youngestDriver(Risk $risk)
    {
        return array_reduce(
            $risk->drivers,
            static function ($youngest, $driver) {
                if ($youngest === null || $driver->age < $youngest->age) {
                    return $driver;
                }

                return $youngest;
            }
        );
    }
}