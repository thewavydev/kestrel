<?php

declare(strict_types=1);

namespace App\Domain\Rating\DTO;

final readonly class BreakdownLine
{
    public function __construct(
        public string $name,
        public string $value,
        public ?string $multiplier,
        public string $subtotal,
    ) {
    }
}