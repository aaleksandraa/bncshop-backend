<?php

namespace Database\Seeders;

use App\Models\B2bAttributeDefinition;
use App\Models\B2bAttributeOption;
use App\Models\B2bCategory;
use App\Services\B2b\B2bProductAttributeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class B2bCategoryAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'monitor' => [
                ['name' => 'Veličina (dijagonala)', 'slug' => 'velicina', 'input_type' => B2bAttributeDefinition::INPUT_SELECT, 'sort_order' => 1],
                ['name' => 'Rezolucija', 'slug' => 'rezolucija', 'input_type' => B2bAttributeDefinition::INPUT_SELECT, 'sort_order' => 2],
                ['name' => 'Proizvođač', 'slug' => 'proizvodjac', 'input_type' => B2bAttributeDefinition::INPUT_SELECT, 'sort_order' => 3],
                [
                    'name' => 'Priključci',
                    'slug' => 'prikljucci',
                    'input_type' => B2bAttributeDefinition::INPUT_MULTISELECT,
                    'sort_order' => 4,
                    'options' => ['HDMI', 'DisplayPort', 'USB', 'LAN'],
                ],
            ],
            'racunar' => [
                ['name' => 'Brend', 'slug' => 'brend', 'input_type' => B2bAttributeDefinition::INPUT_SELECT, 'sort_order' => 1],
                ['name' => 'Model', 'slug' => 'model', 'input_type' => B2bAttributeDefinition::INPUT_TEXT, 'sort_order' => 2],
                ['name' => 'Procesor', 'slug' => 'procesor', 'input_type' => B2bAttributeDefinition::INPUT_SELECT, 'sort_order' => 3],
                ['name' => 'RAM', 'slug' => 'ram', 'input_type' => B2bAttributeDefinition::INPUT_SELECT, 'sort_order' => 4],
                ['name' => 'SSD', 'slug' => 'ssd', 'input_type' => B2bAttributeDefinition::INPUT_SELECT, 'sort_order' => 5],
                ['name' => 'HDD', 'slug' => 'hdd', 'input_type' => B2bAttributeDefinition::INPUT_SELECT, 'sort_order' => 6],
                ['name' => 'Grafika', 'slug' => 'grafika', 'input_type' => B2bAttributeDefinition::INPUT_SELECT, 'sort_order' => 7],
                [
                    'name' => 'Izlazi',
                    'slug' => 'izlazi',
                    'input_type' => B2bAttributeDefinition::INPUT_MULTISELECT,
                    'sort_order' => 8,
                    'options' => ['HDMI', 'DisplayPort', 'USB', 'LAN', 'VGA', 'DVI'],
                ],
            ],
            'laptop' => [
                ['name' => 'Brend', 'slug' => 'brend', 'input_type' => B2bAttributeDefinition::INPUT_SELECT, 'sort_order' => 1],
                ['name' => 'Model', 'slug' => 'model', 'input_type' => B2bAttributeDefinition::INPUT_TEXT, 'sort_order' => 2],
                ['name' => 'Veličina', 'slug' => 'velicina', 'input_type' => B2bAttributeDefinition::INPUT_SELECT, 'sort_order' => 3],
                ['name' => 'Procesor', 'slug' => 'procesor', 'input_type' => B2bAttributeDefinition::INPUT_SELECT, 'sort_order' => 4],
                ['name' => 'RAM', 'slug' => 'ram', 'input_type' => B2bAttributeDefinition::INPUT_SELECT, 'sort_order' => 5],
                ['name' => 'SSD', 'slug' => 'ssd', 'input_type' => B2bAttributeDefinition::INPUT_SELECT, 'sort_order' => 6],
            ],
        ];

        $categoryAliases = [
            'monitor' => ['monitor', 'monitori'],
            'racunar' => ['racunar', 'racunari', 'računar', 'računari'],
            'laptop' => ['laptop', 'laptopi'],
        ];

        foreach ($catalog as $categoryKey => $attributes) {
            $category = $this->resolveCategory($categoryAliases[$categoryKey] ?? [$categoryKey]);

            if ($category === null) {
                continue;
            }

            foreach ($attributes as $attributeData) {
                $definition = B2bAttributeDefinition::query()->updateOrCreate(
                    ['slug' => $attributeData['slug']],
                    [
                        'name' => $attributeData['name'],
                        'input_type' => $attributeData['input_type'],
                        'is_filterable' => true,
                        'is_active' => true,
                        'sort_order' => $attributeData['sort_order'],
                    ],
                );

                $category->attributeDefinitions()->syncWithoutDetaching([
                    $definition->id => ['sort_order' => $attributeData['sort_order']],
                ]);

                foreach ($attributeData['options'] ?? [] as $index => $optionValue) {
                    B2bAttributeOption::query()->firstOrCreate(
                        [
                            'b2b_attribute_definition_id' => $definition->id,
                            'value' => $optionValue,
                        ],
                        ['sort_order' => $index + 1],
                    );
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $aliases
     */
    private function resolveCategory(array $aliases): ?B2bCategory
    {
        $normalized = collect($aliases)
            ->flatMap(fn (string $alias): array => [
                $alias,
                Str::slug($alias),
            ])
            ->unique()
            ->values()
            ->all();

        return B2bCategory::query()
            ->where(function ($query) use ($normalized): void {
                $query->whereIn('slug', $normalized)
                    ->orWhereIn('name', $normalized);
            })
            ->orderBy('id')
            ->first();
    }
}
