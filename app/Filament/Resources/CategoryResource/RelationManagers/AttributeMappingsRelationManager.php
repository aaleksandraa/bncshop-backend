<?php

namespace App\Filament\Resources\CategoryResource\RelationManagers;

use App\Models\AttributeDefinition;
use App\Services\Catalog\ProductReadCache;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AttributeMappingsRelationManager extends RelationManager
{
    protected static string $relationship = 'attributeMappings';

    protected static ?string $title = 'Filteri atributa';

    protected static ?string $modelLabel = 'mapiranje atributa';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('attribute_definition_id')
                    ->label('Atribut')
                    ->relationship(
                        'attributeDefinition',
                        'name',
                        fn ($query) => $query->whereNull('canonical_attribute_definition_id'),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (AttributeDefinition $record): string => filled($record->display_name)
                            ? "{$record->display_name} ({$record->name})"
                            : $record->name,
                    )
                    ->searchable(['name', 'display_name'])
                    ->preload()
                    ->required()
                    ->disabled(fn (?string $operation): bool => $operation === 'edit'),
                Forms\Components\Toggle::make('is_filter_enabled')
                    ->label('Filter omogućen na shopu')
                    ->helperText('Redoslijed i uključivanje filtera upravljate u tabu "Filteri shopa".')
                    ->default(true),
                Forms\Components\Toggle::make('is_public_enabled')
                    ->label('Vidljiv na stranici proizvoda')
                    ->default(true),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Redoslijed')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('attributeDefinition.display_name')
                    ->label('Atribut')
                    ->description(fn ($record): ?string => filled($record->attributeDefinition?->display_name)
                        ? $record->attributeDefinition?->name
                        : null)
                    ->formatStateUsing(
                        fn ($state, $record): string => filled($state)
                            ? (string) $state
                            : ($record->attributeDefinition?->name ?? '—'),
                    ),
                Tables\Columns\IconColumn::make('attributeDefinition.is_filter')
                    ->label('Globalno')
                    ->boolean()
                    ->tooltip('Da li je atribut globalno označen kao filter'),
                Tables\Columns\IconColumn::make('is_filter_enabled')
                    ->label('Filter shop')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_public_enabled')
                    ->label('PDP')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Redoslijed')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(fn (): mixed => $this->flushCategoryFilters()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn (): mixed => $this->flushCategoryFilters()),
                Tables\Actions\DeleteAction::make()
                    ->after(fn (): mixed => $this->flushCategoryFilters()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(fn (): mixed => $this->flushCategoryFilters()),
                ]),
            ])
            ->emptyStateHeading('Nema mapiranih atributa')
            ->emptyStateDescription('Dodajte atribute koji će biti dostupni kao filteri u ovoj kategoriji.');
    }

    private function flushCategoryFilters(): void
    {
        app(ProductReadCache::class)->flushListAndFilters($this->getOwnerRecord()->id);
    }
}
