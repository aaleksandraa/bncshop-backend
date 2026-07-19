<?php

namespace App\Filament\Pages;

use App\Services\Commerce\InstallmentSettings;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use InvalidArgumentException;

class InstallmentSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Shop';

    protected static ?string $navigationLabel = 'Kupovina na rate';

    protected static ?string $title = 'Postavke kupovine na rate';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.installment-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->hasRole(['Super Admin', 'Admin'])
            || $user->can('manage_products')
            || $user->can('manage_orders')
        );
    }

    public function mount(InstallmentSettings $settings): void
    {
        $state = $settings->all();
        $state['mikrofin_provision_rate'] = round($state['mikrofin_provision_rate'] * 100, 2);
        $state['mikrofin_interest_rate'] = round($state['mikrofin_interest_rate'] * 100, 2);
        $state['card_markup_rate'] = round($state['card_markup_rate'] * 100, 2);
        $state['shopping_card_banks'] = array_map(
            static fn (string $bank): array => ['name' => $bank],
            $state['shopping_card_banks'] ?? [],
        );

        $this->form->fill($state);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Opće postavke')
                    ->description('Globalna pravila prikaza i izračuna rata na web shopu.')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Omogući kupovinu na rate')
                            ->helperText('Isključite da sakrijete teaser, kalkulator i formu upita.')
                            ->default(true),
                        TextInput::make('min_total_price')
                            ->label('Minimalni ukupni iznos (KM)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('KM'),
                        TextInput::make('max_total_price')
                            ->label('Maksimalni ukupni iznos (KM)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('KM'),
                        TextInput::make('min_installment')
                            ->label('Minimalni mjesečni anuitet')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('KM')
                            ->helperText('Planovi sa nižom ratom se ne prikazuju kupcu.'),
                    ])
                    ->columns(2),
                Section::make('Mikrofin kredit')
                    ->description('Parametri partnerskog proizvoda. Do graničnog broja mjeseci primjenjuje se provizija bez kamate; iznad toga kamata bez provizije.')
                    ->schema([
                        Toggle::make('mikrofin_enabled')
                            ->label('Omogući Mikrofin opciju')
                            ->default(true),
                        TextInput::make('mikrofin_partner_name')
                            ->label('Naziv partnera')
                            ->maxLength(120),
                        TextInput::make('mikrofin_product_name')
                            ->label('Naziv kreditnog proizvoda')
                            ->maxLength(120),
                        TextInput::make('mikrofin_max_months')
                            ->label('Maksimalni rok otplate')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(120)
                            ->suffix('mj.'),
                        TextInput::make('mikrofin_zero_interest_max_months')
                            ->label('Granični rok (provizija / bez kamate)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(120)
                            ->suffix('mj.')
                            ->helperText('Do ovog broja mjeseci: 0% kamata + provizija. Preko: kamata + 0% provizija.'),
                        TextInput::make('mikrofin_provision_rate')
                            ->label('Provizija (do graničnog roka)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                        TextInput::make('mikrofin_interest_rate')
                            ->label('Kamata (preko graničnog roka)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->helperText('Godišnja nominalna kamata — anuitetna otplata.'),
                        Textarea::make('mikrofin_description')
                            ->label('Dodatni opis (opcionalno)')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Shopping / kreditne kartice')
                    ->description('Kupovina na rate putem bankarskih kartica — cijena se uvećava za zadani postotak.')
                    ->schema([
                        Toggle::make('shopping_card_enabled')
                            ->label('Omogući shopping kartice')
                            ->default(true),
                        TextInput::make('card_markup_rate')
                            ->label('Uvećanje cijene')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->helperText('Primjenjuje se na web cijenu proizvoda.'),
                        TextInput::make('card_months')
                            ->label('Broj rata za prikaz')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(120)
                            ->suffix('mj.'),
                        Repeater::make('shopping_card_banks')
                            ->label('Podržane kartice / banke')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Naziv')
                                    ->required()
                                    ->maxLength(120),
                            ])
                            ->defaultItems(1)
                            ->reorderable()
                            ->columnSpanFull(),
                        Textarea::make('card_description')
                            ->label('Dodatni opis (opcionalno)')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Kontakt za upite')
                    ->description('Prikazuje se na stranici kupovine na rate.')
                    ->schema([
                        TextInput::make('contact_phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(40),
                        TextInput::make('contact_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(120),
                    ])
                    ->columns(2),
                Section::make('Pregled pravila')
                    ->description('Sažetak trenutno unesenih vrijednosti prije spremanja.')
                    ->schema([
                        TextInput::make('rules_preview')
                            ->label('Sažetak')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(function (Get $get): string {
                                $min = $get('min_total_price');
                                $max = $get('max_total_price');
                                $threshold = $get('mikrofin_zero_interest_max_months');
                                $provision = $get('mikrofin_provision_rate');
                                $interest = $get('mikrofin_interest_rate');
                                $cardMarkup = $get('card_markup_rate');
                                $cardMonths = $get('card_months');

                                return implode(' | ', array_filter([
                                    is_numeric($min) && is_numeric($max)
                                        ? "Raspon: {$min} – {$max} KM"
                                        : null,
                                    is_numeric($threshold) && is_numeric($provision) && is_numeric($interest)
                                        ? "Mikrofin do {$threshold} mj.: 0% kamata, {$provision}% provizija; preko {$threshold} mj.: {$interest}% kamata, 0% provizija"
                                        : null,
                                    is_numeric($cardMarkup) && is_numeric($cardMonths)
                                        ? "Kartice: +{$cardMarkup}% / {$cardMonths} rata"
                                        : null,
                                ]));
                            })
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(InstallmentSettings $settings): void
    {
        $state = $this->form->getState();

        $state['mikrofin_provision_rate'] = ((float) ($state['mikrofin_provision_rate'] ?? 0)) / 100;
        $state['mikrofin_interest_rate'] = ((float) ($state['mikrofin_interest_rate'] ?? 0)) / 100;
        $state['card_markup_rate'] = ((float) ($state['card_markup_rate'] ?? 0)) / 100;
        $state['shopping_card_banks'] = array_values(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['name'] ?? '')),
            $state['shopping_card_banks'] ?? [],
        )));

        try {
            $settings->save($state);
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title('Greška pri spremanju')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Postavke kupovine na rate sačuvane')
            ->success()
            ->send();
    }
}
