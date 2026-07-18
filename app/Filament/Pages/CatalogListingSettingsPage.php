<?php

namespace App\Filament\Pages;

use App\Services\Catalog\CatalogListingSettings;
use App\Services\Catalog\ProductReadCache;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CatalogListingSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Shop';

    protected static ?string $navigationLabel = 'Katalog';

    protected static ?string $title = 'Postavke kataloga';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.catalog-listing-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_products') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(CatalogListingSettings $settings): void
    {
        $this->form->fill($settings->all());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Vidljivost proizvoda')
                    ->description('Upravljajte prikazom proizvoda u katalogu, pretrazi i filterima.')
                    ->schema([
                        Toggle::make('hide_out_of_stock_refurbished_eline')
                            ->label('Sakrij refurbished i eLine proizvode bez zaliha')
                            ->helperText('Refurbished i eLine artikli sa stanjem 0 se ne prikazuju u katalogu, pretrazi i filterima. Direktan link na proizvod i dalje radi.')
                            ->default(true),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(CatalogListingSettings $settings, ProductReadCache $productReadCache): void
    {
        if (! static::canAccess()) {
            Notification::make()
                ->title('Nemate dozvolu za izmjenu postavki.')
                ->danger()
                ->send();

            return;
        }

        $settings->save($this->form->getState());
        $productReadCache->flushListAndFilters();

        $this->form->fill($settings->all());

        Notification::make()
            ->title('Postavke kataloga su sačuvane.')
            ->success()
            ->send();
    }
}
