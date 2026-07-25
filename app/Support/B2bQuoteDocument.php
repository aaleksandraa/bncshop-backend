<?php

namespace App\Support;

class B2bQuoteDocument
{
    public static function title(): string
    {
        return 'Predračun';
    }

    public static function downloadFilename(string $orderNumber): string
    {
        return 'predracun-'.$orderNumber.'.pdf';
    }

    public static function logoDataUri(): ?string
    {
        $candidates = [
            public_path('bnc-logo.png'),
            public_path('images/bnc-logo.png'),
        ];

        foreach ($candidates as $path) {
            if (! is_readable($path)) {
                continue;
            }

            $contents = file_get_contents($path);

            if ($contents === false) {
                continue;
            }

            return 'data:image/png;base64,'.base64_encode($contents);
        }

        return null;
    }
}
