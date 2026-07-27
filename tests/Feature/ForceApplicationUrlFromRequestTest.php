<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ForceApplicationUrlFromRequestTest extends TestCase
{
    public function test_web_request_overrides_cached_app_url_for_filament_assets(): void
    {
        config([
            'app.url' => 'https://api.bncshop.ba',
            'app.asset_url' => 'https://api.bncshop.ba',
        ]);

        $this->get('https://api.bnc.ba/up');

        $this->assertSame('https://api.bnc.ba', config('app.url'));
        $this->assertNull(config('app.asset_url'));
        $this->assertSame('https://api.bnc.ba', URL::to('/js/filament/tables/components/table.js'));
    }
}
