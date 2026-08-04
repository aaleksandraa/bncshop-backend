<?php

namespace App\Services\Media;

final class OptimizedImageSet
{
    /**
     * @param  array<int, string>  $variants  width => binary contents
     */
    public function __construct(
        public readonly string $masterKey,
        public readonly string $masterContents,
        public readonly int $width,
        public readonly int $height,
        public readonly array $variants = [],
        public readonly bool $passthrough = false,
    ) {}
}
