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
            '/bot|crawl|spider|slurp|facebookexternalhit|meta-externalagent|whatsapp|telegram|preview|python-requests|go-http-client|bytespider|lighthouse|gtmetrix|pingdom|uptimerobot|statuscake|site24x7|headlesschrome|wget|curl\/|libwww|scrapy|httpie|chatgpt-user|perplexity|inspectiontool|pagespeed|ptst|phantomjs|puppeteer|playwright/i',
            $userAgent,
        );
    }
}
