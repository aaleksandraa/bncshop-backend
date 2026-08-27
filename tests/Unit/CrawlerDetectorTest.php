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
        $this->assertTrue(CrawlerDetector::isCrawler('Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36 (compatible; Bytespider; https://zhanzhang.toutiao.com/)'));
        $this->assertTrue(CrawlerDetector::isCrawler('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/120.0.0.0 Safari/537.36'));
        $this->assertTrue(CrawlerDetector::isCrawler('Mozilla/5.0 (compatible; UptimeRobot/2.0; http://www.uptimerobot.com/)'));
        $this->assertFalse(CrawlerDetector::isCrawler('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0'));
        $this->assertFalse(CrawlerDetector::isCrawler(null));
    }
}
