<?php

namespace App\Filament\Pages;

use App\Services\Ananas\AnanasApiClient;
use App\Services\Ananas\AnanasEligibilityReporter;
use App\Services\Ananas\AnanasProductTypeSyncService;
use App\Services\Ananas\AnanasSyncSettings;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\HtmlString;

class AnanasSyncSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Ananas';

    protected static ?string $navigationLabel = 'Postavke';

    protected static ?string $title = 'Ananas export — postavke';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.ananas-sync-settings';

    public ?array $data = [];

    /** @var array<string, mixed> */
    public array $status = [];

    /** @var array<string, mixed> */
    public array $eligibilitySummary = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->hasRole(['Super Admin', 'Admin'])
            || $user->can('manage_sync')
            || $user->can('api_sources.update')
        );
    }

    public function mount(AnanasSyncSettings $settings, AnanasEligibilityReporter $reporter): void
    {
        $this->status = $settings->status();
        $settings->resolveSource();
        $this->eligibilitySummary = $reporter->summarize();

        $all = $settings->all();
        $credentials = $settings->credentials();
        $all['client_id'] = $credentials['client_id'];
        $all['client_secret'] = '';
        $all['environment'] = $settings->environment();

        $this->form->fill($all);
    }

    public function form(Form $form): Form
    {
        $envLocked = filled(env('ANANAS_ENV'));

        return $form
            ->schema([
                Section::make('API pristup (Ananas Merchant API)')
                    ->description('QA2 Stage i Production koriste različite URL-ove i credentials. Nikad ne miješaj okruženja.')
                    ->schema([
                        Select::make('environment')
                            ->label('Okruženje')
                            ->options([
                                AnanasSyncSettings::ENV_STAGE => 'Stage (QA2)',
                                AnanasSyncSettings::ENV_PRODUCTION => 'Production',
                            ])
                            ->disabled($envLocked)
                            ->dehydrated(! $envLocked)
                            ->helperText($envLocked
                                ? 'Okruženje je fiksirano preko ANANAS_ENV u .env.'
                                : 'Odaberite Stage za sandbox ili Production za live.'),
                        TextInput::make('client_id')
                            ->label('Client ID')
                            ->required(),
                        TextInput::make('client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->helperText('Ostavite prazno da zadržite postojeći secret.')
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        Placeholder::make('endpoint_hint')
                            ->label('')
                            ->content(fn (AnanasSyncSettings $settings): HtmlString => new HtmlString(
                                '<p class="text-sm text-gray-500 dark:text-gray-400">Token: <code>'.$settings->tokenEndpointUrl().'</code></p>'
                            ))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Export')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Ananas export uključen'),
                        Toggle::make('allow_catalog_writes')
                            ->label('Dozvoli catalog write API pozive')
                            ->helperText('Ostaje isključeno dok Ananas ne potvrdi BiH VAT i dok mapiranja nisu spremna. Phase 1B ne šalje import automatski.'),
                        Select::make('vat_rate')
                            ->label('Ananas VAT (0 / 10 / 20)')
                            ->options([
                                0 => '0 — finalna BAM cijena već uključuje naš VAT i maržu',
                                10 => '10',
                                20 => '20',
                            ])
                            ->default(0)
                            ->helperText('basePrice = PriceCalculator regularPrice (konačna prodajna cijena u KM). VAT polje na Ananasu ostaje 0.'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(AnanasSyncSettings $settings, AnanasApiClient $client): void
    {
        $state = $this->form->getState();

        $settings->saveCredentials([
            'client_id' => $state['client_id'] ?? null,
            'client_secret' => $state['client_secret'] ?? null,
        ]);

        unset($state['client_id'], $state['client_secret']);

        if (! array_key_exists('vat_rate', $state) || $state['vat_rate'] === '' || $state['vat_rate'] === null) {
            $state['vat_rate'] = 0;
        }

        $settings->save($state);
        $client->clearTokenCache();
        $this->status = $settings->status();

        Notification::make()->title('Ananas postavke sačuvane.')->success()->send();
    }

    public function testConnection(AnanasApiClient $client, AnanasSyncSettings $settings): void
    {
        try {
            $client->authenticate(true);
            $settings->resolveSource()->update([
                'connection_status' => 'connected',
                'last_error' => null,
            ]);

            Notification::make()
                ->title('Ananas konekcija uspješna')
                ->body('Token dobijen za '.$settings->environment().' okruženje.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $settings->resolveSource()->update([
                'connection_status' => 'disconnected',
                'last_error' => $e->getMessage(),
            ]);

            Notification::make()->title('Ananas konekcija neuspješna')->body($e->getMessage())->danger()->send();
        }

        $this->status = $settings->status();
    }

    public function refreshProductTypes(AnanasProductTypeSyncService $syncService): void
    {
        try {
            $types = $syncService->refreshFromApi();

            Notification::make()
                ->title('Ananas product types osvježeni')
                ->body('Pronađeno: '.count($types))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Osvježavanje neuspješno')->body($e->getMessage())->danger()->send();
        }
    }

    public function runEligibilityReport(AnanasEligibilityReporter $reporter): void
    {
        $this->eligibilitySummary = $reporter->summarize();

        Notification::make()
            ->title('Eligibility izvještaj osvježen')
            ->body('Eligible: '.$this->eligibilitySummary['eligible'].' / '.$this->eligibilitySummary['total_scanned'])
            ->success()
            ->send();
    }

    public function runEligibilityReportCommand(): void
    {
        Artisan::call('bnc:ananas-eligibility-report');

        Notification::make()
            ->title('CLI izvještaj')
            ->body(trim(Artisan::output()) ?: 'Gotovo.')
            ->success()
            ->send();
    }
}
