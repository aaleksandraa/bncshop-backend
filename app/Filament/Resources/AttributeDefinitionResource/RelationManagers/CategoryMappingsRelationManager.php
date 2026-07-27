<?php

namespace App\Filament\Resources\AttributeDefinitionResource\RelationManagers;

use App\Support\CategoryAdminSearch;
use App\Filament\Forms\CategoryMappingSelect;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryMappingsRelationManager extends RelationManager
{
    protected static string $relationship = 'categoryMappings';

    protected static ?string $title = 'Mapiranje kategorija';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                CategoryMappingSelect::make('category_id')
                    ->label('Kategorija')
                    ->required(),
                Forms\Components\TextInput::make('category_name')
                    ->label('Naziv kategorije (snapshot)')
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_filter_enabled')
                    ->label('Filter omogućen')
                    ->default(true),
                Forms\Components\Toggle::make('is_public_enabled')
                    ->label('Javno vidljivo')
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
            ->modifyQueryUsing(fn ($query) => $query->with(['category' => fn ($q) => $q->withCount('products')]))
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategorija')
                    ->formatStateUsing(fn ($state, $record): string => $record->category !== null
                        ? CategoryAdminSearch::formatOptionLabel($record->category)
                        : '—')
                    ->wrap(),
                Tables\Columns\IconColumn::make('is_filter_enabled')
                    ->label('Filter')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_public_enabled')
                    ->label('Javno')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Redoslijed')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
