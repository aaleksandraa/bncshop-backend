<?php

namespace App\Filament\Pages;

use App\Services\Integrations\TrackingSettings;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class TrackingSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Integracije';

    protected static ?string $navigationLabel = 'Analitika i kolačići';

    protected static ?string $title = 'Google Analytics, Meta Pixel i saglasnost';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.tracking-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->hasRole(['Super Admin', 'Admin']) || $user->can('customers.update'));
    }

    public function mount(TrackingSettings $settings): void
    {
        $this->form->fill($settings->all());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Banner saglasnosti (prva posjeta)')
                    ->schema([
                        Toggle::make('consent_enabled')
                            ->label('Prikaži banner za kolačiće')
                            ->default(true),
                        TextInput::make('consent_title')
                            ->label('Naslov bannera')
                            ->maxLength(255),
                        Textarea::make('consent_message')
                            ->label('Tekst bannera')
                            ->rows(4),
                        TextInput::make('privacy_page_slug')
                            ->label('Slug stranice privatnosti')
                            ->helperText('Npr. privatnost → /stranica/privatnost')
                            ->maxLength(120),
                        Toggle::make('load_scripts_only_with_consent')
                            ->label('Učitaj GA/Pixel tek nakon prihvatanja')
                            ->default(true),
                    ])
                    ->columns(1),
                Section::make('Praćenje (javni ID-evi)')
                    ->description('Measurement ID i Pixel ID su javni. Purchase se šalje i sa servera (API secret) da narudžbe uđu u Analytics i kad kupac odbije neobavezne kolačiće.')
                    ->schema([
                        TextInput::make('ga_measurement_id')
                            ->label('Google Analytics 4 Measurement ID')
                            ->placeholder('G-XXXXXXXXXX')
                            ->maxLength(32),
                        TextInput::make('ga_api_secret')
                            ->label('GA4 Measurement Protocol API secret')
                            ->password()
                            ->revealable()
                            ->helperText('Admin → Data streams → Measurement Protocol API secrets. Purchase ide u Analytics i kad kupac odbije neobavezne kolačiće. Ne prikazuje se na shopu.')
                            ->maxLength(64),
                        TextInput::make('fb_pixel_id')
                            ->label('Meta (Facebook) Pixel ID')
                            ->placeholder('123456789012345')
                            ->maxLength(32),
                    ])
                    ->columns(2),
                Section::make('Meta Conversions API (server)')
                    ->description('Dataset ID i access token za server-side događaje (Purchase, Lead). Purchase se šalje pri narudžbi; Lead pri upitu za rate. Koristi isti dataset kao Pixel u Events Manageru.')
                    ->schema([
                        TextInput::make('fb_dataset_id')
                            ->label('Meta Dataset ID')
                            ->placeholder('786294308773690')
                            ->helperText('Ako ostane prazno, koristi se Pixel ID.')
                            ->maxLength(32),
                        TextInput::make('fb_access_token')
                            ->label('Meta Conversions API access token')
                            ->password()
                            ->revealable()
                            ->helperText('Events Manager → Dataset → Settings → Generate access token. Ne prikazuje se na shopu.')
                            ->maxLength(512),
                        TextInput::make('fb_test_event_code')
                            ->label('Test event code (opcionalno)')
                            ->helperText('Za test događaje u Events Manager → Test Events tab.')
                            ->maxLength(64),
                        TextInput::make('fb_crm_name')
                            ->label('CRM naziv (lead_event_source)')
                            ->default('BNC Shop')
                            ->helperText('Prikazuje se u Meta CRM integraciji za Lead događaje.')
                            ->maxLength(120),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(TrackingSettings $settings): void
    {
        $settings->save($this->form->getState());

        Notification::make()
            ->title('Postavke analitike sačuvane')
            ->success()
            ->send();
    }
}
