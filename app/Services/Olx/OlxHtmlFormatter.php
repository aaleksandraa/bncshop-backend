<?php

namespace App\Services\Olx;

use App\Support\SafeHtml;
use App\Services\Eline\ElineSupport;

class OlxHtmlFormatter
{
    public function formatBlock(?string $raw): string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return '';
        }

        if ($this->looksLikeHtml($raw)) {
            return trim((string) SafeHtml::clean($raw));
        }

        return $this->plainTextToHtml($raw);
    }

    public function looksLikeHtml(string $text): bool
    {
        return (bool) preg_match('/<[a-z][\s\S]*>/i', $text);
    }

    public function plainTextToHtml(string $text): string
    {
        $text = ElineSupport::plainTextDescription($text, $text);
        $text = $this->formatBooleanTokens($text);

        $paragraphs = preg_split("/\n{2,}/", $text) ?: [$text];
        $chunks = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            $paragraph = htmlspecialchars($paragraph, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $paragraph = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $paragraph) ?? $paragraph;
            $paragraph = nl2br($paragraph, false);
            $chunks[] = '<p>'.$paragraph.'</p>';
        }

        return implode('', $chunks);
    }

    /**
     * @param  array<int, array{display_name?: string, display_value?: string}>  $attributes
     */
    public function specificationsHtml(array $attributes): string
    {
        if ($attributes === []) {
            return '';
        }

        $items = [];

        foreach ($attributes as $attribute) {
            $name = trim((string) ($attribute['display_name'] ?? ''));
            $value = trim((string) ($attribute['display_value'] ?? ''));

            if ($name === '' || $value === '') {
                continue;
            }

            $items[] = '<li><strong>'.htmlspecialchars($name, ENT_QUOTES | ENT_HTML5, 'UTF-8').':</strong> '
                .htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</li>';
        }

        if ($items === []) {
            return '';
        }

        return '<p><strong>Specifikacije:</strong></p><ul>'.implode('', $items).'</ul>';
    }

    /**
     * @param  array<int, string>  $sections
     */
    public function combineSections(array $sections): string
    {
        return trim(implode("\n", array_filter(array_map('trim', $sections))));
    }

    private function formatBooleanTokens(string $text): string
    {
        return preg_replace_callback(
            '/(^|[\n;]\s*)([^:\n;]+:\s*)(true|false)(\s*(?=[\n;]|$))/i',
            static function (array $matches): string {
                $value = strtolower($matches[3]) === 'true' ? 'Da' : 'Ne';

                return $matches[1].$matches[2].$value.$matches[4];
            },
            $text,
        ) ?? $text;
    }
}
