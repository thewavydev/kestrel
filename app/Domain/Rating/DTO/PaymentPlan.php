<?php

declare(strict_types=1);

namespace App\Domain\Rating\DTO;

final readonly class PaymentPlan
{
    /**
     * @param string[] $instalments
     */
    public function __construct(
        public string $deposit,
        public array $instalments,
        public string $financedAmount,
        public string $creditCharge,
        public string $totalPayable,
    ) {
    }
}