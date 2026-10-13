<?php

namespace App\Domain\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

// spec.md AC-10.2: end before start is invalid; an empty range is not an error.
final class DateRange
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {
        if ($this->to->lessThan($this->from)) {
            throw new InvalidArgumentException('INVALID_DATE_RANGE');
        }
    }
}
