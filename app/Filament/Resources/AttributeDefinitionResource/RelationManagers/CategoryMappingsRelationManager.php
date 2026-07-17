<?php

namespace App\Filament\Resources\AttributeDefinitionResource\RelationManagers;

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
                Forms\Components\Select::make('category_id')
                    ->label('Kategorija')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
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
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategorija'),
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
