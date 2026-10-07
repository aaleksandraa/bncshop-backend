<?php

namespace App\Services\Ananas;

use App\Models\AnanasCategoryMapping;

class AnanasBatchMappingEnabler
{
    /**
     * @return array{
     *     enabled: list<array{mapping_id: int, category_id: int, bnc_category: string, ananas_category: string, already: bool}>,
     *     skipped: list<string>
     * }
     */
    public function enable(bool $dryRun = false): array
    {
        $allow = $this->normalizeList(config('bnc.ananas_stage_batch_enable_categories', []));
        $deny = $this->normalizeList(config('bnc.ananas_stage_batch_never_enable_categories', []));
        $validatedCategories = ['Gaming laptopi', 'Nosači za televizor'];

        $enabled = [];
        $skipped = [];

        $mappings = AnanasCategoryMapping::query()->with('category')->orderBy('id')->get();

        foreach ($mappings as $mapping) {
            if (! $mapping instanceof AnanasCategoryMapping) {
                continue;
            }

            $ananas = trim((string) $mapping->ananas_category);
            $bnc = trim((string) ($mapping->category?->display_name ?: $mapping->category?->name ?: ''));

            if ($ananas === '' || in_array($ananas, $deny, true)) {
                if (in_array($ananas, $deny, true) && $mapping->is_enabled) {
                    $skipped[] = sprintf(
                        'Mapping #%d „%s“ je na never-enable listi — ostavljeno kako jeste (uključeno).',
                        $mapping->id,
                        $ananas,
                    );
                }

                continue;
            }

            if (! in_array($ananas, $allow, true)) {
                continue;
            }

            $isValidatedLeaf = in_array($ananas, $validatedCategories, true);
            $isOneToOne = strcasecmp($bnc, $ananas) === 0;

            if (! $isValidatedLeaf && ! $isOneToOne) {
                $skipped[] = sprintf(
                    'Mapping #%d preskočen: BNC „%s“ ≠ Ananas „%s“ (nije 1:1).',
                    $mapping->id,
                    $bnc !== '' ? $bnc : (string) $mapping->category_id,
                    $ananas,
                );

                continue;
            }

            $already = (bool) $mapping->is_enabled;

            if (! $dryRun && ! $already) {
                $mapping->update(['is_enabled' => true]);
            }

            $enabled[] = [
                'mapping_id' => (int) $mapping->id,
                'category_id' => (int) $mapping->category_id,
                'bnc_category' => $bnc !== '' ? $bnc : (string) $mapping->category_id,
                'ananas_category' => $ananas,
                'already' => $already,
            ];
        }

        return compact('enabled', 'skipped');
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function normalizeList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $names = [];

        foreach ($value as $item) {
            $name = trim((string) $item);

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }
}
