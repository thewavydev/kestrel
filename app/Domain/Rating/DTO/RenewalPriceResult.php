<?php

declare(strict_types=1);

namespace App\Domain\Rating\DTO;

final readonly class RenewalPriceResult
{
    public function __construct(
        public string $technicalRenewalPrice,
        public string $equivalentNewBusinessPrice,
        public string $priceToOffer,
        public bool $capApplied,
    ) {
    }

    
}