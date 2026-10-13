<?php

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

// spec.md AC-03.2: 13 digits, digits only. Plain PHP, no framework — Article I's spirit.
final class Isbn
{
    private string $value;

    public function __construct(string $value)
    {
        $digits = preg_replace('/\D/', '', $value);

        if (strlen($digits) !== 13) {
            throw new InvalidArgumentException('ISBN debe tener 13 dígitos.');
        }

        $this->value = $digits;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
