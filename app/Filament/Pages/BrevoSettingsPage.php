<?php

namespace App\Filament\Pages;

use App\Services\Marketing\BrevoService;
use App\Services\Marketing\BrevoSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class BrevoSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?string $navigationGroup = 'Integracije';

    protected static ?string $navigationLabel = 'Brevo';

    protected static ?string $title = 'Brevo e-mail integracija';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.brevo-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->hasRole(['Super Admin', 'Admin']) || $user->can('customers.update'));
    }

    public function mount(BrevoSettings $settings): void
    {
        $this->form->fill($settings->all());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Povezivanje')
                    ->description('Koristite Brevo API ključ iz Brevo → SMTP & API → API Keys.')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Uključi Brevo integraciju')
                            ->live(),
                        TextInput::make('api_key')
                            ->label('API ključ')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                        TextInput::make('sender_email')
                            ->label('Pošiljalac e-mail')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('sender_name')
                            ->label('Ime pošiljaoca')
                            ->maxLength(255)
                            ->default('BNC Shop'),
                        TextInput::make('default_list_id')
                            ->label('Brevo lista ID (opcionalno)')
                            ->numeric()
                            ->helperText('Kontakti se dodaju u ovu listu pri sinhronizaciji.'),
                    ])
                    ->columns(2),
                Section::make('Automatska sinhronizacija')
                    ->schema([
                        Toggle::make('sync_on_order')
                            ->label('Sinhronizuj nakon narudžbe'),
                        Toggle::make('sync_registered')
                            ->label('Sinhronizuj nakon registracije'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(BrevoSettings $settings): void
    {
        $settings->save($this->form->getState());

        Notification::make()
            ->title('Brevo postavke sačuvane.')
            ->success()
            ->send();
    }

    public function testConnection(BrevoSettings $settings, BrevoService $brevo): void
    {
        $settings->save($this->form->getState());

        if ($brevo->testConnection()) {
            Notification::make()
                ->title('Brevo veza uspješna.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Brevo veza nije uspjela. Provjerite API ključ.')
            ->danger()
            ->send();
    }
}
