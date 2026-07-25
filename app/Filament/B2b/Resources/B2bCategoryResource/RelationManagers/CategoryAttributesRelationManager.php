<?php

namespace App\Filament\B2b\Resources\B2bCategoryResource\RelationManagers;

use App\Models\B2bAttributeDefinition;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryAttributesRelationManager extends RelationManager
{
    protected static string $relationship = 'attributeDefinitions';

    protected static ?string $title = 'Atributi kategorije';

    protected static ?string $modelLabel = 'atribut';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Atribut')->searchable(),
                Tables\Columns\TextColumn::make('input_type')->label('Tip')->badge(),
                Tables\Columns\TextColumn::make('options_count')
                    ->label('Opcije')
                    ->counts('options'),
                Tables\Columns\TextColumn::make('pivot.sort_order')->label('Redoslijed')->sortable(),
            ])
            ->defaultSort('b2b_category_attribute.sort_order')
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('is_active', true))
                    ->recordTitle(fn (B2bAttributeDefinition $record): string => "{$record->name} ({$record->input_type})")
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Redoslijed')
                            ->numeric()
                            ->default(0),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Redoslijed')
                            ->numeric()
                            ->required(),
                    ]),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
