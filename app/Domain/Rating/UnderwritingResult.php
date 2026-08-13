<?php

declare(strict_types=1);

namespace App\Domain\Rating;

final readonly class UnderwritingResult
{
    /**
     * @param array<int, string> $reasonCodes
     */
    public function __construct(
        public string $decision,
        public array $reasonCodes,
    ) {
    }

    public function isDeclined(): bool
    {
        return $this->decision === 'DECLINED';
    }

    public function isReferred(): bool
    {
        return $this->decision === 'REFERRED';
    }

    public function isQuoted(): bool
    {
        return $this->decision === 'QUOTED';
    }
}