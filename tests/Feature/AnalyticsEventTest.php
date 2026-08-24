<?php

namespace Tests\Feature;

use App\Jobs\TrackAnalyticsEventJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyticsEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_event_type_is_accepted(): void
    {
        $response = $this->postJson('/api/v1/analytics/events', [
            'event_type' => 'page_view',
            'metadata' => [
                'path' => '/',
            ],
        ]);

        $response->assertAccepted();
    }

    public function test_invalid_event_type_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/analytics/events', [
            'event_type' => 'spam_event',
        ]);

        $response->assertStatus(422);
    }

    public function test_metadata_values_must_be_strings(): void
    {
        $response = $this->postJson('/api/v1/analytics/events', [
            'event_type' => 'page_view',
            'metadata' => [
                'nested' => ['bad' => 'value'],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_crawler_events_are_ignored(): void
    {
        Queue::fake();

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)',
        ])->postJson('/api/v1/analytics/events', [
            'event_type' => 'page_view',
            'metadata' => ['path' => '/shop'],
        ])->assertAccepted();

        Queue::assertNothingPushed();
    }

    public function test_browser_events_are_queued(): void
    {
        Queue::fake();

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0',
        ])->postJson('/api/v1/analytics/events', [
            'event_type' => 'page_view',
            'metadata' => ['path' => '/'],
        ])->assertAccepted();

        Queue::assertPushed(TrackAnalyticsEventJob::class);
    }
}
