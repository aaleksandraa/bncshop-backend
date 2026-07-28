<?php

namespace App\Filament\Resources\ApiImportJobResource\RelationManagers;

use App\Filament\Resources\ProductResource;
use App\Services\Sync\ImportJobFieldLabels;
use Filament\Forms\Form;
use Filament\Resources\Components\Tab;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'changes';

    protected static ?string $title = 'Promjene proizvoda';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Svi'),
            'inserted' => Tab::make('Novi')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('action', 'inserted')),
            'updated' => Tab::make('Izmijenjeni')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('action', 'updated')),
            'deactivated' => Tab::make('Deaktivirani')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('action', 'deactivated')),
            'errors' => Tab::make('Greške')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('action', 'error')),
        ];
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
                    ->label('Promjene')
                    ->formatStateUsing(function ($state, $record): string {
                        if ($record->action === 'inserted') {
                            return 'Novi proizvod';
                        }

                        if ($record->action === 'error') {
                            return '—';
                        }

                        if (is_array($state) && $state !== []) {
                            return ImportJobFieldLabels::formatList($state);
                        }

                        if ($record->action === 'updated') {
                            return 'Bez promjene u praćenim poljima';
                        }

                        return '—';
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Greška')
                    ->placeholder('—')
                    ->wrap()
                    ->visible(fn (): bool => $this->activeTab === 'errors'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Vrijeme')
                    ->dateTime('d.m.Y H:i:s'),
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
