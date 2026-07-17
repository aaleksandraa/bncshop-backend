<?php

namespace App\Filament\Resources\ApiImportJobResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Stavke joba';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('page')
                    ->label('Stranica')
                    ->sortable(),
                Tables\Columns\TextColumn::make('records_count')
                    ->label('Zapisi'),
                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('Trajanje (ms)'),
                Tables\Columns\TextColumn::make('errors')
                    ->label('Greške')
                    ->formatStateUsing(fn ($state): string => is_array($state) ? count($state).' grešaka' : '-'),
            ])
            ->defaultSort('page');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
