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
     * MPC za shop je najviša aktivna MPC, čak i kad drugo skladište ima nižu cijenu.
     *
     * @param  array<int, array<string, mixed>>  $cjenovnici
     * @return array<string, array{mpc: float, candidates: list<float>}>
     */
    public function priceChoicesBySifra(array $cjenovnici): array
    {
        /** @var array<string, list<float>> $candidates */
        $candidates = [];

        foreach ($cjenovnici as $entry) {
            if (! isset($entry['sifra']) || ! $this->isPriceRowActive($entry)) {
                continue;
            }

            $mpc = ElineSupport::parseMpc($entry['mpc'] ?? 0);

            if ($mpc <= 0) {
                continue;
            }

            $sifra = (string) $entry['sifra'];
            $candidates[$sifra] ??= [];

            if (! in_array($mpc, $candidates[$sifra], true)) {
                $candidates[$sifra][] = $mpc;
            }
        }

        $choices = [];

        foreach ($candidates as $sifra => $mpcs) {
            $choice = [
                'mpc' => max($mpcs),
                'candidates' => $mpcs,
            ];

            foreach (ElineSupport::sifraKeys($sifra) as $key) {
                $choices[$key] = $choice;
            }
        }

        return $choices;
    }

    /**
     * Cijena za shop je najviša aktivna MPC. Zaliha ostaje iz reda koji sync već koristi.
     *
     * @param  array<string, array<string, mixed>>  $priceMap
     * @param  array<int, array<string, mixed>>  $cjenovnici
     * @return array<string, array<string, mixed>>
     */
    public function applyShelfMpc(array $priceMap, array $cjenovnici): array
    {
        $choices = $this->priceChoicesBySifra($cjenovnici);

        foreach ($priceMap as $sifra => $entry) {
            $choice = null;

            foreach (ElineSupport::sifraKeys((string) $sifra) as $key) {
                if (isset($choices[$key])) {
                    $choice = $choices[$key];
                    break;
                }
            }

            if ($choice === null) {
                continue;
            }

            $entry['mpc'] = $choice['mpc'];
            $priceMap[(string) $sifra] = $entry;
        }

        return $priceMap;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function isPriceRowActive(array $entry): bool
    {
        if (! array_key_exists('aktivan', $entry) || $entry['aktivan'] === null) {
            return true;
        }

        return ElineSupport::isActive($entry['aktivan']);
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
