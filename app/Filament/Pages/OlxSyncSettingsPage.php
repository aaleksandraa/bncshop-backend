<?php

namespace App\Filament\Pages;

use App\Jobs\RunOlxSyncJob;
use App\Services\Olx\OlxApiClient;
use App\Services\Olx\OlxCategoryDiscoveryService;
use App\Services\Olx\OlxExistingListingImporter;
use App\Services\Olx\OlxSyncSettings;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\HtmlString;

class OlxSyncSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'OLX';

    protected static ?string $navigationLabel = 'Postavke';

    protected static ?string $title = 'OLX export — postavke';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.olx-sync-settings';

    public ?array $data = [];

    /** @var array<string, mixed> */
    public array $status = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->hasRole(['Super Admin', 'Admin'])
            || $user->can('manage_sync')
            || $user->can('api_sources.update')
        );
    }

    public function mount(OlxSyncSettings $settings): void
    {
        $this->status = $settings->status();
        $settings->resolveSource();

        $all = $settings->all();
        $credentials = $settings->credentials();
        $all['sync_times'] = array_map(
            static fn (string $time): array => ['time' => $time],
            $all['sync_times'] ?? [],
        );
        $all['api_base_url'] = $credentials['base_url'];
        $all['api_username'] = $credentials['username'];
        $all['api_password'] = '';
        $all['device_name'] = $credentials['device_name'];

        $this->form->fill($all);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('API pristup (OLX.ba)')
                    ->description('Kredencijali za shop nalog na api.olx.ba. Ako su postavljeni ovdje, imaju prednost nad .env varijablama.')
                    ->schema([
                        TextInput::make('api_base_url')
                            ->label('API URL')
                            ->url()
                            ->required()
                            ->default('https://api.olx.ba')
                            ->columnSpanFull(),
                        TextInput::make('api_username')
                            ->label('Korisničko ime (shop nalog)')
                            ->required()
                            ->placeholder('bnc'),
                        TextInput::make('api_password')
                            ->label('Lozinka')
                            ->password()
                            ->revealable()
                            ->helperText('Ostavite prazno da zadržite postojeću lozinku.')
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        TextInput::make('device_name')
                            ->label('Naziv uređaja / integracije')
                            ->default('bncshopweb_integration')
                            ->helperText('OLX API device_name parametar pri loginu.'),
                        Placeholder::make('credentials_hint')
                            ->label('')
                            ->content(fn (): HtmlString => new HtmlString(
                                '<p class="text-sm text-gray-500 dark:text-gray-400">Alternativa: postavite <code>OLX_USERNAME</code> i <code>OLX_PASSWORD</code> u <code>.env</code> na serveru ako ne želite čuvati lozinku u bazi.</p>'
                            ))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Export')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('OLX export uključen'),
                        Toggle::make('auto_sync_enabled')
                            ->label('Automatski sync (scheduler)'),
                        Repeater::make('sync_times')
                            ->label('Vremena sync-a (HH:MM)')
                            ->simple(TextInput::make('time')->placeholder('06:00')),
                        TextInput::make('batch_size')
                            ->label('Batch veličina')
                            ->numeric()
                            ->default(20),
                        TextInput::make('daily_create_limit')
                            ->label('Dnevni limit novih OLX objava')
                            ->numeric()
                            ->default(350)
                            ->helperText('OLX API limit je 350 objava po danu (provjereno).'),
                        TextInput::make('max_creates_per_run')
                            ->label('Max novih objava po sync run-u')
                            ->numeric()
                            ->default(150)
                            ->helperText('Inkrementalni sync kreira najviše ovoliko novih listinga po pokretanju.'),
                    ])
                    ->columns(2),
                Section::make('Lokacija oglasa')
                    ->schema([
                        TextInput::make('country_id')->numeric()->label('Država (OLX ID)'),
                        TextInput::make('city_id')->numeric()->label('Grad (OLX ID)'),
                        TextInput::make('location_lat')->label('Geografska širina'),
                        TextInput::make('location_lon')->label('Geografska dužina'),
                        TextInput::make('listing_type')->label('Tip oglasa')->default('sell'),
                        TextInput::make('shipping')->label('Dostava')->default('no_shipping'),
                    ])
                    ->columns(2),
                Section::make('Opis footer')
                    ->schema([
                        RichEditor::make('description_footer')
                            ->label('Tekst ispod opisa proizvoda')
                            ->helperText('Podržava bold, linkove i liste. HTML se prenosi na OLX opis.')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'link',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'undo',
                                'redo',
                            ])
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(OlxSyncSettings $settings, OlxApiClient $client): void
    {
        $state = $this->form->getState();

        $settings->saveCredentials([
            'base_url' => $state['api_base_url'] ?? null,
            'username' => $state['api_username'] ?? null,
            'password' => $state['api_password'] ?? null,
        ]);

        unset($state['api_base_url'], $state['api_username'], $state['api_password']);

        $state['sync_times'] = collect($state['sync_times'] ?? [])
            ->pluck('time')
            ->filter()
            ->values()
            ->all();

        $settings->save($state);
        $settings->resolveSource()->update(['auto_sync_enabled' => (bool) ($state['auto_sync_enabled'] ?? false)]);

        $client->clearTokenCache();
        $this->status = $settings->status();

        Notification::make()->title('OLX postavke sačuvane.')->success()->send();
    }

    public function testConnection(OlxApiClient $client, OlxSyncSettings $settings): void
    {
        try {
            $client->authenticate(true);
            $me = $client->me();
            $settings->resolveSource()->update([
                'connection_status' => 'connected',
                'last_error' => null,
            ]);

            Notification::make()
                ->title('OLX konekcija uspješna')
                ->body('Nalog: '.($me['username'] ?? $settings->credentials()['username']))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $settings->resolveSource()->update([
                'connection_status' => 'disconnected',
                'last_error' => $e->getMessage(),
            ]);

            Notification::make()->title('OLX konekcija neuspješna')->body($e->getMessage())->danger()->send();
        }

        $this->status = $settings->status();
    }

    public function discoverCategories(OlxCategoryDiscoveryService $discovery): void
    {
        $result = $discovery->discoverCategories();
        Notification::make()
            ->title('OLX kategorije osvježene')
            ->body("Pronađeno: {$result['discovered']}")
            ->success()
            ->send();
    }

    public function discoverAttributes(OlxCategoryDiscoveryService $discovery): void
    {
        $results = $discovery->discoverAllAttributes();
        $total = array_sum(array_column($results, 'attributes'));
        Notification::make()
            ->title('OLX atributi osvježeni')
            ->body("Ukupno: {$total}")
            ->success()
            ->send();
    }

    public function importExistingListings(OlxExistingListingImporter $importer): void
    {
        $result = $importer->import();
        Notification::make()
            ->title('Legacy oglasi uvezeni')
            ->body("Import: {$result['imported']}, match: {$result['matched']}")
            ->success()
            ->send();
    }

    public function seedMappings(): void
    {
        try {
            Artisan::call('bnc:olx-seed-mappings');

            Notification::make()
                ->title('OLX mapiranja učitana')
                ->body(trim(Artisan::output()) ?: 'Kategorije i atributi (15 OLX kategorija) su učitani.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Seed mapiranja neuspješan')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function runIncrementalSync(): void
    {
        RunOlxSyncJob::dispatch(false, null);
        Notification::make()->title('Inkrementalni OLX sync pokrenut.')->success()->send();
        $this->status = app(OlxSyncSettings::class)->status();
    }

    public function runFullSync(): void
    {
        RunOlxSyncJob::dispatch(true, null);
        Notification::make()->title('Puni OLX sync pokrenut.')->success()->send();
        $this->status = app(OlxSyncSettings::class)->status();
    }

    public function runSyncNow(): void
    {
        RunOlxSyncJob::dispatch(false, null);
        Notification::make()
            ->title('OLX sync pokrenut u pozadini')
            ->body('Sync se izvršava preko queue-a. Pratite status u kartici „Zadnji job“ ili u Import jobovima.')
            ->success()
            ->send();
        $this->status = app(OlxSyncSettings::class)->status();
    }
}
