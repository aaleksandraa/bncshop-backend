<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\CategorySeo;
use App\Services\Catalog\CategoryFilterLayoutService;
use App\Services\Catalog\ProductReadCache;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record->seo === null) {
            CategorySeo::query()->create(['category_id' => $this->record->id]);
            $this->record->unsetRelation('seo');
            $this->record->load('seo');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn ($record) => ! $record->system),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['filter_layout'] = app(CategoryFilterLayoutService::class)
            ->buildFormLayout($this->record);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! array_key_exists('filter_layout', $data)) {
            return $data;
        }

        $layout = app(CategoryFilterLayoutService::class)
            ->applyLayoutToCategory($this->record, $data['filter_layout']);

        $data['filter_layout'] = $layout;

        foreach (CategoryFilterLayoutService::STANDARD_FILTERS as $key => $meta) {
            $data[$meta['column']] = (bool) $this->record->{$meta['column']};
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (is_array($this->record->filter_layout)) {
            app(CategoryFilterLayoutService::class)
                ->syncAttributeMappings($this->record, $this->record->filter_layout);
        }

        app(ProductReadCache::class)->flushListAndFilters($this->record->id);
    }
}
