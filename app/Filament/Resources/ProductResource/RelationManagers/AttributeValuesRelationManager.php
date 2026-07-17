<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AttributeValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'attributeValues';

    protected static ?string $title = 'Atributi';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('attribute_definition_id')
                    ->label('Atribut')
                    ->relationship('attributeDefinition', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('raw_value')
                    ->label('Vrijednost')
                    ->required()
                    ->maxLength(65535),
                Forms\Components\TextInput::make('normalized_value')
                    ->label('Normalizovana vrijednost')
                    ->maxLength(65535),
                Forms\Components\Toggle::make('is_locked')
                    ->label('Zaključano'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('attribute_name_snapshot')
            ->columns([
                Tables\Columns\TextColumn::make('attributeDefinition.name')
                    ->label('Atribut'),
                Tables\Columns\TextColumn::make('raw_value')
                    ->label('Vrijednost'),
                Tables\Columns\TextColumn::make('normalized_value')
                    ->label('Normalizovano'),
                Tables\Columns\IconColumn::make('is_locked')
                    ->label('Zaključano')
                    ->boolean(),
            ])
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
