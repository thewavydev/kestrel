<?php

declare(strict_types=1);

namespace App\Domain\Rating\DTO;

final readonly class Driver
{
    /**
     * @param string[] $convictions
     */
    public function __construct(
        public int $age,
        public int $licenceMonths,
        public array $convictions = [],
    ) {
    }
}