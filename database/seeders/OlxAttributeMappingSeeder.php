<?php

namespace Database\Seeders;

use App\Models\OlxAttributeMapping;
use Illuminate\Database\Seeder;

class OlxAttributeMappingSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<int, array<int, array<string, mixed>>> $definitions */
        $definitions = require __DIR__.'/data/olx_attribute_mappings.php';

        $count = 0;

        foreach ($definitions as $olxCategoryId => $mappings) {
        foreach ($mappings as $mapping) {
            $payload = array_merge([
                'attribute_definition_id' => null,
                'bnc_attribute_aliases' => null,
                'parser_pattern' => null,
                'default_value' => null,
                'value_mappings' => null,
                'is_required_for_publish' => false,
            ], $mapping);

            OlxAttributeMapping::query()->updateOrCreate(
                [
                    'olx_category_id' => (int) $olxCategoryId,
                    'olx_attribute_id' => (int) $mapping['olx_attribute_id'],
                ],
                $payload,
            );
                $count++;
            }
        }

        $this->command?->info("OLX attribute mappings seeded: {$count} across ".count($definitions).' categories.');
    }
}
