<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\ApiImportJobResource\Pages;
use App\Filament\Resources\ApiImportJobResource\RelationManagers;
use App\Models\ApiImportJob;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ApiImportJobResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = ApiImportJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Integracije';

    protected static ?string $modelLabel = 'Import job';

    protected static ?string $pluralModelLabel = 'Import jobovi';

    protected static ?int $navigationSort = 2;

    protected static function permissionPrefix(): string
    {
        return 'api_import_jobs';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Job')
                    ->schema([
                        Infolists\Components\TextEntry::make('apiSource.name')
                            ->label('API izvor'),
                        Infolists\Components\TextEntry::make('type')
                            ->label('Tip')
                            ->badge(),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'completed' => 'success',
                                'failed' => 'danger',
                                'running' => 'warning',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('started_at')
                            ->label('Početak')
                            ->dateTime('d.m.Y H:i'),
                        Infolists\Components\TextEntry::make('completed_at')
                            ->label('Kraj')
                            ->dateTime('d.m.Y H:i'),
                        Infolists\Components\TextEntry::make('error_message')
                            ->label('Greška')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Statistika proizvoda')
                    ->schema([
                        Infolists\Components\TextEntry::make('stats.products.created')
                            ->label('Ubačeno')
                            ->state(fn (ApiImportJob $record): string => (string) ($record->stats['products']['created'] ?? '—')),
                        Infolists\Components\TextEntry::make('stats.products.updated')
                            ->label('Izmijenjeno')
                            ->state(fn (ApiImportJob $record): string => (string) ($record->stats['products']['updated'] ?? '—')),
                        Infolists\Components\TextEntry::make('stats.products.deactivated')
                            ->label('Deaktivirano')
                            ->state(fn (ApiImportJob $record): string => (string) ($record->stats['products']['deactivated'] ?? '—')),
                        Infolists\Components\TextEntry::make('stats.products.imported')
                            ->label('Ukupno obrađeno')
                            ->state(fn (ApiImportJob $record): string => (string) ($record->stats['products']['imported'] ?? '—')),
                        Infolists\Components\TextEntry::make('stats.products.pages')
                            ->label('Stranica API-ja')
                            ->state(fn (ApiImportJob $record): string => (string) ($record->stats['products']['pages'] ?? '—')),
                        Infolists\Components\TextEntry::make('stats.products.errors')
                            ->label('Greške')
                            ->state(fn (ApiImportJob $record): string => self::formatErrorCount($record->stats['products']['errors'] ?? null))
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->visible(fn (ApiImportJob $record): bool => isset($record->stats['products'])),
                Infolists\Components\Section::make('Ostala statistika')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('stats')
                            ->label('Statistika')
                            ->getStateUsing(fn (ApiImportJob $record): array => self::flattenStatsForDisplay(
                                self::statsWithoutProducts($record->stats),
                            ))
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (ApiImportJob $record): bool => self::statsWithoutProducts($record->stats) !== []),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('apiSource.name')
                    ->label('Izvor')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tip')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'running' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Početak')
                    ->dateTime('d.m.Y H:i')
                    ->timezone(config('app.timezone'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Kraj')
                    ->dateTime('d.m.Y H:i')
                    ->timezone(config('app.timezone')),
                Tables\Columns\TextColumn::make('stats.products.created')
                    ->label('Ubačeno')
                    ->state(fn (ApiImportJob $record): ?string => isset($record->stats['products']['created'])
                        ? (string) $record->stats['products']['created']
                        : null)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('stats.products.updated')
                    ->label('Izmijenjeno')
                    ->state(fn (ApiImportJob $record): ?string => isset($record->stats['products']['updated'])
                        ? (string) $record->stats['products']['updated']
                        : null)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('stats.products.deactivated')
                    ->label('Deaktivirano')
                    ->state(fn (ApiImportJob $record): ?string => isset($record->stats['products']['deactivated'])
                        ? (string) $record->stats['products']['deactivated']
                        : null)
                    ->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'running' => 'U toku',
                        'completed' => 'Završeno',
                        'failed' => 'Neuspjelo',
                    ]),
                Tables\Filters\SelectFilter::make('api_source_id')
                    ->label('Izvor')
                    ->relationship('apiSource', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ChangesRelationManager::class,
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApiImportJobs::route('/'),
            'view' => Pages\ViewApiImportJob::route('/{record}'),
        ];
    }

    /**
     * KeyValueEntry accepts only scalar values; sync stats are nested arrays.
     *
     * @param  array<string, mixed>|null  $stats
     * @return array<string, string>
     */
    private static function flattenStatsForDisplay(?array $stats, string $prefix = ''): array
    {
        if ($stats === null || $stats === []) {
            return [];
        }

        $flat = [];

        foreach ($stats as $key => $value) {
            $label = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                if ($value === []) {
                    $flat[$label] = '0';

                    continue;
                }

                if (array_is_list($value) && ! is_array($value[0] ?? null)) {
                    $flat[$label] = implode("\n", array_map(strval(...), $value));

                    continue;
                }

                $flat = array_merge($flat, self::flattenStatsForDisplay($value, $label));

                continue;
            }

            $flat[$label] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                default => (string) $value,
            };
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>|null  $stats
     * @return array<string, mixed>
     */
    private static function statsWithoutProducts(?array $stats): array
    {
        if ($stats === null || $stats === []) {
            return [];
        }

        $filtered = $stats;
        unset($filtered['products']);

        return $filtered;
    }

    /**
     * @param  array<int, string>|null  $errors
     */
    private static function formatErrorCount(?array $errors): string
    {
        if ($errors === null || $errors === []) {
            return '0';
        }

        return count($errors).' (vidi stavke joba / promjene)';
    }
}
