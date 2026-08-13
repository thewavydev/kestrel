<?php

declare(strict_types=1);

namespace App\Domain\Rating\ValueObjects;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(
        private int $pence
    ) {
    }

    public static function fromPounds(string $amount): self
    {
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('Invalid money amount.');
        }

        [$pounds, $pence] = array_pad(explode('.', $amount, 2), 2, '0');

        $pence = str_pad($pence, 2, '0');

        return new self(
            ((int) $pounds * 100) + (int) $pence
        );
    }

    public static function fromPence(int $pence): self
    {
        if ($pence < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }

        return new self($pence);
    }

    public function pence(): int
    {
        return $this->pence;
    }

    public function pounds(): string
    {
        return number_format($this->pence / 100, 2, '.', '');
    }

    public function add(self $other): self
    {
        return new self($this->pence + $other->pence);
    }

    public function subtract(self $other): self
    {
        return new self($this->pence - $other->pence);
    }

    public function equals(self $other): bool
    {
        return $this->pence === $other->pence;
    }

    public function __toString(): string
    {
        return $this->pounds();
    }
}