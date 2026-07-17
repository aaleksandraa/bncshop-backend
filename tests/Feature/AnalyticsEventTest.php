<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
