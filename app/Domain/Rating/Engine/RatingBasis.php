<?php

declare(strict_types=1);

namespace App\Domain\Rating\Engine;

final readonly class RatingBasis
{
    public function __construct(
        public string $version,

        public array $baseRates,
        public array $coverFactors,
        public array $classOfUseFactors,
        public array $voluntaryExcessFactors,
        public array $ageFactors,
        public array $licenceFactors,
        public array $claimFactors,
        public array $pointFactors,
        public array $mileageFactors,
        public array $ncdDiscounts,
        public array $postcodeFactors,
        public array $postcodeBands,
        public array $addOns,

        public string $minimumPremium,
        public string $iptRate,
        public string $administrationFee,
    ) {
    }

    public static function v1(): self
    {
        return new self(
            version: 'v1',

            baseRates: [
                [1, 10, '480.00'],
                [11, 20, '640.00'],
                [21, 30, '890.00'],
                [31, 40, '1280.00'],
                [41, 50, '1850.00'],
            ],

            coverFactors: [
                'comprehensive' => '1.00',
                'tpft' => '0.92',
                'tpo' => '0.86',
            ],

            classOfUseFactors: [
                'sdp' => '1.00',
                'sdp_commuting' => '1.10',
                'business_1' => '1.22',
                'business_2' => '1.45',
            ],

            voluntaryExcessFactors: [
                0 => '1.06',
                100 => '1.00',
                250 => '0.95',
                500 => '0.88',
                1000 => '0.80',
            ],

            ageFactors: [
                [17, 20, '2.75'],
                [21, 24, '1.90'],
                [25, 29, '1.35'],
                [30, 39, '1.00'],
                [40, 59, '0.92'],
                [60, 74, '0.98'],
                [75, 999, '1.30'],
            ],

            licenceFactors: [
                [0, 0, '1.45'],
                [1, 2, '1.20'],
                [3, 5, '1.05'],
                [6, 999, '1.00'],
            ],

            claimFactors: [
                0 => '1.00',
                1 => '1.25',
                2 => '1.60',
            ],

            pointFactors: [
                [0, 0, '1.00'],
                [1, 3, '1.05'],
                [4, 6, '1.15'],
                [7, 9, '1.35'],
            ],

            mileageFactors: [
                [0, 5000, '0.90'],
                [5001, 10000, '1.00'],
                [10001, 15000, '1.12'],
                [15001, 20000, '1.25'],
                [20001, 999999999, '1.40'],
            ],

            ncdDiscounts: [
                0 => '0.00',
                1 => '0.30',
                2 => '0.40',
                3 => '0.50',
                4 => '0.60',
                5 => '0.65',
                6 => '0.65',
                7 => '0.65',
                8 => '0.65',
                9 => '0.70',
            ],

            postcodeFactors: [
                'A' => '0.85',
                'B' => '0.95',
                'C' => '1.00',
                'D' => '1.18',
                'E' => '1.45',
            ],

            postcodeBands: [
                'A' => [
                    'TR', 'PL', 'EX', 'TA', 'DT',
                    'CA', 'IV', 'KW', 'PH', 'TD',
                ],

                'B' => [
                    'BA', 'BS', 'GL', 'SN', 'BH',
                    'SO', 'OX', 'NR', 'CB', 'YO', 'AB',
                ],

                'C' => [
                    'RG', 'GU', 'MK', 'CM', 'ME', 'BN',
                    'CV', 'LE', 'NG', 'NE', 'EH', 'CF',
                ],

                'D' => [
                    'KT', 'TW', 'HA', 'EN', 'IG', 'RM',
                    'CR', 'SK', 'OL', 'LS', 'WV', 'L', 'G',
                ],

                'E' => [
                    'E', 'N', 'NW', 'SE', 'SW',
                    'W', 'WC', 'EC', 'B', 'M',
                ],
            ],

            addOns: [
                'roadside' => '39.00',
                'legal_expenses' => '29.00',
                'courtesy_car' => '22.00',
                'key_protection' => '15.00',
                'excess_protection' => '34.00',
            ],

            minimumPremium: '180.00',
            iptRate: '0.12',
            administrationFee: '35.00',
        );
    }
}