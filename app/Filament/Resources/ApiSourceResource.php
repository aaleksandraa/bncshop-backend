<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\ApiSourceResource\Pages;
use App\Filament\Pages\A1SyncSettingsPage;
use App\Filament\Pages\OlxSyncSettingsPage;
use App\Jobs\RunApiSyncJob;
use App\Jobs\RunElineSyncJob;
use App\Models\ApiSource;
use App\Services\Eline\ElineSyncOrchestrator;
use App\Services\Olx\OlxSyncSettings;
use App\Services\Sync\A1SyncSettings;
use App\Services\Sync\IntegrationApiClient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ApiSourceResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = ApiSource::class;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-down';

    protected static ?string $navigationGroup = 'Integracije';

    protected static ?string $modelLabel = 'API izvor';

    protected static ?string $pluralModelLabel = 'API izvori';

    protected static ?int $navigationSort = 1;

    protected static function permissionPrefix(): string
    {
        return 'api_sources';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('target_system_code', '!=', OlxSyncSettings::TARGET_SYSTEM_CODE);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Naziv')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('target_system_code')
                    ->label('Kod sistema')
                    ->maxLength(255),
                Forms\Components\TextInput::make('base_url')
                    ->label('Base URL')
                    ->url()
                    ->required()
                    ->maxLength(2048)
                    ->visible(fn (Get $get, ?ApiSource $record): bool => ($record?->target_system_code ?? $get('target_system_code')) !== 'eline'),
                Forms\Components\TextInput::make('username')
                    ->label('Korisničko ime')
                    ->required(fn (Get $get, ?ApiSource $record, string $operation): bool => $operation === 'create'
                        && ($record?->target_system_code ?? $get('target_system_code')) !== 'eline')
                    ->helperText(fn (?ApiSource $record): ?string => $record?->usesIntegrationApiImport()
                        ? 'A1 Technoshop API korisnik (npr. bnc) — nije email za BNC admin panel.'
                        : null)
                    ->visible(fn (Get $get, ?ApiSource $record): bool => ($record?->target_system_code ?? $get('target_system_code')) !== 'eline'),
                Forms\Components\TextInput::make('password')
                    ->label('Lozinka')
                    ->password()
                    ->revealable()
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (Get $get, ?ApiSource $record, string $operation): bool => $operation === 'create'
                        && ($record?->target_system_code ?? $get('target_system_code')) !== 'eline')
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Ostavite prazno da zadržite postojeću lozinku. Test konekcije koristi sačuvane vrijednosti — prvo kliknite Sačuvaj.'
                        : null)
                    ->visible(fn (Get $get, ?ApiSource $record): bool => ($record?->target_system_code ?? $get('target_system_code')) !== 'eline'),
                Forms\Components\Placeholder::make('eline_credentials_hint')
                    ->label('eLine API pristup')
                    ->content(fn (): HtmlString => new HtmlString(
                        '<p class="text-sm text-gray-600 dark:text-gray-300">eLine <strong>ne koristi</strong> korisničko ime/lozinku iz ovog ekrana. '
                        .'Token i shop kod postavite u <code>.env</code> na serveru:</p>'
                        .'<pre class="mt-2 overflow-x-auto rounded bg-gray-100 p-3 text-xs dark:bg-gray-800">'
                        ."ELINE_API_BASE_URL=".e(config('bnc.eline_api_base_url'))."\n"
                        .'ELINE_API_TOKEN=&lt;token od eLine&gt;'."\n"
                        .'ELINE_API_SHOP_CODE='.e(config('bnc.eline_api_shop_code'))."\n"
                        .'ELINE_API_VERIFY_SSL=false</pre>'
                        .'<p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Nakon izmjene: '
                        .'<code>php artisan config:clear</code> i <code>php artisan config:cache</code>. '
                        .'Test: <code>php artisan bnc:eline-test-connection</code>.</p>'
                    ))
                    ->columnSpanFull()
                    ->visible(fn (Get $get, ?ApiSource $record): bool => ($record?->target_system_code ?? $get('target_system_code')) === 'eline'),
                Forms\Components\TextInput::make('page_size')
                    ->label('Veličina stranice')
                    ->numeric()
                    ->default(500),
                Forms\Components\Placeholder::make('a1_credentials_hint')
                    ->label('A1 API pristup')
                    ->content(fn (): HtmlString => new HtmlString(
                        '<p class="text-sm text-gray-600 dark:text-gray-300">Kredencijale možete unijeti ovdje ili u <code>.env</code> '
                        .'(A1_API_USERNAME, A1_API_PASSWORD) pa pokrenuti '
                        .'<code>php artisan db:seed --class=ApiSourceSeeder</code> na serveru. '
                        .'Sync i test konekcije koriste vrijednosti iz baze, ne direktno iz .env.</p>'
                        .'<p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Tipično korisničko ime: <strong>bnc</strong> '
                        .'(lozinku dobijete od A1 tima).</p>'
                    ))
                    ->columnSpanFull()
                    ->visible(fn (?ApiSource $record): bool => $record?->usesIntegrationApiImport() ?? false),
                Forms\Components\Placeholder::make('a1_sync_hint')
                    ->label('')
                    ->content(fn (): HtmlString => new HtmlString(
                        'Za A1 sync postavke (status, automatski sync, interval) koristite '
                        .'<a href="'.e(A1SyncSettingsPage::getUrl()).'" class="text-primary-600 underline dark:text-primary-400">A1 Technoshop sync</a> stranicu.'
                    ))
                    ->visible(fn (?ApiSource $record): bool => $record?->target_system_code === config('bnc.a1_api_target_system_code', 'bnc-shop')
                        || $record === null),
                Forms\Components\Placeholder::make('olx_sync_hint')
                    ->label('')
                    ->content(fn (): HtmlString => new HtmlString(
                        'OLX kredencijale i export postavke uređujte u '
                        .'<a href="'.e(OlxSyncSettingsPage::getUrl()).'" class="text-primary-600 underline dark:text-primary-400">OLX → Postavke</a>.'
                    ))
                    ->visible(fn (?ApiSource $record): bool => $record?->target_system_code === OlxSyncSettings::TARGET_SYSTEM_CODE),
                Forms\Components\Toggle::make('auto_sync_enabled')
                    ->label('Automatski inkrementalni sync')
                    ->default(true)
                    ->helperText('Scheduler pokreće inkrementalni sync (ModifiedAfter) u zadatom intervalu.')
                    ->visible(fn (Get $get, ?ApiSource $record): bool => ($record?->target_system_code ?? $get('target_system_code')) !== 'eline'),
                Forms\Components\Select::make('interval_preset')
                    ->label('Interval inkrementalnog synca')
                    ->options(fn (): array => app(A1SyncSettings::class)->presetOptions())
                    ->default('60')
                    ->live()
                    ->dehydrated(false)
                    ->helperText('Nakon uspješnog punog synca, sistem ažurira samo izmijenjene proizvode (ModifiedAfter).')
                    ->visible(fn (Get $get, ?ApiSource $record): bool => ($record?->target_system_code ?? $get('target_system_code')) !== 'eline'),
                Forms\Components\TextInput::make('sync_interval_minutes')
                    ->label('Prilagođeni interval (min)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(1440)
                    ->default(60)
                    ->visible(fn (Get $get, ?ApiSource $record): bool => ($record?->target_system_code ?? $get('target_system_code')) !== 'eline'
                        && $get('interval_preset') === 'custom'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktivan')
                    ->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Naziv')
                    ->searchable(),
                Tables\Columns\TextColumn::make('base_url')
                    ->label('URL')
                    ->limit(40),
                Tables\Columns\TextColumn::make('connection_status')
                    ->label('Konekcija')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'connected' => 'success',
                        'error' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktivan')
                    ->boolean(),
                Tables\Columns\TextColumn::make('auto_sync_enabled')
                    ->label('Auto sync')
                    ->badge()
                    ->formatStateUsing(fn (?bool $state, ApiSource $record): string => $record->target_system_code === 'eline'
                        ? '—'
                        : ($state ? 'ON' : 'OFF'))
                    ->color(fn (?bool $state, ApiSource $record): string => $record->target_system_code === 'eline'
                        ? 'gray'
                        : ($state ? 'success' : 'gray')),
                Tables\Columns\TextColumn::make('sync_interval_minutes')
                    ->label('Interval')
                    ->suffix(' min')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_successful_sync_at')
                    ->label('Zadnji sync')
                    ->dateTime('d.m.Y H:i'),
                Tables\Columns\TextColumn::make('next_sync_at')
                    ->label('Sljedeći sync')
                    ->state(function (ApiSource $record): ?string {
                        $next = $record->nextSyncAt();
                        if ($next === null) {
                            return null;
                        }

                        if ($record->target_system_code !== 'eline'
                            && $record->auto_sync_enabled
                            && $next->isPast()
                            && ! app(\App\Services\Sync\IncrementalSyncScheduler::class)->hasRunningJob($record)) {
                            return 'ZAKASnio ('.$next->format('d.m.Y H:i').')';
                        }

                        return $next->format('d.m.Y H:i');
                    })
                    ->color(fn (ApiSource $record): ?string => ($record->nextSyncAt()?->isPast() && $record->auto_sync_enabled) ? 'danger' : null)
                    ->placeholder('— (potreban puni sync)'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('testConnection')
                    ->label('Test konekcije')
                    ->icon('heroicon-o-signal')
                    ->visible(fn (): bool => auth()->user()?->can('api_sources.update') ?? false)
                    ->action(function (ApiSource $record): void {
                        try {
                            $record->refresh();

                            if ($record->target_system_code === 'eline') {
                                app(ElineSyncOrchestrator::class)->testConnection();
                            } else {
                                $hasCredentials = filled($record->username) && filled($record->password);

                                if (! $hasCredentials && $record->usesIntegrationApiImport()) {
                                    $hasCredentials = filled(config('bnc.a1_api_username'))
                                        && filled(config('bnc.a1_api_password'));
                                }

                                if (! $hasCredentials) {
                                    Notification::make()
                                        ->title('Kredencijali nisu postavljeni')
                                        ->body('Unesite korisničko ime i lozinku za A1 API, sačuvajte zapis, ili postavite A1_API_* u .env i pokrenite php artisan bnc:a1-sync-credentials.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                IntegrationApiClient::forSource($record)->login();
                            }

                            Notification::make()
                                ->title('Konekcija uspješna')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Konekcija neuspješna')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('runIncrementalSync')
                    ->label('Inkrementalni sync')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Inkrementalni sync proizvoda')
                    ->modalDescription('Povlače se samo proizvodi izmijenjeni nakon zadnjeg uspješnog synca (ModifiedAfter). Kategorije i atributi se ne uvoze.')
                    ->visible(fn (ApiSource $record): bool => $record->target_system_code !== 'eline'
                        && ($record->last_successful_sync_at !== null)
                        && (auth()->user()?->can('api_sources.update') ?? false))
                    ->action(function (ApiSource $record): void {
                        RunApiSyncJob::dispatch($record, fullSync: false, skipMetadata: true);

                        Notification::make()
                            ->title('Inkrementalni sync pokrenut')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('runElineIncrementalSync')
                    ->label('eLine sync')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('eLine inkrementalni sync')
                    ->modalDescription('Samo novi i izmijenjeni artikli u mapiranim kategorijama.')
                    ->visible(fn (ApiSource $record): bool => $record->target_system_code === 'eline'
                        && ((auth()->user()?->can('api_sources.update') ?? false) || (auth()->user()?->can('manage_sync') ?? false)))
                    ->action(function (ApiSource $record): void {
                        RunElineSyncJob::dispatch($record, fullSync: false, refreshCategories: false);

                        Notification::make()
                            ->title('eLine sync pokrenut')
                            ->body('Inkrementalni sync je u redu.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('runElineFullSync')
                    ->label('Puni eLine sync')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Puni eLine sync')
                    ->modalDescription('Uvozi sve mapirane proizvode i osvježava eLine kategorije.')
                    ->visible(fn (ApiSource $record): bool => $record->target_system_code === 'eline'
                        && ((auth()->user()?->can('api_sources.update') ?? false) || (auth()->user()?->can('manage_sync') ?? false)))
                    ->action(function (ApiSource $record): void {
                        RunElineSyncJob::dispatch($record, fullSync: true, refreshCategories: true);

                        Notification::make()
                            ->title('Puni eLine sync pokrenut')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('runFullSync')
                    ->label('Puni sync')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Puni sync')
                    ->modalDescription('Uvozi kategorije, atribute i sve proizvode. Koristiti samo za inicijalni uvoz ili popravku podataka.')
                    ->visible(fn (ApiSource $record): bool => $record->target_system_code !== 'eline'
                        && (auth()->user()?->can('api_sources.update') ?? false))
                    ->action(function (ApiSource $record): void {
                        RunApiSyncJob::dispatch($record, fullSync: true);

                        Notification::make()
                            ->title('Puni sync pokrenut')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApiSources::route('/'),
            'create' => Pages\CreateApiSource::route('/create'),
            'edit' => Pages\EditApiSource::route('/{record}/edit'),
        ];
    }
}
