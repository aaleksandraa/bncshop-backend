<?php

namespace Tests\Unit;

use App\Services\Olx\OlxChangeDetector;
use App\Services\Olx\OlxExportScope;
use App\Services\Olx\OlxListingMapper;
use Mockery;
use Tests\TestCase;

class OlxChangeDetectorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_returns_empty_buckets_when_no_category_mappings(): void
    {
        $scope = Mockery::mock(OlxExportScope::class);
        $scope->shouldReceive('scopedCategoryIds')->once()->andReturn([]);

        $mapper = Mockery::mock(OlxListingMapper::class);

        $detector = new OlxChangeDetector($scope, $mapper);
        $result = $detector->detect();

        $this->assertSame(0, $result['scanned']);
        $this->assertSame([], $result['create']);
        $this->assertSame([], $result['update']);
        $this->assertSame([], $result['hide']);
        $this->assertSame([], $result['unhide']);
    }
}
