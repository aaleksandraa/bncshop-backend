<?php

namespace App\Services\Eline;

use App\Models\ElineCategoryMapping;
use Ramsey\Uuid\Uuid;

class ElineSupport
{
    public static function externalProductId(string $sifra): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_DNS, 'eline:'.$sifra)->toString();
    }

    public static function resolveCategoryName(array $article): string
    {
        $category = trim((string) ($article['grupakategorija'] ?? ''));

        if ($category === '') {
            $category = trim((string) ($article['grupanaziv'] ?? ''));
        }

        return $category;
    }

    public static function inferCondition(string $categoryName): ?string
    {
        $normalized = mb_strtolower($categoryName, 'UTF-8');

        if (str_contains($normalized, 'refurbished')) {
            return ElineCategoryMapping::CONDITION_REFURBISHED;
        }

        if (str_contains($normalized, 'novi')) {
            return ElineCategoryMapping::CONDITION_NEW;
        }

        return null;
    }

    public static function plainTextDescription(?string $opis, ?string $htmlOpis): string
    {
        $source = trim((string) $opis);

        if ($source === '') {
            $source = trim((string) $htmlOpis);
        }

        if ($source === '') {
            return '';
        }

        $decoded = html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = preg_replace('/<br\s*\/?>/i', "\n", $decoded) ?? $decoded;
        $decoded = preg_replace('/<\/(p|div|li|tr|h[1-6])>/i', "\n", $decoded) ?? $decoded;
        $decoded = preg_replace('/<(p|div|li|tr|h[1-6])[^>]*>/i', '', $decoded) ?? $decoded;
        $stripped = strip_tags($decoded);
        $stripped = preg_replace('/\r\n|\r/', "\n", $stripped) ?? $stripped;

        $lines = array_map(
            static fn (string $line): string => trim(preg_replace('/[ \t]+/', ' ', $line) ?? $line),
            explode("\n", $stripped),
        );

        $stripped = implode("\n", $lines);
        $stripped = preg_replace('/\n{3,}/', "\n\n", $stripped) ?? $stripped;

        return trim($stripped);
    }

    public static function parseMpc(mixed $value): float
    {
        if (is_float($value) || is_int($value)) {
            return round((float) $value, 2);
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        return round((float) $normalized, 2);
    }

    public static function isActive(mixed $aktivan): bool
    {
        return (int) $aktivan === (int) config('bnc.eline_active_value', 255);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function feedHash(array $item): string
    {
        $payload = json_encode([
            'naziv' => (string) ($item['naziv'] ?? ''),
            'opis' => (string) ($item['opis'] ?? ''),
            'eline_category' => (string) ($item['eline_category'] ?? ''),
            'aktivan' => $item['aktivan'] ?? null,
            'mpc' => $item['mpc'],
            'stanje' => (int) ($item['stanje'] ?? 0),
            'price_aktivan' => $item['price_aktivan'] ?? null,
        ], JSON_UNESCAPED_UNICODE);

        return hash('sha256', $payload);
    }
}
