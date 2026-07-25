<?php

namespace App\Filament\B2b\Resources;

use App\Filament\B2b\Resources\B2bAttributeDefinitionResource\Pages;
use App\Filament\B2b\Resources\B2bAttributeDefinitionResource\RelationManagers;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Models\B2bAttributeDefinition;
use App\Models\B2bAttributeOption;
use App\Models\B2bCategory;
use App\Services\B2b\B2bProductAttributeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class B2bAttributeDefinitionResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = B2bAttributeDefinition::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?string $modelLabel = 'B2B atribut';

    protected static ?string $pluralModelLabel = 'B2B atributi';

    protected static ?int $navigationSort = 3;

    protected static function permissionPrefix(): string
    {
        return 'b2b_categories';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Osnovno')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Naziv')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state, ?string $operation): void {
                        if ($operation === 'edit') {
                            return;
                        }

                        $set('slug', B2bProductAttributeService::slugFromName($state ?? ''));
                    }),
                Forms\Components\TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Koristi se u filterima i API-ju.'),
                Forms\Components\Select::make('input_type')
                    ->label('Tip polja')
                    ->options([
                        B2bAttributeDefinition::INPUT_SELECT => 'Odabir (select)',
                        B2bAttributeDefinition::INPUT_MULTISELECT => 'Višestruki odabir',
                        B2bAttributeDefinition::INPUT_TEXT => 'Tekst',
                    ])
                    ->required()
                    ->default(B2bAttributeDefinition::INPUT_SELECT)
                    ->live(),
                Forms\Components\Toggle::make('is_filterable')
                    ->label('Koristi u filterima')
                    ->default(true),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktivan')
                    ->default(true),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Redoslijed')
                    ->numeric()
                    ->default(0),
            ])->columns(2),
            Forms\Components\Section::make('Kategorije')
                ->description('Odaberite kategorije u kojima se ovaj atribut prikazuje pri kreiranju proizvoda.')
                ->schema([
                    Forms\Components\Select::make('category_ids')
                        ->label('Vezano za kategorije')
                        ->options(fn (): array => B2bCategory::query()
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Predefinisane vrijednosti')
                ->description('Opcije za odabir na proizvodu. Za tip "Tekst" ova lista se ne koristi.')
                ->schema([
                    Forms\Components\Repeater::make('option_rows')
                        ->label('Vrijednosti')
                        ->schema([
                            Forms\Components\Hidden::make('id'),
                            Forms\Components\TextInput::make('value')
                                ->label('Vrijednost')
                                ->required()
                                ->maxLength(255),
                            Forms\Components\TextInput::make('sort_order')
                                ->label('Redoslijed')
                                ->numeric()
                                ->default(0),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Dodaj vrijednost')
                        ->reorderable()
                        ->columnSpanFull(),
                ])
                ->visible(fn (Get $get): bool => $get('input_type') !== B2bAttributeDefinition::INPUT_TEXT),
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
                Tables\Columns\TextColumn::make('categories.name')
                    ->label('Kategorije')
                    ->badge()
                    ->separator(', ')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('input_type')
                    ->label('Tip')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        B2bAttributeDefinition::INPUT_SELECT => 'Select',
                        B2bAttributeDefinition::INPUT_MULTISELECT => 'Multi',
                        B2bAttributeDefinition::INPUT_TEXT => 'Tekst',
                        default => $state ?? '—',
                    }),
                Tables\Columns\TextColumn::make('options_count')
                    ->label('Vrijednosti')
                    ->counts('options'),
                Tables\Columns\IconColumn::make('is_filterable')
                    ->label('Filter')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktivan')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Kategorija')
                    ->relationship('categories', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktivan'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Uredi'),
                Tables\Actions\DeleteAction::make()
                    ->label('Obriši'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Obriši odabrane'),
                ]),
            ]);
    }

    /**
     * @param  array<int, int|string>  $categoryIds
     */
    public static function syncCategories(B2bAttributeDefinition $definition, array $categoryIds): void
    {
        $sync = [];

        foreach (array_values($categoryIds) as $index => $categoryId) {
            if ($categoryId === '' || $categoryId === null) {
                continue;
            }

            $sync[(int) $categoryId] = [
                'sort_order' => $definition->sort_order ?: ($index + 1),
            ];
        }

        $definition->categories()->sync($sync);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function syncOptions(B2bAttributeDefinition $definition, array $rows): void
    {
        if ($definition->isText()) {
            $definition->options()->delete();

            return;
        }

        $keptIds = [];

        foreach (array_values($rows) as $index => $row) {
            $value = trim((string) ($row['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            $optionId = $row['id'] ?? null;

            if ($optionId) {
                /** @var B2bAttributeOption|null $option */
                $option = $definition->options()->find($optionId);

                if ($option !== null) {
                    $option->update([
                        'value' => $value,
                        'sort_order' => (int) ($row['sort_order'] ?? ($index + 1)),
                    ]);
                    $keptIds[] = $option->id;

                    continue;
                }
            }

            $option = $definition->options()->create([
                'value' => $value,
                'sort_order' => (int) ($row['sort_order'] ?? ($index + 1)),
            ]);

            $keptIds[] = $option->id;
        }

        if ($keptIds === []) {
            $definition->options()->delete();

            return;
        }

        $definition->options()->whereNotIn('id', $keptIds)->delete();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CategoriesRelationManager::class,
            RelationManagers\OptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListB2bAttributeDefinitions::route('/'),
            'create' => Pages\CreateB2bAttributeDefinition::route('/create'),
            'edit' => Pages\EditB2bAttributeDefinition::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('categories');
    }
}
