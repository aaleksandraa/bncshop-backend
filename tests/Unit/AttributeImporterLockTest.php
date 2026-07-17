<?php

namespace Tests\Unit;

use App\Models\AttributeDefinition;
use App\Services\Sync\AttributeImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeImporterLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_does_not_overwrite_locked_public_visibility(): void
    {
        $definition = AttributeDefinition::query()->create([
            'external_attribute_id' => 'locked-attr',
            'name' => 'LOCKED',
            'internal_type' => 'text',
            'is_public' => false,
            'is_public_locked' => true,
        ]);

        app(AttributeImporter::class)->upsertOne([
            'productAttributeDefinitionId' => 'locked-attr',
            'name' => 'LOCKED',
            'isPublic' => true,
            'isFilter' => false,
        ]);

        $definition->refresh();

        $this->assertFalse($definition->is_public);
        $this->assertTrue($definition->is_public_locked);
    }

    public function test_sync_updates_public_visibility_when_not_locked(): void
    {
        $definition = AttributeDefinition::query()->create([
            'external_attribute_id' => 'unlocked-attr',
            'name' => 'UNLOCKED',
            'internal_type' => 'text',
            'is_public' => false,
            'is_public_locked' => false,
        ]);

        app(AttributeImporter::class)->upsertOne([
            'productAttributeDefinitionId' => 'unlocked-attr',
            'name' => 'UNLOCKED',
            'isPublic' => true,
            'isFilter' => false,
        ]);

        $definition->refresh();

        $this->assertTrue($definition->is_public);
    }
}
