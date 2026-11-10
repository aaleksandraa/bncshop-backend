<?php

namespace Tests\Unit\Ananas;

use App\Models\AnanasProductMapping;
use Tests\TestCase;

class AnanasRemoteEnvironmentTest extends TestCase
{
    public function test_stage_linked_product_can_be_exported_again_on_production(): void
    {
        $mapping = new AnanasProductMapping([
            'local_status' => AnanasProductMapping::LOCAL_LINKED,
            'remote_environment' => 'stage',
        ]);

        $this->assertTrue($mapping->blocksAnotherExport('stage'));
        $this->assertFalse($mapping->blocksAnotherExport('production'));
    }

    public function test_production_submission_blocks_another_production_export(): void
    {
        $mapping = new AnanasProductMapping([
            'local_status' => AnanasProductMapping::LOCAL_SUBMITTED,
            'remote_environment' => 'production',
        ]);

        $this->assertTrue($mapping->blocksAnotherExport('production'));
        $this->assertFalse($mapping->blocksAnotherExport('stage'));
    }
}
