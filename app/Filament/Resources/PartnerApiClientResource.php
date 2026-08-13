<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerApiClientResource\Pages;
use App\Models\PartnerApiClient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PartnerApiClientResource extends Resource
{
    protected static ?string $model = PartnerApiClient::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Integracije';

    protected static ?string $navigationLabel = 'Partner API ključevi';

    protected static ?string $modelLabel = 'Partner API ključ';

    protected static ?string $pluralModelLabel = 'Partner API ključevi';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
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

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Partner')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Naziv (interno)')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->label('Kod (targetSystemCode)')
                            ->required()
                            ->maxLength(64)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->helperText('Koristi se u URL-u: /api/integrations/{code}/products'),
                        Forms\Components\Select::make('type')
                            ->label('Tip API-ja')
                            ->options([
                                PartnerApiClient::TYPE_BASIC => 'Osnovni',
                                PartnerApiClient::TYPE_FULL => 'Puni',
                            ])
                            ->helperText('Osnovni: naziv, barkod, zaliha, cijena. Puni: plus kategorija, opis, atributi, slike, brend.')
                            ->default(PartnerApiClient::TYPE_BASIC)
                            ->required()
                            ->in([PartnerApiClient::TYPE_BASIC, PartnerApiClient::TYPE_FULL])
                            ->native(false),
                        Forms\Components\Toggle::make('enabled')
                            ->label('Aktivan')
                            ->default(true),
                        Forms\Components\Placeholder::make('integration_url')
                            ->label('Integracijski endpoint')
                            ->content(function (?Model $record): string {
                                if ($record instanceof PartnerApiClient) {
                                    return $record->integrationProductsUrl();
                                }

                                return rtrim((string) config('app.url'), '/').'/api/integrations/{code}/products';
                            }),
                        Forms\Components\Placeholder::make('legacy_url')
                            ->label('Legacy endpoint')
                            ->content(fn (): string => app(\App\Services\Integrations\PartnerExportSettings::class)->legacyEndpointUrl()),
                        Forms\Components\Placeholder::make('api_key_hint_display')
                            ->label('Aktivni API ključ')
                            ->content(function (?Model $record): string {
                                if ($record instanceof PartnerApiClient && filled($record->api_key_hint)) {
                                    return '...'.$record->api_key_hint.' (rotirajte ključ da biste vidjeli cijeli token)';
                                }

                                return 'Nije generisan — sačuvajte partnera pa rotirajte ključ.';
                            }),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Sigurnost')
                    ->schema([
                        Forms\Components\Toggle::make('require_ip_allowlist')
                            ->label('Zahtijevaj IP allowlist')
                            ->helperText('Kada je uključeno i globalna postavka traži allowlist, partner mora imati definisane IP adrese.'),
                        Forms\Components\Textarea::make('allowed_ips_text')
                            ->label('Dozvoljene IP adrese')
                            ->rows(4)
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Forms\Components\Textarea $component, ?PartnerApiClient $record): void {
                                if ($record === null) {
                                    return;
                                }

                                $component->state(implode("\n", $record->allowedIpList()));
                            })
                            ->helperText('Jedna adresa po liniji. Podržava CIDR (npr. 203.0.113.0/24).'),
                        Forms\Components\TextInput::make('rate_limit_per_minute')
                            ->label('Limit zahtjeva po minuti')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(300)
                            ->default(60)
                            ->required(),
                        Forms\Components\TextInput::make('daily_page_limit')
                            ->label('Dnevni limit stranica')
                            ->numeric()
                            ->minValue(50)
                            ->maxValue(10000)
                            ->default(2000)
                            ->required()
                            ->helperText('Max broj uspješnih GET stranica u 24h. Jedan full sync (~24k proizvoda / 100) troši oko 240 stranica.'),
                        Forms\Components\Placeholder::make('last_used_at')
                            ->label('Zadnji uspješan pristup')
                            ->content(function (?Model $record): string {
                                if ($record instanceof PartnerApiClient && $record->last_used_at) {
                                    return $record->last_used_at->toIso8601String();
                                }

                                return 'Još nema pristupa.';
                            }),
                        Forms\Components\Placeholder::make('last_used_ip')
                            ->label('IP zadnjeg pristupa')
                            ->content(function (?Model $record): string {
                                if ($record instanceof PartnerApiClient && filled($record->last_used_ip)) {
                                    return (string) $record->last_used_ip;
                                }

                                return '—';
                            }),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Upotreba API-ja')
                    ->schema([
                        Forms\Components\Placeholder::make('docs_auth')
                            ->label('Autentifikacija')
                            ->content('Pošaljite API ključ SAMO u HTTP headeru: Authorization: Bearer {ključ} ili X-API-Key: {ključ}.'),
                        Forms\Components\Placeholder::make('docs_incremental')
                            ->label('Inkrementalni sync')
                            ->content('GET ?ModifiedAfter=2026-07-07T20:00:00Z&Page=1&PageSize=100 (ili legacy ?updated_since=...&page=...&per_page=...).'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Naziv')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Kod')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tip')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        PartnerApiClient::TYPE_FULL => 'Puni',
                        default => 'Osnovni',
                    }),
                Tables\Columns\IconColumn::make('enabled')
                    ->label('Aktivan')
                    ->boolean(),
                Tables\Columns\TextColumn::make('api_key_hint')
                    ->label('Ključ')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? '...'.$state : '—'),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Zadnji pristup')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartnerApiClients::route('/'),
            'create' => Pages\CreatePartnerApiClient::route('/create'),
            'edit' => Pages\EditPartnerApiClient::route('/{record}/edit'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mutateFormDataBeforeSave(array $data): array
    {
        return PartnerApiClient::sanitizeFormData($data);
    }
}
