<?php

namespace App\Services\Eline;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ElineApiClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchArtikli(): array
    {
        $payload = $this->fetchJson('ArtikliZaWeb');

        return array_values($payload['artikli'] ?? []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchCjenovnici(): array
    {
        $payload = $this->fetchJson('CjenovniciZaWeb');

        return array_values($payload['cjenovnici'] ?? []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $artikli
     * @param  array<string, array<string, mixed>>  $priceMap
     * @return \Generator<int, Collection<int, array<string, mixed>>>
     */
    public function mergeProductDataInChunks(
        array $artikli,
        array $priceMap,
        int $chunkSize = 500,
    ): \Generator {
        foreach (array_chunk($artikli, max(1, $chunkSize)) as $chunk) {
            yield $this->mergeProductData($chunk, $priceMap);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $cjenovnici
     * @return array<string, array<string, mixed>>
     */
    public function buildPriceMap(array $cjenovnici): array
    {
        $map = [];

        foreach ($cjenovnici as $entry) {
            if (! isset($entry['sifra'])) {
                continue;
            }

            $map[(string) $entry['sifra']] = $entry;
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $artikli
     * @param  array<string, array<string, mixed>>  $priceMap
     * @return Collection<int, array<string, mixed>>
     */
    public function mergeProductData(array $artikli, array $priceMap): Collection
    {
        return collect($artikli)
            ->filter(fn (array $article): bool => isset($article['sifra']))
            ->map(function (array $article) use ($priceMap): array {
                $sifra = (string) $article['sifra'];
                $price = $priceMap[$sifra] ?? null;

                return [
                    'sifra' => $sifra,
                    'naziv' => (string) ($article['naziv'] ?? ''),
                    'opis' => ElineSupport::plainTextDescription(
                        $article['opis'] ?? null,
                        $article['htmlOpis'] ?? null,
                    ),
                    'eline_category' => ElineSupport::resolveCategoryName($article),
                    'aktivan' => $article['aktivan'] ?? null,
                    'proizvodjac' => (string) ($article['proizvodjac'] ?? ''),
                    'brend' => (string) ($article['brend'] ?? ''),
                    'marka' => (string) ($article['marka'] ?? ''),
                    'mpc' => $price !== null ? ElineSupport::parseMpc($price['mpc'] ?? 0) : null,
                    'stanje' => $price !== null ? (int) floor((float) ($price['stanje'] ?? 0)) : 0,
                    'price_aktivan' => $price['aktivan'] ?? null,
                ];
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchJson(string $endpoint): array
    {
        $url = $this->buildUrl($endpoint);
        $timeout = config('bnc.eline_api_timeout', 120);
        $retries = config('bnc.eline_api_retries', 3);

        $response = Http::timeout($timeout)
            ->retry($retries, 2000)
            ->withOptions(['verify' => (bool) config('bnc.eline_api_verify_ssl', true)])
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'eLine API request failed (%s): HTTP %s',
                $endpoint,
                $response->status(),
            ));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException(sprintf('eLine API returned invalid JSON for %s.', $endpoint));
        }

        return $payload;
    }

    private function buildUrl(string $endpoint): string
    {
        $baseUrl = rtrim((string) config('bnc.eline_api_base_url'), '/');
        $token = (string) config('bnc.eline_api_token');

        if ($token === '') {
            throw new RuntimeException('ELINE_API_TOKEN is not configured.');
        }
        $shopCode = (string) config('bnc.eline_api_shop_code');

        return sprintf('%s/%s/%s/%s', $baseUrl, $endpoint, $token, $shopCode);
    }
}
