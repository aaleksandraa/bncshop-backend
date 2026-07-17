<?php

namespace App\Services\Olx;

use GdImage;
use Illuminate\Support\Facades\Http;
use Throwable;

class OlxProductImageDownloader
{
    public function __construct(
        private readonly OlxImageNormalizer $normalizer,
        private readonly OlxImageWatermarker $watermarker,
    ) {}

    /**
     * @return array{contents: string, filename: string, mime: string, source_url: string}|null
     */
    public function downloadForUpload(string $url, int $index = 0): ?array
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        try {
            $response = Http::timeout((int) config('bnc.olx_image_download_timeout', 30))
                ->retry(2, 500)
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $contents = $response->body();

        if ($contents === '') {
            return null;
        }

        return $this->normalizeForOlx($contents, $url, $index);
    }

    /**
     * @return array{contents: string, filename: string, mime: string, source_url: string}|null
     */
    private function normalizeForOlx(string $contents, string $url, int $index): ?array
    {
        $jpeg = $this->normalizer->toJpeg($contents);

        if ($jpeg === null) {
            return null;
        }

        $jpeg = $this->watermarker->applyToJpeg($jpeg) ?? $jpeg;

        return [
            'contents' => $jpeg,
            'filename' => sprintf('product-%d.jpg', $index + 1),
            'mime' => 'image/jpeg',
            'source_url' => $url,
        ];
    }
}
