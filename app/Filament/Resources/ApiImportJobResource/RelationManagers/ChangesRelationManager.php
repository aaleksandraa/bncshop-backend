<?php

namespace App\Filament\Resources\ApiImportJobResource\RelationManagers;

use App\Filament\Resources\ProductResource;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'changes';

    protected static ?string $title = 'Promjene proizvoda';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('action')
                    ->label('Akcija')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'inserted' => 'Ubačeno',
                        'updated' => 'Izmijenjeno',
                        'deactivated' => 'Deaktivirano',
                        'error' => 'Greška',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'inserted' => 'success',
                        'updated' => 'info',
                        'deactivated' => 'warning',
                        'error' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('external_product_id')
                    ->label('External ID')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Naziv')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('changed_fields')
                    ->label('Izmijenjena polja')
                    ->formatStateUsing(fn ($state): string => is_array($state) && $state !== []
                        ? implode(', ', $state)
                        : '—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Greška')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Vrijeme')
                    ->dateTime('d.m.Y H:i:s'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('Akcija')
                    ->options([
                        'inserted' => 'Ubačeno',
                        'updated' => 'Izmijenjeno',
                        'deactivated' => 'Deaktivirano',
                        'error' => 'Greška',
                    ]),
            ])
            ->recordUrl(fn ($record): ?string => $record->product_id
                ? ProductResource::getUrl('edit', ['record' => $record->product_id])
                : null)
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
