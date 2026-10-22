<?php

namespace App\Filament\B2b\Resources\B2bProductResource\RelationManagers;

use App\Filament\B2b\Resources\B2bOrderResource;
use App\Models\B2bStockMovement;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StockMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMovements';

    protected static ?string $title = 'Istorija zalihe';

    protected static ?string $modelLabel = 'promjena zalihe';

    protected static ?string $icon = 'heroicon-o-clock';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('Narudžba odmah smanjuje stanje. Ručna izmjena količine bilježi koliko je admin dodao ili smanjio.')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Datum')
                    ->dateTime('d.m.Y. H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('delta')
                    ->label('Promjena')
                    ->formatStateUsing(fn (int|string $state): string => ((int) $state > 0 ? '+' : '').(int) $state)
                    ->color(fn (int|string $state): string => (int) $state > 0 ? 'success' : 'danger')
                    ->badge(),
                Tables\Columns\TextColumn::make('quantity_after')
                    ->label('Stanje')
                    ->formatStateUsing(fn (B2bStockMovement $record): string => $record->quantity_before.' → '.$record->quantity_after),
                Tables\Columns\TextColumn::make('summary')
                    ->label('Prema kome')
                    ->wrap()
                    ->url(fn (B2bStockMovement $record): ?string => $record->b2b_order_id
                        ? B2bOrderResource::getUrl('edit', ['record' => $record->b2b_order_id])
                        : null),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Još nema promjena zalihe')
            ->emptyStateDescription('Ovdje će biti narudžbe kupaca i ručne izmjene stanja.');
    }
}
