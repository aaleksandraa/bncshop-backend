<?php

namespace App\Filament\Forms;

use App\Support\CategoryAdminSearch;
use Filament\Forms\Components\Select;

class CategoryMappingSelect
{
    public static function make(string $name = 'category_id'): Select
    {
        return Select::make($name)
            ->searchable()
            ->searchDebounce(300)
            ->getSearchResultsUsing(fn (string $search): array => CategoryAdminSearch::optionsForSearch($search))
            ->getOptionLabelUsing(fn ($value): ?string => CategoryAdminSearch::labelForId($value))
            ->helperText('Upišite naziv kategorije — prikazuje se da li je glavna ili podkategorija, putanja i broj proizvoda.');
    }
}
