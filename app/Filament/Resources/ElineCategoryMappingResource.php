<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ElineCategoryMappingResource\Pages;
use App\Jobs\RunElineSyncJob;
use App\Models\ApiSource;
use App\Models\Category;
use App\Models\ElineCategoryMapping;
use App\Services\Eline\ElineCategoryDiscoveryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ElineCategoryMappingResource extends Resource
{
    protected static ?string $model = ElineCategoryMapping::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Integracije';

    protected static ?string $modelLabel = 'eLine mapiranje kategorije';

    protected static ?string $pluralModelLabel = 'eLine mapiranje kategorija';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user?->can('manage_sync') || $user?->can('view_sync') || $user?->can('manage_products');
    }

    public static function canEdit($record): bool
    {
        return (bool) (auth()->user()?->can('manage_sync') || auth()->user()?->can('manage_products'));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('eline_category_name')
                    ->label('eLine kategorija')
                    ->content(fn (?ElineCategoryMapping $record): string => $record?->elineCategory?->name ?? '—'),
                Forms\Components\Placeholder::make('product_count')
                    ->label('Broj proizvoda u feedu')
                    ->content(fn (?ElineCategoryMapping $record): string => (string) ($record?->elineCategory?->product_count ?? 0)),
                Forms\Components\Select::make('category_id')
                    ->label('BNC kategorija')
                    ->options(fn (): array => Category::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->nullable(),
                Forms\Components\Toggle::make('is_enabled')
                    ->label('Uključeno za import')
                    ->helperText('Proizvodi se uvode samo ako je kategorija mapirana i uključena.'),
                Forms\Components\Select::make('product_condition')
                    ->label('Stanje proizvoda')
                    ->options([
                        ElineCategoryMapping::CONDITION_REFURBISHED => 'Refurbished (polovno)',
                        ElineCategoryMapping::CONDITION_NEW => 'Novo',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('margin_percentage')
                    ->label('Marža (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(999)
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['elineCategory', 'category']))
            ->columns([
                Tables\Columns\TextColumn::make('elineCategory.name')
                    ->label('eLine kategorija')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('elineCategory.product_count')
                    ->label('Artikala')
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('BNC kategorija')
                    ->placeholder('Nije mapirano')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Uključeno')
                    ->boolean(),
                Tables\Columns\TextColumn::make('product_condition')
                    ->label('Stanje')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        ElineCategoryMapping::CONDITION_NEW => 'Novo',
                        default => 'Refurbished',
                    }),
                Tables\Columns\TextColumn::make('elineCategory.last_seen_at')
                    ->label('Zadnji feed')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('elineCategory.name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label('Uključeno'),
                Tables\Filters\SelectFilter::make('product_condition')
                    ->label('Stanje')
                    ->options([
                        ElineCategoryMapping::CONDITION_REFURBISHED => 'Refurbished',
                        ElineCategoryMapping::CONDITION_NEW => 'Novo',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('enable')
                        ->label('Uključi odabrane')
                        ->icon('heroicon-o-check')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_enabled' => true])),
                    Tables\Actions\BulkAction::make('disable')
                        ->label('Isključi odabrane')
                        ->icon('heroicon-o-x-mark')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_enabled' => false])),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('runIncrementalSync')
                    ->label('Pokreni eLine sync')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Ručni eLine sync')
                    ->modalDescription('Inkrementalni uvoz: samo novi i izmijenjeni proizvodi u mapiranim i uključenim kategorijama. Job se izvršava u pozadini.')
                    ->visible(fn (): bool => auth()->user()?->can('manage_sync') ?? false)
                    ->action(function (): void {
                        static::dispatchElineSync(fullSync: false, refreshCategories: false);
                    }),
                Tables\Actions\Action::make('runFullSync')
                    ->label('Puni eLine sync')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Puni eLine sync')
                    ->modalDescription('Uvozi sve proizvode iz mapiranih kategorija i osvježava listu eLine kategorija prije uvoza. Koristiti za inicijalni uvoz ili popravku podataka.')
                    ->visible(fn (): bool => auth()->user()?->can('manage_sync') ?? false)
                    ->action(function (): void {
                        static::dispatchElineSync(fullSync: true, refreshCategories: true);
                    }),
                Tables\Actions\Action::make('discoverCategories')
                    ->label('Osvježi kategorije iz eLine')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => auth()->user()?->can('manage_sync') ?? false)
                    ->action(function (ElineCategoryDiscoveryService $discovery): void {
                        $stats = $discovery->discover();

                        Notification::make()
                            ->title('eLine kategorije osvježene')
                            ->body(sprintf(
                                'Pronađeno %d kategorija (%d novih mapiranja).',
                                $stats['categories'],
                                $stats['mappings_created'],
                            ))
                            ->success()
                            ->send();
                    }),
            ]);
    }

    protected static function dispatchElineSync(bool $fullSync, bool $refreshCategories): void
    {
        $source = ApiSource::query()
            ->where('target_system_code', 'eline')
            ->where('is_active', true)
            ->first();

        if ($source === null) {
            Notification::make()
                ->title('eLine izvor nije pronađen')
                ->body('Provjerite da je eLine ERP aktivan u API izvorima.')
                ->danger()
                ->send();

            return;
        }

        RunElineSyncJob::dispatch($source, $fullSync, $refreshCategories);

        Notification::make()
            ->title($fullSync ? 'Puni eLine sync pokrenut' : 'eLine sync pokrenut')
            ->body($fullSync
                ? 'Sync je u redu. Osvježit će kategorije i uvesti sve mapirane proizvode.'
                : 'Inkrementalni sync je u redu. Povlače se samo novi i izmijenjeni artikli.')
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListElineCategoryMappings::route('/'),
            'edit' => Pages\EditElineCategoryMapping::route('/{record}/edit'),
        ];
    }
}
