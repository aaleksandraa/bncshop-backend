<?php

namespace App\Services\Menu;

use App\Models\MenuItem;
use Illuminate\Support\Collection;

class MenuTreeBuilder
{
    /**
     * @param  Collection<int, MenuItem>  $items
     * @return array<int, array<string, mixed>>
     */
    public function build(Collection $items): array
    {
        $grouped = $items->groupBy(fn (MenuItem $item): int => $item->parent_id ?? 0);

        return $this->buildLevel($grouped, 0);
    }

    /**
     * @param  Collection<int, Collection<int, MenuItem>>  $grouped
     * @return array<int, array<string, mixed>>
     */
    private function buildLevel(Collection $grouped, int $parentKey): array
    {
        return ($grouped->get($parentKey) ?? collect())
            ->sortBy('sort_order')
            ->values()
            ->map(fn (MenuItem $item): array => $this->node($item, $grouped))
            ->filter(fn (array $node): bool => $node['url'] !== null)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Collection<int, MenuItem>>  $grouped
     * @return array<string, mixed>
     */
    private function node(MenuItem $item, Collection $grouped): array
    {
        return [
            'id' => $item->id,
            'label' => $item->resolvedLabel(),
            'url' => $item->resolvedUrl(),
            'type' => $item->type,
            'open_in_new_tab' => $item->open_in_new_tab,
            'children' => $this->buildLevel($grouped, $item->id),
        ];
    }
}
