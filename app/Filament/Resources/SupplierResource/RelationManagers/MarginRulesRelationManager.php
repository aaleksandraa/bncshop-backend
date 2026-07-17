<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use App\Filament\Forms\MarginCategoryScopeFields;
use App\Models\SupplierCategoryMarginRule;
use App\Services\Pricing\ProductPriceRecalculator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MarginRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'marginRules';

    protected static ?string $title = 'Marže po kategorijama';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('category_id')
                    ->label('Kategorija')
                    ->relationship('category', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->publicName())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Forms\Components\TextInput::make('margin_percentage')
                    ->label('Marža (%)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(500)
                    ->suffix('%'),
                ...MarginCategoryScopeFields::schema(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktivno')
                    ->default(true),
                Forms\Components\Textarea::make('notes')
                    ->label('Napomena')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategorija')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('margin_percentage')
                    ->label('Marža')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subcategory_scope')
                    ->label('Obuhvat')
                    ->formatStateUsing(fn (SupplierCategoryMarginRule $record): string => $record->scopeSummaryLabel())
                    ->badge()
                    ->color(fn (SupplierCategoryMarginRule $record): string => match ($record->subcategory_scope) {
                        'all_descendants' => 'info',
                        'selected' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktivno')
                    ->boolean(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Napomena')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->defaultSort('category.name')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data, RelationManager $livewire): SupplierCategoryMarginRule {
                        $targets = array_map('intval', (array) ($data['target_category_ids'] ?? []));
                        unset($data['target_category_ids']);

                        /** @var SupplierCategoryMarginRule $record */
                        $record = $livewire->getOwnerRecord()->marginRules()->create($data);
                        $this->syncTargets($record, $targets);
                        $this->recalculate($record);

                        return $record;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, SupplierCategoryMarginRule $record): array {
                        $data['target_category_ids'] = $record->targetCategories()->pluck('categories.id')->all();

                        return $data;
                    })
                    ->using(function (array $data, SupplierCategoryMarginRule $record, RelationManager $livewire): SupplierCategoryMarginRule {
                        $targets = array_map('intval', (array) ($data['target_category_ids'] ?? []));
                        unset($data['target_category_ids']);

                        $record->update($data);
                        $this->syncTargets($record, $targets);
                        $this->recalculate($record);

                        return $record;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(fn (SupplierCategoryMarginRule $record) => $this->recalculate($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @param  array<int, int>  $targets
     */
    private function syncTargets(SupplierCategoryMarginRule $record, array $targets): void
    {
        if ($record->subcategory_scope === 'selected') {
            $record->targetCategories()->sync($targets);

            return;
        }

        $record->targetCategories()->sync([]);
    }

    private function recalculate(SupplierCategoryMarginRule $record): void
    {
        app(ProductPriceRecalculator::class)->forMarginRule($record);
    }
}
