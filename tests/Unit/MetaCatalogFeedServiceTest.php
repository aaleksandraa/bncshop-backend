<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\Integrations\MetaCatalogFeedService;
use App\Services\Integrations\TrackingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaCatalogFeedServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_feed_token_and_authorizes_request(): void
    {
        $service = app(MetaCatalogFeedService::class);
        $token = $service->feedToken();

        $this->assertNotSame('', $token);
        $this->assertTrue($service->isAuthorized($token));
        $this->assertFalse($service->isAuthorized('wrong-token'));
    }

    public function test_feed_url_points_to_csv_endpoint(): void
    {
        config(['bnc.frontend_url' => 'https://bnc.ba']);

        $service = app(MetaCatalogFeedService::class);
        $url = $service->feedUrl();

        $this->assertStringContainsString('/feeds/meta-catalog.csv?token=', $url);
    }
}
