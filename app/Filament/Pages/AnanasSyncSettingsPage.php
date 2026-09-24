<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AnanasCategoryMappingResource;
use App\Services\Ananas\AnanasApiClient;
use App\Services\Ananas\AnanasCatalogWriteGuard;
use App\Services\Ananas\AnanasEligibilityReporter;
use App\Services\Ananas\AnanasLinkedProductSyncService;
use App\Services\Ananas\AnanasProductImportService;
use App\Services\Ananas\AnanasProductReconciliationService;
use App\Services\Ananas\AnanasProductTypeSyncService;
use App\Services\Ananas\AnanasSyncSettings;
use App\Services\Ananas\AnanasValidatedMappingService;
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

    /** @var list<array<string, mixed>> */
    public array $mappingRows = [];

    /** @var array<string, mixed>|null */
    public ?array $lastCatalogAction = null;

    public int $importLimit = 50;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->hasRole(['Super Admin', 'Admin'])
            || $user->can('manage_sync')
            || $user->can('api_sources.update')
        );
    }

    public function mount(
        AnanasSyncSettings $settings,
        AnanasEligibilityReporter $reporter,
        AnanasValidatedMappingService $mappingService,
    ): void {
        $this->status = $settings->status();
        $settings->resolveSource();
        $this->eligibilitySummary = $reporter->summarize();
        $this->mappingRows = $mappingService->summaryRows();

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
                            ->helperText('Uključite prije live importa, probe i publish. Dry-run radi i bez ovoga. POST import ostaje blokiran dok je isključeno.'),
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

    public function applyValidatedMappings(AnanasValidatedMappingService $mappingService): void
    {
        $result = $mappingService->apply();
        $this->mappingRows = $mappingService->summaryRows();

        $appliedCount = count($result['applied']);
        $skippedCount = count($result['skipped']);
        $disabledCount = count($result['disabled']);

        $lines = [];
        foreach ($result['applied'] as $row) {
            $lines[] = sprintf(
                '%s #%d → %s (%s)',
                $row['action'] === 'created' ? 'Novo' : 'Ažurirano',
                $row['mapping_id'],
                $row['ananas_category'],
                $row['enabled'] ? 'uključeno' : 'isključeno',
            );
        }
        foreach ($result['skipped'] as $skip) {
            $lines[] = $skip;
        }
        foreach ($result['disabled'] as $row) {
            $lines[] = sprintf('Isključeno zastarjelo #%d: %s', $row['mapping_id'], $row['reason']);
        }

        $this->lastCatalogAction = [
            'title' => 'Mapiranja',
            'body' => implode("\n", $lines),
        ];

        if ($appliedCount === 0) {
            Notification::make()
                ->title('Nijedno mapiranje nije primijenjeno')
                ->body($skippedCount > 0
                    ? implode(' ', $result['skipped'])
                    : 'Provjerite da BNC kategorije 199 i 231 postoje.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Stage mapiranja primijenjena')
            ->body($appliedCount.' spremno za import'
                .($disabledCount > 0 ? ', '.$disabledCount.' zastarjelih isključeno.' : '.'))
            ->success()
            ->send();
    }

    public function importDryRun(AnanasProductImportService $importService): void
    {
        $this->runImport($importService, dryRun: true);
    }

    public function importLive(
        AnanasProductImportService $importService,
        AnanasSyncSettings $settings,
        AnanasCatalogWriteGuard $writeGuard,
    ): void {
        if ($settings->isProduction()) {
            Notification::make()
                ->title('Production import je blokiran u adminu')
                ->body('Za production koristite CLI: php artisan bnc:ananas-import-products --confirm --allow-production')
                ->warning()
                ->send();

            return;
        }

        if (! $writeGuard->isAllowed()) {
            Notification::make()
                ->title('Catalog writes su isključeni')
                ->body('Uključite „Dozvoli catalog write API pozive“, sačuvajte, pa ponovo pokrenite import.')
                ->warning()
                ->send();

            return;
        }

        $this->runImport($importService, dryRun: false);
    }

    public function reconcileProducts(AnanasProductReconciliationService $reconciliationService): void
    {
        try {
            $result = $reconciliationService->reconcileSubmittedMappings(limit: max(1, min($this->importLimit, 100)));
        } catch (\Throwable $e) {
            Notification::make()->title('Reconcile neuspješan')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->lastCatalogAction = [
            'title' => 'Reconcile',
            'body' => sprintf(
                "Linked: %d\nPending: %d\nFailed: %d%s",
                $result['linked'],
                $result['pending'],
                $result['failed'],
                $result['details'] !== [] ? "\n".implode("\n", array_slice($result['details'], 0, 12)) : '',
            ),
        ];

        Notification::make()
            ->title('Reconcile završen')
            ->body(sprintf('Linked %d, pending %d, failed %d', $result['linked'], $result['pending'], $result['failed']))
            ->success()
            ->send();
    }

    public function publishDryRun(AnanasLinkedProductSyncService $syncService): void
    {
        $this->runPublish($syncService, dryRun: true);
    }

    public function publishLive(
        AnanasLinkedProductSyncService $syncService,
        AnanasSyncSettings $settings,
        AnanasCatalogWriteGuard $writeGuard,
    ): void {
        if ($settings->isProduction()) {
            Notification::make()
                ->title('Production publish je blokiran u adminu')
                ->body('Za production koristite CLI: php artisan bnc:ananas-publish --confirm --allow-production')
                ->warning()
                ->send();

            return;
        }

        if (! $writeGuard->isAllowed()) {
            Notification::make()
                ->title('Catalog writes su isključeni')
                ->body('Uključite „Dozvoli catalog write API pozive“, sačuvajte, pa ponovo pokrenite publish.')
                ->warning()
                ->send();

            return;
        }

        $this->runPublish($syncService, dryRun: false);
    }

    public function mappingsUrl(): string
    {
        return AnanasCategoryMappingResource::getUrl();
    }

    private function runImport(AnanasProductImportService $importService, bool $dryRun): void
    {
        $limit = max(1, min($this->importLimit, (int) config('bnc.ananas_import_batch_max_size', 100)));

        try {
            $result = $importService->importBatch(
                limit: $limit,
                dryRun: $dryRun,
            );
        } catch (\Throwable $e) {
            Notification::make()->title('Import neuspješan')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->lastCatalogAction = [
            'title' => $dryRun ? 'Import dry-run' : 'Import',
            'body' => sprintf(
                "Submitted: %d\nSkipped: %d\nProgress UUID: %s\nProduct IDs: %s%s",
                $result['submitted'],
                $result['skipped'],
                $result['progress_id'] ?? '—',
                $result['product_ids'] !== [] ? implode(', ', $result['product_ids']) : '—',
                $result['errors'] !== [] ? "\n".implode("\n", $result['errors']) : '',
            ),
        ];

        $title = $dryRun ? 'Dry-run importa' : 'Import poslan';
        $body = 'Submitted: '.$result['submitted'].', skipped: '.$result['skipped'];

        if (! $dryRun && filled($result['progress_id'])) {
            $body .= '. UUID: '.$result['progress_id'];
        }

        if ($result['submitted'] === 0 && ! $dryRun) {
            Notification::make()
                ->title('Nijedan SKU nije poslan')
                ->body($body.'. Primijenite mapiranja i uključite export, pa dry-run.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()->title($title)->body($body)->success()->send();
    }

    private function runPublish(AnanasLinkedProductSyncService $syncService, bool $dryRun): void
    {
        $limit = max(1, min($this->importLimit, 100));

        try {
            $result = $syncService->publishReadyLinked(
                limit: $limit,
                dryRun: $dryRun,
            );
        } catch (\Throwable $e) {
            Notification::make()->title('Publish neuspješan')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->lastCatalogAction = [
            'title' => $dryRun ? 'Publish dry-run' : 'Publish',
            'body' => sprintf(
                "Items: %d\nProgress UUID: %s%s",
                $result['published'],
                $result['progress_id'] ?? '—',
                $result['errors'] !== [] ? "\n".implode("\n", $result['errors']) : '',
            ),
        ];

        $notification = Notification::make()
            ->title($dryRun ? 'Publish dry-run' : 'Publish poslan')
            ->body('Items: '.$result['published'].($result['progress_id'] ? ', UUID: '.$result['progress_id'] : ''));

        if ($result['errors'] !== []) {
            $notification->danger()->send();

            return;
        }

        $notification->success()->send();
    }
}
