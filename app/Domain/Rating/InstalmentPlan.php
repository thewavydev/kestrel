<?php

declare(strict_types=1);

namespace App\Domain\Rating;

final class InstalmentPlan
{
    /**
     * @return array{
     *     annual_total_pence: int,
     *     deposit_pence: int,
     *     balance_pence: int,
     *     credit_charge_pence: int,
     *     financed_amount_pence: int,
     *     instalments: array<int, int>,
     *     total_by_instalments_pence: int
     * }
     */
    public static function calculate(int $annualTotalPence): array
    {
        $deposit = self::roundHalfUp(
            $annualTotalPence * 20,
            100
        );

        $balance = $annualTotalPence - $deposit;

        $creditCharge = self::roundHalfUp(
            $balance * 125,
            1000
        );

        $financedAmount = $balance + $creditCharge;

        $baseInstalment = intdiv(
            $financedAmount,
            11
        );

        $residual = $financedAmount - ($baseInstalment * 11);

        $instalments = array_fill(0, 11, $baseInstalment);

        // Explicitly allocate residual pence to first instalment.
        $instalments[0] += $residual;

        return [
            'annual_total_pence' => $annualTotalPence,
            'deposit_pence' => $deposit,
            'balance_pence' => $balance,
            'credit_charge_pence' => $creditCharge,
            'financed_amount_pence' => $financedAmount,
            'instalments' => $instalments,
            'total_by_instalments_pence' =>
                $deposit + array_sum($instalments),
        ];
    }

    private static function roundHalfUp(
        int $numerator,
        int $denominator
    ): int {
        return intdiv(
            $numerator + intdiv($denominator, 2),
            $denominator
        );
    }
}