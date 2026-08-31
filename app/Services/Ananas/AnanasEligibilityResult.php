<?php

namespace App\Services\Ananas;

class AnanasEligibilityResult
{
    public function __construct(
        public readonly bool $eligible,
        public readonly ?string $reasonCode = null,
    ) {}

    public static function eligible(): self
    {
        return new self(true);
    }

    public static function notEligible(string $reasonCode): self
    {
        return new self(false, $reasonCode);
    }
}
