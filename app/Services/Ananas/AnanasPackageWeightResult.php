<?php

namespace App\Services\Ananas;

class AnanasPackageWeightResult
{
    public const STATUS_OK = 'OK';

    public const STATUS_MISSING = 'MISSING';

    public const STATUS_UNPARSEABLE = 'UNPARSEABLE';

    public const STATUS_ZERO_OR_NEGATIVE = 'ZERO_OR_NEGATIVE';

    public const STATUS_UNITLESS_AMBIGUOUS = 'UNITLESS_AMBIGUOUS';

    public function __construct(
        public readonly ?float $resolvedWeightKg,
        public readonly ?string $sourceAttributeName,
        public readonly ?string $rawValue,
        public readonly string $parseStatus,
        public readonly ?string $reason = null,
    ) {}

    public function isOk(): bool
    {
        return $this->parseStatus === self::STATUS_OK && $this->resolvedWeightKg !== null;
    }

    public static function missing(): self
    {
        return new self(null, null, null, self::STATUS_MISSING, 'No weight attribute in approved chain.');
    }

    public static function unparseable(?string $sourceAttributeName, ?string $rawValue, string $reason): self
    {
        return new self(null, $sourceAttributeName, $rawValue, self::STATUS_UNPARSEABLE, $reason);
    }

    public static function zeroOrNegative(?string $sourceAttributeName, ?string $rawValue): self
    {
        return new self(null, $sourceAttributeName, $rawValue, self::STATUS_ZERO_OR_NEGATIVE, 'Weight must be greater than zero.');
    }

    public static function unitlessAmbiguous(?string $sourceAttributeName, ?string $rawValue): self
    {
        return new self(null, $sourceAttributeName, $rawValue, self::STATUS_UNITLESS_AMBIGUOUS, 'Unit-less numeric weight is not allowed without a confirmed convention.');
    }

    public static function ok(float $kg, string $sourceAttributeName, string $rawValue): self
    {
        return new self($kg, $sourceAttributeName, $rawValue, self::STATUS_OK);
    }
}
