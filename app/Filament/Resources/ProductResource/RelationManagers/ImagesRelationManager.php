<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Slike';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('image_url')
                    ->label('URL slike')
                    ->url()
                    ->required()
                    ->maxLength(2048),
                Forms\Components\TextInput::make('public_url')
                    ->label('Javni URL')
                    ->url()
                    ->maxLength(2048),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Redoslijed')
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_primary')
                    ->label('Glavna slika'),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktivna',
                        'pending' => 'Na čekanju',
                        'failed' => 'Greška',
                    ])
                    ->default('active'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('image_url')
            ->columns([
                Tables\Columns\ImageColumn::make('public_url')
                    ->label('Slika')
                    ->defaultImageUrl(fn ($record) => $record->image_url),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Redoslijed')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Glavna')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
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
