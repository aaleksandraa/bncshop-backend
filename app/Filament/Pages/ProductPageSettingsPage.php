<?php

namespace App\Filament\Pages;

use App\Services\Catalog\ProductPageSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ProductPageSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Shop';

    protected static ?string $navigationLabel = 'Stranica proizvoda';

    protected static ?string $title = 'Postavke stranice proizvoda';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.product-page-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_products') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(ProductPageSettings $settings): void
    {
        $this->form->fill($settings->all());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Prikaz sadržaja')
                    ->description('Upravljajte elementima koji se prikazuju na stranici pojedinačnog proizvoda.')
                    ->schema([
                        Toggle::make('show_short_description')
                            ->label('Prikaži kratki opis')
                            ->helperText('Kratki opis se prikazuje ispod oznaka (Novo, garancija, zaliha) i iznad cijene.')
                            ->default(true),
                        Toggle::make('show_messaging_order_buttons')
                            ->label('Viber i WhatsApp pored „Kupi odmah“')
                            ->helperText('Prikazuje ikonice Viber i WhatsApp pored dugmeta „Kupi odmah“ s porukom „Želim naručiti [proizvod]“ prema mobilnom broju iz kontakt postavki.')
                            ->default(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(ProductPageSettings $settings): void
    {
        if (! static::canAccess()) {
            Notification::make()
                ->title('Nemate dozvolu za izmjenu postavki.')
                ->danger()
                ->send();

            return;
        }

        $settings->save($this->form->getState());

        $this->form->fill($settings->all());

        Notification::make()
            ->title('Postavke stranice proizvoda su sačuvane.')
            ->success()
            ->send();
    }
}
