<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\CategorySeo;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['system'] = false;
        $data['external_category_id'] = $data['external_category_id'] ?? (string) Str::uuid();
        $data['status'] = $data['status'] ?? 'active';

        return $data;
    }

    protected function afterCreate(): void
    {
        CategorySeo::query()->firstOrCreate(['category_id' => $this->record->id]);
    }
}
