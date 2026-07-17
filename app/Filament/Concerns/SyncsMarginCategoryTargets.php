<?php

namespace App\Filament\Concerns;

trait SyncsMarginCategoryTargets
{
    /** @var array<int, int|string>|null */
    protected ?array $pendingTargetCategoryIds = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractTargetCategoryIds(array $data): array
    {
        $this->pendingTargetCategoryIds = array_map(
            'intval',
            (array) ($data['target_category_ids'] ?? []),
        );
        unset($data['target_category_ids']);

        return $data;
    }

    protected function syncTargetCategories(): void
    {
        if (! isset($this->record) || $this->pendingTargetCategoryIds === null) {
            return;
        }

        if (($this->record->subcategory_scope ?? 'category_only') === 'selected') {
            $this->record->targetCategories()->sync($this->pendingTargetCategoryIds);
        } else {
            $this->record->targetCategories()->sync([]);
        }
    }
}
