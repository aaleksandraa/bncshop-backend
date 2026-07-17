<?php

namespace App\Services\Olx;

use RuntimeException;

class OlxListingImagesLockedException extends RuntimeException
{
    public function __construct(
        public readonly int $listingId,
        public readonly int $remoteImageCount,
    ) {
        parent::__construct(sprintf(
            'OLX oglas %d ima %d slika i nije moguće uploadovati nove bez brisanja starih ID-eva.',
            $listingId,
            $remoteImageCount,
        ));
    }
}
