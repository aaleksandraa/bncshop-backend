<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Product;
use App\Services\Pricing\ProductPriceRecalculator;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierOffersRelationManager extends RelationManager
{
    protected static string $relationship = 'supplierOffers';

    protected static ?string $title = 'Dobavljači';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('supplier.display_name')
                    ->label('Dobavljač')
                    ->placeholder(fn ($record) => $record->supplier?->name),
                Tables\Columns\TextColumn::make('supplier.code')
                    ->label('Kod')
                    ->badge(),
                Tables\Columns\TextColumn::make('supplier_sku')
                    ->label('Dobavljačka šifra'),
                Tables\Columns\TextColumn::make('supplier_price')
                    ->label('Nabavna cijena')
                    ->money('BAM')
                    ->visible(fn (): bool => auth()->user()?->can('view_margin') ?? false),
                Tables\Columns\TextColumn::make('supplier_stock')
                    ->label('Zaliha'),
                Tables\Columns\IconColumn::make('is_selected_price_source')
                    ->label('API izvor')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_preferred')
                    ->label('Preferiran')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $this->getOwnerRecord()->preferred_supplier_id === $record->supplier_id),
            ])
            ->actions([
                Tables\Actions\Action::make('setPreferred')
                    ->label('Postavi preferiranog')
                    ->icon('heroicon-o-star')
                    ->visible(fn (): bool => auth()->user()?->can('manage_products') ?? false)
                    ->action(function ($record): void {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();
                        $product->update(['preferred_supplier_id' => $record->supplier_id]);
                        app(ProductPriceRecalculator::class)->forProduct($product);
                    }),
            ]);
    }
}
