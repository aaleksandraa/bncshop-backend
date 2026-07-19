<?php

namespace App\Filament\Pages;

use App\Services\Commerce\CheckoutSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CheckoutSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Shop';

    protected static ?string $navigationLabel = 'Checkout';

    protected static ?string $title = 'Postavke checkouta';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.checkout-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->hasRole(['Super Admin', 'Admin']) || $user->can('manage_products'));
    }

    public function mount(CheckoutSettings $settings): void
    {
        $this->form->fill($settings->all());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Gost i registracija')
                    ->schema([
                        Toggle::make('guest_checkout_enabled')
                            ->label('Dozvoli checkout bez prijave')
                            ->default(true),
                        Toggle::make('guest_registration_prompt_checkout')
                            ->label('Prikaži opciju kreiranja profila na checkoutu')
                            ->helperText('Gost može označiti da želi korisnički račun pri slanju narudžbe.')
                            ->default(true),
                    ])
                    ->columns(1),
                Section::make('Uslovi i privatnost')
                    ->description('Linkovi na CMS stranice koje se prikazuju pri potvrdi narudžbe.')
                    ->schema([
                        TextInput::make('terms_page_slug')
                            ->label('Slug stranice uslova')
                            ->helperText('Npr. uslovi → /uslovi')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('privacy_page_slug')
                            ->label('Slug stranice privatnosti')
                            ->helperText('Npr. privatnost → /privatnost')
                            ->required()
                            ->maxLength(120),
                        Toggle::make('terms_default_checked')
                            ->label('Checkbox uslova označen po defaultu')
                            ->helperText('Korisnik i dalje mora imati mogućnost da ga odznači prije slanja.')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(CheckoutSettings $settings): void
    {
        $settings->save($this->form->getState());

        Notification::make()
            ->title('Postavke checkouta sačuvane')
            ->success()
            ->send();
    }
}
