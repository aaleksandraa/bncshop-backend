<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SitemapController extends Controller
{
    use RespondsWithJson;

    /** @var list<string> */
    private const SECTIONS = ['pages', 'categories', 'products', 'savjeti'];

    public function __invoke(Request $request): JsonResponse
    {
        $cached = SystemSetting::query()->where('key', 'sitemap_cache')->first();

        if (! $cached) {
            return $this->success($this->emptyPayload());
        }

        $value = is_array($cached->value) ? $cached->value : [];
        $section = $request->query('section');

        if (! is_string($section) || $section === '') {
            return $this->success($this->normalizePayload($value));
        }

        if (! in_array($section, self::SECTIONS, true)) {
            return $this->success([
                'generated_at' => $value['generated_at'] ?? null,
                'section' => $section,
                'entries' => [],
            ]);
        }

        if ($section === 'products') {
            return $this->success($this->productSectionPayload($value, $request));
        }

        $sections = is_array($value['sections'] ?? null) ? $value['sections'] : [];
        $sectionData = is_array($sections[$section] ?? null) ? $sections[$section] : null;

        return $this->success([
            'generated_at' => $value['generated_at'] ?? null,
            'section' => $section,
            'count' => is_array($sectionData) ? ($sectionData['count'] ?? 0) : 0,
            'entries' => is_array($sectionData['entries'] ?? null) ? $sectionData['entries'] : [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function productSectionPayload(array $value, Request $request): array
    {
        $sections = is_array($value['sections'] ?? null) ? $value['sections'] : [];
        $products = is_array($sections['products'] ?? null) ? $sections['products'] : null;

        if ($products === null) {
            return [
                'generated_at' => $value['generated_at'] ?? null,
                'section' => 'products',
                'chunk' => 1,
                'chunk_count' => 0,
                'count' => 0,
                'entries' => [],
            ];
        }

        $chunkCount = (int) ($products['chunk_count'] ?? 0);
        $chunk = max(1, (int) $request->query('chunk', 1));

        if ($chunkCount > 0 && $chunk > $chunkCount) {
            return [
                'generated_at' => $value['generated_at'] ?? null,
                'section' => 'products',
                'chunk' => $chunk,
                'chunk_count' => $chunkCount,
                'count' => 0,
                'entries' => [],
            ];
        }

        $chunks = is_array($products['chunks'] ?? null) ? $products['chunks'] : [];
        $chunkData = collect($chunks)->first(
            static fn ($item): bool => is_array($item) && (int) ($item['index'] ?? 0) === $chunk,
        );

        return [
            'generated_at' => $value['generated_at'] ?? null,
            'section' => 'products',
            'chunk' => $chunk,
            'chunk_count' => $chunkCount,
            'count' => is_array($chunkData) ? (int) ($chunkData['count'] ?? 0) : 0,
            'entries' => is_array($chunkData['entries'] ?? null) ? $chunkData['entries'] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(): array
    {
        return [
            'generated_at' => null,
            'sections' => [
                'pages' => ['entries' => [], 'count' => 0],
                'categories' => ['entries' => [], 'count' => 0],
                'products' => ['chunks' => [], 'count' => 0, 'chunk_count' => 0],
                'savjeti' => ['entries' => [], 'count' => 0],
            ],
            'counts' => [
                'pages' => 0,
                'categories' => 0,
                'products' => 0,
                'savjeti' => 0,
                'total' => 0,
            ],
            'entries' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function normalizePayload(array $value): array
    {
        if (isset($value['sections']) && is_array($value['sections'])) {
            return $value;
        }

        $entries = is_array($value['entries'] ?? null) ? $value['entries'] : [];

        return [
            'generated_at' => $value['generated_at'] ?? null,
            'sections' => $this->legacySectionsFromEntries($entries),
            'counts' => [
                'pages' => 0,
                'categories' => 0,
                'products' => 0,
                'savjeti' => 0,
                'total' => count($entries),
            ],
            'entries' => $entries,
        ];
    }

    /**
     * Backward compatibility for caches generated before section split.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    private function legacySectionsFromEntries(array $entries): array
    {
        $pages = [];
        $categories = [];
        $products = [];
        $savjeti = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $type = (string) ($entry['type'] ?? '');

            if (in_array($type, ['home', 'static', 'page'], true)) {
                $pages[] = $entry;
            } elseif (in_array($type, ['category', 'manufacturer'], true)) {
                $categories[] = $entry;
            } elseif ($type === 'product') {
                $products[] = $entry;
            } elseif (in_array($type, ['blog', 'savjeti'], true)) {
                $savjeti[] = $entry;
            }
        }

        $productChunks = array_chunk($products, 10_000);
        if ($productChunks === []) {
            $productChunks = [[]];
        }

        return [
            'pages' => ['entries' => $pages, 'count' => count($pages)],
            'categories' => ['entries' => $categories, 'count' => count($categories)],
            'products' => [
                'chunks' => array_map(
                    static fn (array $chunk, int $index): array => [
                        'index' => $index + 1,
                        'entries' => $chunk,
                        'count' => count($chunk),
                    ],
                    $productChunks,
                    array_keys($productChunks),
                ),
                'count' => count($products),
                'chunk_count' => count($productChunks),
            ],
            'savjeti' => ['entries' => $savjeti, 'count' => count($savjeti)],
        ];
    }
}
