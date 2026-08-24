<?php

namespace App\Support;

final class CrawlerDetector
{
    public static function isCrawler(?string $userAgent): bool
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return false;
        }

        return (bool) preg_match(
            '/bot|crawl|spider|slurp|facebookexternalhit|meta-externalagent|whatsapp|telegram|preview|python-requests|go-http-client/i',
            $userAgent,
        );
    }
}
