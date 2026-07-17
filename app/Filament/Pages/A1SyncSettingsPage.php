<?php

namespace App\Filament\Pages;

use App\Jobs\RunApiSyncJob;
use App\Services\Sync\A1SyncSettings;
use App\Services\Sync\IntegrationApiClient;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class A1SyncSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Integracije';

    protected static ?string $navigationLabel = 'A1 Technoshop sync';

    protected static ?string $title = 'A1 Technoshop sync';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.a1-sync-settings';

    public ?array $data = [];

    /** @var array<string, mixed> */
    public array $status = [];

    public bool $sourceExists = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->hasRole(['Super Admin', 'Admin'])
            || $user->can('api_sources.update')
            || $user->can('manage_sync')
        );
    }

    public function mount(A1SyncSettings $settings): void
    {
        $this->refreshStatus($settings);
        $this->form->fill($settings->all());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Automatsko ažuriranje')
                    ->description('Scheduler provjerava svakih 5 minuta; stvarni inkrementalni sync se pokreće kad interval istekne. Koristi A1 filter ModifiedAfter.')
                    ->schema([
                        Toggle::make('auto_sync_enabled')
                            ->label('Automatski inkrementalni sync')
                            ->helperText('Privremeno pauzirajte automatska ažuriranja bez gašenja API izvora.')
                            ->disabled(fn (): bool => ! $this->sourceExists),
                        Select::make('interval_preset')
                            ->label('Interval provjere')
                            ->options(fn (A1SyncSettings $settings): array => $settings->presetOptions())
                            ->default('60')
                            ->live()
                            ->disabled(fn (): bool => ! $this->sourceExists),
                        TextInput::make('sync_interval_minutes')
                            ->label('Prilagođeni interval (min)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->visible(fn (Get $get): bool => $get('interval_preset') === 'custom')
                            ->helperText('Scheduler poll je 5 min — efektivni minimum je približno 5 min.')
                            ->disabled(fn (): bool => ! $this->sourceExists),
                    ])
                    ->columns(2)
                    ->visible(fn (): bool => $this->sourceExists),
                Section::make('API izvor')
                    ->schema([
                        Placeholder::make('missing_source')
                            ->label('')
                            ->content('A1 Technoshop API izvor nije pronađen. Pokrenite seeder (ApiSourceSeeder) ili kreirajte izvor sa target_system_code = bnc-shop u API izvorima.')
                            ->visible(fn (): bool => ! $this->sourceExists),
                    ])
                    ->visible(fn (): bool => ! $this->sourceExists),
            ])
            ->statePath('data');
    }

    public function save(A1SyncSettings $settings): void
    {
        if (! $this->sourceExists) {
            Notification::make()
                ->title('A1 izvor nije pronađen')
                ->warning()
                ->send();

            return;
        }

        $settings->save($this->form->getState());
        $this->refreshStatus($settings);

        Notification::make()
            ->title('A1 sync postavke sačuvane.')
            ->success()
            ->send();
    }

    public function testConnection(A1SyncSettings $settings): void
    {
        $source = $settings->resolveSource();

        if ($source === null) {
            Notification::make()
                ->title('A1 izvor nije pronađen')
                ->warning()
                ->send();

            return;
        }

        try {
            IntegrationApiClient::forSource($source)->login();

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

        $this->refreshStatus($settings);
    }

    public function runIncrementalSync(A1SyncSettings $settings): void
    {
        $source = $settings->resolveSource();

        if ($source === null) {
            Notification::make()
                ->title('A1 izvor nije pronađen')
                ->warning()
                ->send();

            return;
        }

        if ($source->last_successful_sync_at === null) {
            Notification::make()
                ->title('Potreban je puni sync')
                ->body('Inkrementalni sync zahtijeva postojeći last_successful_sync_at. Prvo pokrenite puni sync.')
                ->warning()
                ->send();

            return;
        }

        RunApiSyncJob::dispatch($source, fullSync: false, skipMetadata: true);

        Notification::make()
            ->title('Inkrementalni sync pokrenut')
            ->success()
            ->send();

        $this->refreshStatus($settings);
    }

    public function runFullSync(A1SyncSettings $settings): void
    {
        $source = $settings->resolveSource();

        if ($source === null) {
            Notification::make()
                ->title('A1 izvor nije pronađen')
                ->warning()
                ->send();

            return;
        }

        RunApiSyncJob::dispatch($source, fullSync: true);

        Notification::make()
            ->title('Puni sync pokrenut')
            ->success()
            ->send();

        $this->refreshStatus($settings);
    }

    private function refreshStatus(A1SyncSettings $settings): void
    {
        $this->status = $settings->status();
        $this->sourceExists = (bool) ($this->status['source_exists'] ?? false);
    }
}
