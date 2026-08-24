<?php

namespace Tests\Unit;

use App\Support\CrawlerDetector;
use PHPUnit\Framework\TestCase;

class CrawlerDetectorTest extends TestCase
{
    public function test_detects_known_crawlers(): void
    {
        $this->assertTrue(CrawlerDetector::isCrawler('Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)'));
        $this->assertTrue(CrawlerDetector::isCrawler('facebookexternalhit/1.1'));
        $this->assertTrue(CrawlerDetector::isCrawler('meta-externalagent/1.1'));
        $this->assertTrue(CrawlerDetector::isCrawler('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'));
        $this->assertFalse(CrawlerDetector::isCrawler('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0'));
        $this->assertFalse(CrawlerDetector::isCrawler(null));
    }
}
