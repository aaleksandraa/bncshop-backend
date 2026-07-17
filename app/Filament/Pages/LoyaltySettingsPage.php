<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\CanAccessLoyalty;
use App\Services\Loyalty\LoyaltySettings;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class LoyaltySettingsPage extends Page implements HasForms
{
    use CanAccessLoyalty;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'BNC bodovi';

    protected static ?string $title = 'Postavke BNC bodova';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.loyalty-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return static::canAccessLoyalty();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccessLoyalty();
    }

    public function mount(LoyaltySettings $settings): void
    {
        $this->form->fill($settings->all());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Program')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Uključi program'),
                        TextInput::make('program_name')
                            ->label('Naziv programa')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('program_description')
                            ->label('Opis na shopu')
                            ->rows(3),
                        DateTimePicker::make('starts_at')
                            ->label('Početak')
                            ->native(false),
                        DateTimePicker::make('ends_at')
                            ->label('Kraj')
                            ->native(false),
                        TextInput::make('points_per_km')
                            ->label('Bodova po 1 KM')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->step(0.01),
                    ])
                    ->columns(2),
                Section::make('Pravila')
                    ->schema([
                        Toggle::make('combine_with_coupons')
                            ->label('Kombinuj s kuponima'),
                        Toggle::make('combine_with_discounts')
                            ->label('Kombinuj s automatskim popustima'),
                        Toggle::make('guest_registration_prompt')
                            ->label('Poziv gostima na registraciju'),
                        Select::make('expiry_mode')
                            ->label('Istek bodova')
                            ->options([
                                'never' => 'Nikad',
                                'program_end' => 'Na kraju programa',
                                'months_after_earn' => 'X mjeseci nakon zarade',
                            ])
                            ->required()
                            ->live(),
                        TextInput::make('expiry_months')
                            ->label('Mjeseci do isteka')
                            ->numeric()
                            ->minValue(1)
                            ->visible(fn ($get): bool => $get('expiry_mode') === 'months_after_earn'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(LoyaltySettings $settings): void
    {
        if (! static::canAccessLoyalty(requireUpdate: true)) {
            Notification::make()
                ->title('Nemate dozvolu za izmjenu postavki.')
                ->danger()
                ->send();

            return;
        }

        $settings->save($this->form->getState());

        Notification::make()
            ->title('Postavke sačuvane.')
            ->success()
            ->send();
    }

    public function canEditSettings(): bool
    {
        return static::canAccessLoyalty(requireUpdate: true);
    }
}
