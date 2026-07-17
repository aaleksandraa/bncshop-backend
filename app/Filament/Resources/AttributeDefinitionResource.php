<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Concerns\HasAttributeMergeActions;
use App\Filament\Resources\AttributeDefinitionResource\Pages;
use App\Filament\Resources\AttributeDefinitionResource\RelationManagers;
use App\Models\AttributeDefinition;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AttributeDefinitionResource extends Resource
{
    use AuthorizesWithPermissions;
    use HasAttributeMergeActions;

    protected static ?string $model = AttributeDefinition::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?string $modelLabel = 'Atribut';

    protected static ?string $pluralModelLabel = 'Atributi';

    protected static ?int $navigationSort = 4;

    protected static function permissionPrefix(): string
    {
        return 'attributes';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Osnovno')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('API naziv')
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn (?AttributeDefinition $record): bool => $record !== null),
                        Forms\Components\TextInput::make('display_name')
                            ->label('Frontend naziv')
                            ->helperText('Kako se prikazuje kupcu na stranici proizvoda.')
                            ->maxLength(255),
                        Forms\Components\Select::make('internal_type')
                            ->label('Interni tip')
                            ->options([
                                'text' => 'Tekst',
                                'number' => 'Broj',
                                'boolean' => 'Da/Ne',
                                'select' => 'Select',
                                'multi_select' => 'Multi select',
                            ])
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('display_unit')
                            ->label('Jedinica')
                            ->placeholder('npr. GB, mm, W')
                            ->visible(fn (Forms\Get $get): bool => $get('internal_type') === 'number'),
                        Forms\Components\TextInput::make('detail_sort_order')
                            ->label('Redoslijed u specifikacijama')
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('is_public')
                            ->label('Aktivan na frontendu')
                            ->live(),
                        Forms\Components\Toggle::make('is_public_locked')
                            ->label('Sync zaključan')
                            ->helperText('Kada je uključeno, API sync ne mijenja vidljivost.')
                            ->disabled(fn (Forms\Get $get): bool => ! $get('is_public_locked')),
                        Forms\Components\Toggle::make('is_filter')
                            ->label('Filter'),
                        Forms\Components\Toggle::make('is_mapped')
                            ->label('Mapiran')
                            ->helperText('Označite kada ste završili prevod/mapiranje.'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Mapiranje vrijednosti')
                    ->description('API vrijednost → tekst koji kupac vidi. Za Da/Ne koristite ključeve true/false.')
                    ->schema([
                        Forms\Components\KeyValue::make('value_mappings')
                            ->label('Mapiranje vrijednosti')
                            ->keyLabel('API vrijednost')
                            ->valueLabel('Frontend tekst')
                            ->addActionLabel('Dodaj mapiranje')
                            ->columnSpanFull(),
                        Forms\Components\KeyValue::make('parsed_options')
                            ->label('Opcije / boolean labele')
                            ->helperText('Za boolean: true=Da, false=Ne. Za select: vrijednost=label.')
                            ->keyLabel('Ključ')
                            ->valueLabel('Label')
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Integracije')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('api_type')
                            ->label('API tip')
                            ->numeric(),
                        Forms\Components\TextInput::make('external_attribute_id')
                            ->label('External ID')
                            ->disabled(),
                        Forms\Components\Toggle::make('olx_required')
                            ->label('OLX obavezno'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Spojeni atributi')
                    ->description('API aliasi koji su spojeni u ovaj atribut. Sync i dalje upisuje pod originalnim ID-jem, ali se prikazuje ovdje.')
                    ->visible(fn (?AttributeDefinition $record): bool => $record !== null && ! $record->isAlias())
                    ->schema([
                        Forms\Components\Placeholder::make('aliases_list')
                            ->label('Alias atributi')
                            ->content(function (?AttributeDefinition $record): string {
                                if ($record === null) {
                                    return '—';
                                }

                                $aliases = $record->aliases()->orderBy('name')->get();

                                if ($aliases->isEmpty()) {
                                    return 'Nema spojenih aliasa.';
                                }

                                return $aliases
                                    ->map(fn (AttributeDefinition $alias): string => $alias->name)
                                    ->implode(', ');
                            }),
                    ]),
                Forms\Components\Section::make('Alias atributa')
                    ->description('Ovaj zapis je alias i spojen je u glavni atribut ispod.')
                    ->visible(fn (?AttributeDefinition $record): bool => (bool) $record?->isAlias())
                    ->schema([
                        Forms\Components\Placeholder::make('canonical_attribute')
                            ->label('Glavni atribut')
                            ->content(fn (?AttributeDefinition $record): string => $record?->canonicalDefinition?->publicLabel() ?? '—'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('API naziv')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Frontend naziv')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('internal_type')
                    ->label('Tip'),
                Tables\Columns\TextColumn::make('canonical_label')
                    ->label('Spojen u')
                    ->placeholder('—')
                    ->getStateUsing(fn (AttributeDefinition $record): ?string => $record->isAlias()
                        ? $record->canonicalDefinition?->publicLabel()
                        : null)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('aliases_count')
                    ->label('Alias-a')
                    ->counts('aliases')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_mapped')
                    ->label('Mapiran')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_public')
                    ->label('Aktivan')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_public_locked')
                    ->label('Sync zakl.')
                    ->boolean(),
                Tables\Columns\TextColumn::make('detail_sort_order')
                    ->label('Redoslijed')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_filter')
                    ->label('Filter')
                    ->boolean(),
                Tables\Columns\TextColumn::make('category_mappings_count')
                    ->label('Kategorije')
                    ->counts('categoryMappings'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('canonical')
                    ->label('Samo glavni atributi')
                    ->default(true)
                    ->queries(
                        true: fn ($query) => $query->whereNull('canonical_attribute_definition_id'),
                        false: fn ($query) => $query->whereNotNull('canonical_attribute_definition_id'),
                    ),
                Tables\Filters\TernaryFilter::make('is_mapped')
                    ->label('Mapiran'),
                Tables\Filters\TernaryFilter::make('is_filter')
                    ->label('Filter'),
                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Aktivan'),
                Tables\Filters\TernaryFilter::make('is_public_locked')
                    ->label('Sync zaključan'),
            ])
            ->defaultSort('detail_sort_order')
            ->reorderable('detail_sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                ...static::attributeMergeTableActions(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (AttributeDefinition $record): bool => ! $record->isAlias()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ...static::attributeMergeBulkActions(),
                    Tables\Actions\BulkAction::make('unlockPublicSync')
                        ->label('Otključaj sync vidljivosti')
                        ->icon('heroicon-o-lock-open')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['is_public_locked' => false])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CategoryMappingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttributeDefinitions::route('/'),
            'create' => Pages\CreateAttributeDefinition::route('/create'),
            'edit' => Pages\EditAttributeDefinition::route('/{record}/edit'),
        ];
    }
}
