<?php

namespace App\Services\Olx;

class OlxAttributeParser
{
    /**
     * @return array<string, string|null>
     */
    public function parseFromProductText(string $text): array
    {
        $normalized = html_entity_decode(strip_tags($text));

        return [
            'ram' => $this->parseRam($normalized),
            'ssd_gb' => $this->parseSsdGb($normalized),
            'os' => $this->parseOs($normalized),
            'display_inch' => $this->parseDisplayInch($normalized),
            'processor_brand' => $this->parseProcessorBrand($normalized),
            'connection' => $this->parseConnection($normalized),
            'monitor_type' => $this->parseMonitorType($normalized),
            'tv_technology' => $this->parseTvTechnology($normalized),
            'resolution' => $this->parseResolution($normalized),
            'headphone_type' => $this->parseHeadphoneType($normalized),
            'video_resolution' => $this->parseVideoResolution($normalized),
            'printer_type' => $this->parsePrinterType($normalized),
            'smartwatch_os' => $this->parseSmartwatchOs($normalized),
            'color' => $this->parseColor($normalized),
            'listing_type' => $this->parseListingType($normalized),
        ];
    }

    public function parseRam(string $text): ?string
    {
        if (preg_match('/(\d+)\s*GB\s*RAM/i', $text, $m)) {
            return $this->formatRamSelect((int) $m[1]);
        }

        if (preg_match('/\b(\d+)\s*GB\b/i', $text, $m)) {
            $gb = (int) $m[1];

            if (in_array($gb, [1, 2, 3, 4, 6, 8, 12, 16, 24, 32, 48, 64], true)) {
                return $this->formatRamSelect($gb);
            }
        }

        if (preg_match('/\b(\d+)\s*\/\s*(\d+)\b/', $text, $m)) {
            $second = (int) $m[2];

            if (in_array($second, [4, 8, 12, 16, 24, 32, 48, 64], true)) {
                return $this->formatRamSelect($second);
            }
        }

        return null;
    }

    public function parseSsdGb(string $text): ?string
    {
        if (preg_match('/(\d+)\s*GB\s*SSD/i', $text, $m)) {
            return (string) (int) $m[1];
        }

        if (preg_match('/SSD\s*(\d+)\s*GB/i', $text, $m)) {
            return (string) (int) $m[1];
        }

        if (preg_match('/\b(\d+)\s*GB\s*SSD\b/i', $text, $m)) {
            return (string) (int) $m[1];
        }

        return null;
    }

    public function parseOs(string $text): ?string
    {
        if (preg_match('/Win(?:dows)?\s*11/i', $text)) {
            return 'Win 11';
        }

        if (preg_match('/Win(?:dows)?\s*10/i', $text)) {
            return 'Win 10';
        }

        if (preg_match('/\b(freedos|free\s+dos|bez\s+os|without\s+os|no\s+os|nema\s+os)\b/i', $text)) {
            return 'Nema';
        }

        if (preg_match('/\bDOS\b/i', $text) && ! preg_match('/Win(?:dows)?/i', $text)) {
            return 'Nema';
        }

        if (preg_match('/\bLinux\b/i', $text)) {
            return 'Linux';
        }

        if (preg_match('/\b(mac\s*os|macos|apple\s+os)\b/i', $text)) {
            return 'Mac OS';
        }

        if (preg_match('/\b(chrome\s*os|chromebook)\b/i', $text)) {
            return 'Linux';
        }

        return null;
    }

    public function parseDisplayInch(string $text): ?string
    {
        if (preg_match('/Dijagonala\s*\(inch\)\s*:?\s*(\d+(?:[.,]\d+)?)/iu', $text, $m)) {
            return $this->normalizeInch(str_replace(',', '.', $m[1]));
        }

        if (preg_match('/Veličina\s*\(inch\)\s*:?\s*(\d+(?:[.,]\d+)?)/iu', $text, $m)) {
            return $this->normalizeInch(str_replace(',', '.', $m[1]));
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*["\']/', $text, $m)) {
            return $this->normalizeInch($m[1]);
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*inch/i', $text, $m)) {
            return $this->normalizeInch(str_replace(',', '.', $m[1]));
        }

        if (preg_match('/\b(\d+(?:\.\d+)?)\s*in\b/i', $text, $m)) {
            return $this->normalizeInch($m[1]);
        }

        return null;
    }

    public function parseProcessorBrand(string $text): ?string
    {
        if (preg_match('/\bApple\b|\bM[1-9]\b|\bMacBook\b/i', $text)) {
            return 'Apple';
        }

        if (preg_match('/\bIntel\b/i', $text)) {
            return 'Intel';
        }

        if (preg_match('/\bAMD\b/i', $text)) {
            return 'AMD';
        }

        if (preg_match('/\bRyzen\b/i', $text)) {
            return 'AMD';
        }

        if (preg_match('/\bCore\s*i[3579]/i', $text)) {
            return 'Intel';
        }

        if (preg_match('/\bCeleron\b|\bPentium\b|\bXeon\b/i', $text)) {
            return 'Intel';
        }

        return null;
    }

    public function parseConnection(string $text): ?string
    {
        if (preg_match('/\b(wireless|bežični|bezicni|bluetooth|\bBT\b|wifi|wi-fi)\b/i', $text)) {
            return 'Wireless (bežični)';
        }

        if (preg_match('/\bUSB\b/i', $text)) {
            return 'USB';
        }

        if (preg_match('/\bPS\s*\/?\s*2\b/i', $text)) {
            return 'PS/2';
        }

        return null;
    }

    public function parseMonitorType(string $text): ?string
    {
        if (preg_match('/\bOLED\b/i', $text)) {
            return 'OLED';
        }

        if (preg_match('/\bQLED\b/i', $text)) {
            return 'LED';
        }

        if (preg_match('/\b(IPS|VA|TN|LCD|LED)\b/i', $text, $m)) {
            return match (strtoupper($m[1])) {
                'IPS', 'VA', 'TN', 'LCD' => 'LCD',
                'LED' => 'LED',
                default => 'LED',
            };
        }

        return null;
    }

    public function parseTvTechnology(string $text): ?string
    {
        if (preg_match('/\bMINI\s*LED\b/i', $text)) {
            return 'MINI LED';
        }

        if (preg_match('/\bQLED\b/i', $text)) {
            return 'QLED';
        }

        if (preg_match('/\bOLED\b/i', $text)) {
            return 'OLED';
        }

        if (preg_match('/\bLED\b/i', $text)) {
            return 'LED LCD';
        }

        if (preg_match('/\bLCD\b/i', $text)) {
            return 'LCD';
        }

        return null;
    }

    public function parseResolution(string $text): ?string
    {
        if (preg_match('/\b8K\b/i', $text)) {
            return '8K';
        }

        if (preg_match('/\b(4K|UHD|3840\s*x\s*2160)\b/i', $text)) {
            return '4K';
        }

        if (preg_match('/\b(2K|2560\s*x\s*1440|QHD)\b/i', $text)) {
            return '2K';
        }

        if (preg_match('/\b(1080p|Full\s*HD|FHD|1920\s*x\s*1080)\b/i', $text)) {
            return '1080p (full HD)';
        }

        if (preg_match('/\b(768p|1366\s*x\s*768|HD Ready)\b/i', $text)) {
            return '768p';
        }

        return null;
    }

    public function parseHeadphoneType(string $text): ?string
    {
        if (preg_match('/\b(in[-\s]?ear|earbuds|buds)\b/i', $text)) {
            return 'U uho';
        }

        if (preg_match('/\b(on[-\s]?ear)\b/i', $text)) {
            return 'Oko uha';
        }

        if (preg_match('/\b(over[-\s]?ear|headset|headphones|slušalice|slusalice)\b/i', $text)) {
            return 'Na uho';
        }

        return null;
    }

    public function parseVideoResolution(string $text): ?string
    {
        if (preg_match('/\b8K\b/i', $text)) {
            return '8K';
        }

        if (preg_match('/\b4K\b/i', $text)) {
            return '4K';
        }

        if (preg_match('/\b1080p\b/i', $text)) {
            return '1080p';
        }

        if (preg_match('/\b720p\b/i', $text)) {
            return '720p';
        }

        return null;
    }

    public function parsePrinterType(string $text): ?string
    {
        if (preg_match('/\bskener\b/i', $text)) {
            return 'Skener';
        }

        if (preg_match('/\bkopir\b/i', $text)) {
            return 'Kopir aparat';
        }

        if (preg_match('/\bprinter\b/i', $text)) {
            return 'Printer';
        }

        return null;
    }

    public function parseSmartwatchOs(string $text): ?string
    {
        if (preg_match('/\b(watchOS|Apple\s*Watch|iOS)\b/i', $text)) {
            return 'iOS';
        }

        if (preg_match('/\bWear\s*OS\b/i', $text)) {
            return 'Android';
        }

        if (preg_match('/\bAndroid\b/i', $text)) {
            return 'Android';
        }

        return null;
    }

    public function parseColor(string $text): ?string
    {
        return match (true) {
            (bool) preg_match('/\b(bijel|white)\b/i', $text) => 'Bijela',
            (bool) preg_match('/\b(crn|black)\b/i', $text) => 'Crna',
            (bool) preg_match('/\b(siv|gray|grey)\b/i', $text) => 'Siva',
            (bool) preg_match('/\b(srebr|silver)\b/i', $text) => 'Srebrna',
            (bool) preg_match('/\b(zlat|gold)\b/i', $text) => 'Zlatna',
            default => null,
        };
    }

    public function parseListingType(string $text): ?string
    {
        if (preg_match('/\biznajmlj/i', $text)) {
            return 'Iznajmljivanje';
        }

        return 'Prodaja';
    }

    public function booleanToOlx(?bool $value): string
    {
        return $value ? 'Da' : 'Ne';
    }

    private function formatRamSelect(int $gb): string
    {
        return $gb >= 1024 ? '512 MB' : "{$gb} GB";
    }

    private function normalizeInch(string $value): string
    {
        $float = (float) $value;
        $whole = (int) round($float * 10);

        if ($whole % 10 === 0) {
            return (string) (int) $float;
        }

        return number_format($float, 1, '.', '');
    }
}
