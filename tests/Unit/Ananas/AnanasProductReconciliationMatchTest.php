<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasProductMapping;
use App\Services\Ananas\AnanasProductReconciliationService;
use ReflectionMethod;
use Tests\TestCase;

class AnanasProductReconciliationMatchTest extends TestCase
{
    public function test_one_catalog_page_matches_ean_with_or_without_leading_zero(): void
    {
        $service = app(AnanasProductReconciliationService::class);
        $index = $this->invoke($service, 'indexRemoteRows', [[
            [
                'id' => 7452532,
                'ean' => '4977766821773',
                'externalId' => '15',
                'status' => 'READY_FOR_PUBLISH',
            ],
        ]]);

        $mapping = new AnanasProductMapping([
            'ean' => '04977766821773',
            'external_id' => '15',
            'product_id' => 15,
        ]);

        $remote = $this->invoke($service, 'findIndexedRemote', [$mapping, $index]);

        $this->assertSame(7452532, $remote['id'] ?? null);
    }

    public function test_external_id_matches_when_ean_differs(): void
    {
        $service = app(AnanasProductReconciliationService::class);
        $index = $this->invoke($service, 'indexRemoteRows', [[
            [
                'id' => 99,
                'ean' => '8600000000011',
                'externalId' => '222',
            ],
        ]]);

        $mapping = new AnanasProductMapping([
            'ean' => '1111111111111',
            'external_id' => '222',
            'product_id' => 222,
        ]);

        $remote = $this->invoke($service, 'findIndexedRemote', [$mapping, $index]);

        $this->assertSame(99, $remote['id'] ?? null);
    }

    /**
     * @param  list<mixed>  $arguments
     */
    private function invoke(AnanasProductReconciliationService $service, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($service, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($service, ...$arguments);
    }
}
