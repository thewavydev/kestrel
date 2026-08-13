<?php

declare(strict_types=1);

namespace App\Domain\Rating\DTO;

final readonly class MidTermAdjustmentResult
{
    public function __construct(
        public string $unusedPremiumRefund,
        public string $amendedRiskPremium,
        public string $additionalPremium,
        public string $ipt,
        public string $adjustmentFee,
        public string $totalCharged,
    ) {
    }
}