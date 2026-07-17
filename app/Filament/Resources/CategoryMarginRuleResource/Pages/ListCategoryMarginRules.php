<?php

namespace App\Filament\Resources\CategoryMarginRuleResource\Pages;

use App\Filament\Resources\CategoryMarginRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategoryMarginRules extends ListRecords
{
    protected static string $resource = CategoryMarginRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
