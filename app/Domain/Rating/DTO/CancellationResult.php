<?php

declare(strict_types=1);

namespace App\Domain\Rating\DTO;

final readonly class CancellationResult
{
    public function __construct(
        public string $premiumRefund,
        public string $addOnRefund,
        public string $iptRefund,
        public string $cancellationFee,
        public string $netRefund,
        public string $administrationFeeRetained,
    ) {
    }


    
}