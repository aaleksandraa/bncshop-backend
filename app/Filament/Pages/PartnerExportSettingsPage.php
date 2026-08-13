<?php

namespace App\Filament\Pages;

use App\Services\Integrations\PartnerExportSettings;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PartnerExportSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Integracije';

    protected static ?string $navigationLabel = 'Partner API postavke';

    protected static ?string $title = 'Partner export — globalne postavke';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.partner-export-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        return $user->can('manage_products')
            || $user->can('manage_sync')
            || $user->can('view_sync')
            || $user->can('customers.update');
    }

    public function mount(PartnerExportSettings $settings): void
    {
        $this->form->fill([
            'enabled' => $settings->all()['enabled'] ?? false,
            'require_https' => $settings->requireHttps(),
            'require_ip_allowlist' => $settings->requiresIpAllowlist(),
            'max_failed_auth_per_minute' => $settings->maxFailedAuthPerMinute(),
            'log_access' => $settings->shouldLogAccess(),
            'legacy_endpoint_url' => $settings->legacyEndpointUrl(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Globalni prekidač')
                    ->description('Upravljanje partner ključevima i tipovima API-ja nalazi se u resursu Partner API ključevi.')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Uključi partner export API')
                            ->helperText('Kada je isključeno, svi partneri dobijaju HTTP 403.'),
                        Placeholder::make('legacy_endpoint_url')
                            ->label('Legacy endpoint')
                            ->content(fn (): string => app(PartnerExportSettings::class)->legacyEndpointUrl()),
                    ])
                    ->columns(2),
                Section::make('Sigurnost')
                    ->schema([
                        Toggle::make('require_https')
                            ->label('Zahtijevaj HTTPS')
                            ->helperText('Blokira plain HTTP pristup. Lokalno možete privremeno isključiti.'),
                        Toggle::make('require_ip_allowlist')
                            ->label('Zahtijevaj IP allowlist kod partnera')
                            ->helperText('Partneri sa uključenim allowlistom moraju imati definisane IP adrese prije produkcijskog rada.'),
                        TextInput::make('max_failed_auth_per_minute')
                            ->label('Limit neuspjelih auth pokušaja po minuti (po IP)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->default(10),
                        Toggle::make('log_access')
                            ->label('Loguj pristupe API-ju')
                            ->helperText('Upisuje IP, putanju i ishod autentifikacije u Laravel log.'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(PartnerExportSettings $settings): void
    {
        $state = $this->form->getState();

        $settings->save([
            'enabled' => (bool) ($state['enabled'] ?? false),
            'require_https' => (bool) ($state['require_https'] ?? true),
            'require_ip_allowlist' => (bool) ($state['require_ip_allowlist'] ?? true),
            'max_failed_auth_per_minute' => (int) ($state['max_failed_auth_per_minute'] ?? 10),
            'log_access' => (bool) ($state['log_access'] ?? true),
        ]);

        Notification::make()
            ->title('Partner export postavke sačuvane.')
            ->success()
            ->send();
    }
}
