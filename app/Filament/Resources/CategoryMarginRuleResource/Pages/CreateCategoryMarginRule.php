<?php

namespace App\Filament\Resources\CategoryMarginRuleResource\Pages;

use App\Filament\Concerns\SyncsMarginCategoryTargets;
use App\Filament\Resources\CategoryMarginRuleResource;
use App\Services\Pricing\ProductPriceRecalculator;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCategoryMarginRule extends CreateRecord
{
    use SyncsMarginCategoryTargets;

    protected static string $resource = CategoryMarginRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractTargetCategoryIds($data);
    }

    protected function afterCreate(): void
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
