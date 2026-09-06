<?php

namespace Tests\Unit;

use App\Services\Integrations\TrackingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaDiagnoseCommandTest extends TestCase
{
    use RefreshDatabase;
    public function test_diagnose_sends_page_view_with_required_user_data(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['events_received' => 1], 200),
        ]);

        app(TrackingSettings::class)->save([
            'fb_dataset_id' => '786294308773690',
            'fb_access_token' => 'meta-token',
        ]);

        $this->artisan('meta:diagnose')
            ->assertSuccessful();

        Http::assertSent(function ($request): bool {
            $event = $request->data()['data'][0] ?? [];

            return ($event['event_name'] ?? null) === 'PageView'
                && filled($event['user_data']['client_user_agent'] ?? null)
                && filled($event['user_data']['client_ip_address'] ?? null);
        });
    }
}
