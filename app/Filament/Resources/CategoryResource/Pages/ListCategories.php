<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected ?string $heading = 'Kategorije';

    protected ?string $subheading = 'Uredite naziv prikaza na shopu, kratki opis i SEO za svaku kategoriju.';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nova kategorija'),
        ];
    }
}
