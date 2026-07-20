<?php

namespace Tests\Unit;

use App\Models\ApiSource;
use App\Services\Sync\IntegrationApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IntegrationApiClientFormatModifiedAfterTest extends TestCase
{
    use RefreshDatabase;

    public function test_format_modified_after_uses_utc_z_suffix(): void
    {
        $date = Carbon::parse('2026-07-07T20:00:00+02:00');

        $this->assertSame(
            '2026-07-07T18:00:00Z',
            IntegrationApiClient::formatModifiedAfter($date),
        );
    }

    public function test_format_modified_after_returns_null_for_null_input(): void
    {
        $this->assertNull(IntegrationApiClient::formatModifiedAfter(null));
    }
}
