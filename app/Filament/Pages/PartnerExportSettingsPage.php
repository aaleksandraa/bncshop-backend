<?php

namespace App\Filament\Pages;

use App\Services\Integrations\PartnerExportSettings;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
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

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Integracije';

    protected static ?string $navigationLabel = 'Partner API ključevi';

    protected static ?string $title = 'Partner export API';

    protected static ?int $navigationSort = 3;

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
            'partner_name' => $settings->all()['partner_name'] ?? '',
            'require_https' => $settings->requireHttps(),
            'require_ip_allowlist' => $settings->requiresIpAllowlist(),
            'allowed_ips_text' => implode("\n", $settings->allowedIps()),
            'rate_limit_per_minute' => $settings->rateLimitPerMinute(),
            'max_failed_auth_per_minute' => $settings->maxFailedAuthPerMinute(),
            'log_access' => $settings->shouldLogAccess(),
            'endpoint_url' => $settings->endpointUrl(),
            'api_key_hint' => $settings->apiKeyHint(),
            'last_used_at' => $settings->lastUsedAt(),
            'last_used_ip' => $settings->lastUsedIp(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Pristup')
                    ->description('Partner webshop koristi ovaj API za preuzimanje proizvoda (naziv, EAN, šifra, cijena, akcijska cijena, zaliha).')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Uključi partner export API')
                            ->helperText('Kada je isključeno, svi zahtjevi vraćaju HTTP 403.'),
                        TextInput::make('partner_name')
                            ->label('Naziv partnera (interno)')
                            ->maxLength(255)
                            ->helperText('Opcionalna oznaka za admin panel; ne utiče na API.'),
                        Placeholder::make('endpoint_url')
                            ->label('Endpoint')
                            ->content(fn (): string => app(PartnerExportSettings::class)->endpointUrl()),
                        Placeholder::make('api_key_hint')
                            ->label('Aktivni API ključ')
                            ->content(function (): string {
                                $hint = app(PartnerExportSettings::class)->apiKeyHint();

                                return filled($hint)
                                    ? '...'.$hint.' (generišite novi ključ da biste vidjeli cijeli token)'
                                    : 'Nije generisan — kliknite "Generiši novi API ključ".';
                            }),
                    ])
                    ->columns(2),
                Section::make('Sigurnost')
                    ->description('Preporuka za produkciju: uključen HTTPS, definisan IP allowlist partnera i redovna rotacija API ključa.')
                    ->schema([
                        Toggle::make('require_https')
                            ->label('Zahtijevaj HTTPS')
                            ->helperText('Blokira plain HTTP pristup. Lokalno možete privremeno isključiti.'),
                        Toggle::make('require_ip_allowlist')
                            ->label('Zahtijevaj IP allowlist')
                            ->helperText('U produkciji preporučeno uključeno. Bez unesenih IP adresa API ne radi.'),
                        Textarea::make('allowed_ips_text')
                            ->label('Dozvoljene IP adrese partnera')
                            ->rows(4)
                            ->helperText('Jedna adresa po liniji. Podržava CIDR (npr. 203.0.113.0/24). Prazno = dozvoljava sve IP adrese.'),
                        TextInput::make('rate_limit_per_minute')
                            ->label('Limit zahtjeva po minuti')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(300)
                            ->default(60),
                        TextInput::make('max_failed_auth_per_minute')
                            ->label('Limit neuspjelih auth pokušaja po minuti (po IP)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->default(10),
                        Toggle::make('log_access')
                            ->label('Loguj pristupe API-ju')
                            ->helperText('Upisuje IP, putanju i ishod autentifikacije u Laravel log.'),
                        Placeholder::make('last_used_at')
                            ->label('Zadnji uspješan pristup')
                            ->content(fn (): string => app(PartnerExportSettings::class)->lastUsedAt() ?: 'Još nema pristupa.'),
                        Placeholder::make('last_used_ip')
                            ->label('IP zadnjeg pristupa')
                            ->content(fn (): string => app(PartnerExportSettings::class)->lastUsedIp() ?: '—'),
                    ])
                    ->columns(2),
                Section::make('Upotreba API-ja')
                    ->schema([
                        Placeholder::make('docs')
                            ->label('Autentifikacija')
                            ->content('Pošaljite API ključ SAMO u HTTP headeru: Authorization: Bearer {ključ} ili X-API-Key: {ključ}. Nikad u URL query parametrima.'),
                        Placeholder::make('docs_filter')
                            ->label('Filtriranje')
                            ->content('GET ?updated_since=2026-01-01T00:00:00+02:00 vraća samo proizvode izmijenjene od tog datuma. Podržani su i page i per_page (max 200).'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(PartnerExportSettings $settings): void
    {
        $state = $this->form->getState();
        $invalidIps = $settings->invalidAllowedIps((string) ($state['allowed_ips_text'] ?? ''));

        if ($invalidIps !== []) {
            Notification::make()
                ->title('Neispravne IP adrese')
                ->body('Provjerite unos: '.implode(', ', $invalidIps))
                ->danger()
                ->send();

            return;
        }

        $settings->save([
            'enabled' => (bool) ($state['enabled'] ?? false),
            'partner_name' => (string) ($state['partner_name'] ?? ''),
            'require_https' => (bool) ($state['require_https'] ?? true),
            'require_ip_allowlist' => (bool) ($state['require_ip_allowlist'] ?? true),
            'allowed_ips_text' => (string) ($state['allowed_ips_text'] ?? ''),
            'rate_limit_per_minute' => (int) ($state['rate_limit_per_minute'] ?? 60),
            'max_failed_auth_per_minute' => (int) ($state['max_failed_auth_per_minute'] ?? 10),
            'log_access' => (bool) ($state['log_access'] ?? true),
        ]);

        Notification::make()
            ->title('Partner export postavke sačuvane.')
            ->success()
            ->send();
    }

    public function regenerateApiKey(PartnerExportSettings $settings): void
    {
        $state = $this->form->getState();
        $invalidIps = $settings->invalidAllowedIps((string) ($state['allowed_ips_text'] ?? ''));

        if ($invalidIps !== []) {
            Notification::make()
                ->title('Neispravne IP adrese')
                ->body('Sačuvajte ispravan allowlist prije generisanja ključa.')
                ->danger()
                ->send();

            return;
        }

        $settings->save([
            'enabled' => (bool) ($state['enabled'] ?? false),
            'partner_name' => (string) ($state['partner_name'] ?? ''),
            'require_https' => (bool) ($state['require_https'] ?? true),
            'require_ip_allowlist' => (bool) ($state['require_ip_allowlist'] ?? true),
            'allowed_ips_text' => (string) ($state['allowed_ips_text'] ?? ''),
            'rate_limit_per_minute' => (int) ($state['rate_limit_per_minute'] ?? 60),
            'max_failed_auth_per_minute' => (int) ($state['max_failed_auth_per_minute'] ?? 10),
            'log_access' => (bool) ($state['log_access'] ?? true),
        ]);

        $plainKey = $settings->rotateApiKey();

        $this->form->fill([
            ...$state,
            'api_key_hint' => $settings->apiKeyHint(),
        ]);

        Notification::make()
            ->title('Novi API ključ generisan')
            ->body($plainKey)
            ->persistent()
            ->success()
            ->send();
    }
}
