<?php

declare(strict_types=1);

namespace App\Domain\Rating\DTO;

final readonly class RatingResult
{
    /**
     * @param BreakdownLine[] $breakdown
     * @param string[] $reasonCodes
     */
    public function __construct(
        public string $decision,
        public array $reasonCodes = [],
        public ?string $netPremium = null,
        public ?string $ncdProtectionFee = null,
        public ?string $ratingBasisVersion = null,
        public ?string $addOns = null,
        public ?string $taxableAmount = null,
        public ?string $ipt = null,
        public ?string $administrationFee = null,
        public ?string $total = null,
        public ?string $compulsoryExcess = null,
        public ?string $voluntaryExcess = null,
        public ?string $totalExcess = null,
        public array $breakdown = [],
    ) {
    }

    public function isQuoted(): bool
    {
        return $this->decision === 'QUOTE';
    }
}