<?php

namespace App\Filament\Resources\CategoryMarginRuleResource\Pages;

use App\Filament\Concerns\SyncsMarginCategoryTargets;
use App\Filament\Resources\CategoryMarginRuleResource;
use App\Services\Pricing\ProductPriceRecalculator;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCategoryMarginRule extends EditRecord
{
    use SyncsMarginCategoryTargets;

    protected static string $resource = CategoryMarginRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['target_category_ids'] = $this->record->targetCategories()->pluck('categories.id')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractTargetCategoryIds($data);
    }

    protected function afterSave(): void
    {
        $this->syncTargetCategories();
        $this->recalculatePrices();
    }

    private function recalculatePrices(): void
    {
        $count = app(ProductPriceRecalculator::class)->forCategoryMarginRule($this->record);

        Notification::make()
            ->title('Cijene preračunate')
            ->body("Ažurirano {$count} A1 proizvoda (nova roba).")
            ->success()
            ->send();
    }
}
