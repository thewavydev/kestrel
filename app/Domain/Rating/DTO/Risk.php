<?php

declare(strict_types=1);

namespace App\Domain\Rating\DTO;

final readonly class Risk
{
    /**
     * @param Driver[] $drivers
     * @param string[] $addOns
     */
    public function __construct(
        public int $vehicleGroup,
        public string $coverType,
        public array $drivers,
        public int $faultClaims,
        public int $penaltyPoints,
        public int $annualMileage,
        public string $postcode,
        public string $classOfUse,
        public int $voluntaryExcess,
        public int $ncdYears,
        public bool $ncdProtected = false,
        public array $addOns = [],
    ) {
    }
}