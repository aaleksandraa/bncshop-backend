<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Stavke narudžbe';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Proizvod'),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU'),
                Tables\Columns\TextColumn::make('brand_name')
                    ->label('Brend'),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Količina'),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Cijena')
                    ->money('BAM'),
                Tables\Columns\TextColumn::make('final_price')
                    ->label('Finalna cijena')
                    ->money('BAM'),
                Tables\Columns\TextColumn::make('line_total')
                    ->label('Ukupno')
                    ->money('BAM'),
            ])
            ->paginated(false);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
