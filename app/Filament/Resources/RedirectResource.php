<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\RedirectResource\Pages;
use App\Models\Redirect;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RedirectResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = Redirect::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-right';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $modelLabel = 'Redirect';

    protected static ?string $pluralModelLabel = 'Redirecti';

    protected static ?int $navigationSort = 1;

    protected static function permissionPrefix(): string
    {
        return 'redirects';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('from_path')
                    ->label('Od putanje')
                    ->required()
                    ->maxLength(2048),
                Forms\Components\TextInput::make('to_path')
                    ->label('Do putanje')
                    ->required()
                    ->maxLength(2048),
                Forms\Components\Select::make('status_code')
                    ->label('HTTP status')
                    ->options([
                        301 => '301 Trajno',
                        302 => '302 Privremeno',
                    ])
                    ->default(301)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('from_path')
                    ->label('Od')
                    ->searchable(),
                Tables\Columns\TextColumn::make('to_path')
                    ->label('Do')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status_code')
                    ->label('Status'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Izmjena')
                    ->dateTime('d.m.Y H:i'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRedirects::route('/'),
        ];
    }
}
