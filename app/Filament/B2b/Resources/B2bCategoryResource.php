<?php

namespace App\Filament\B2b\Resources;

use App\Filament\B2b\Resources\B2bCategoryResource\Pages;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Models\B2bCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class B2bCategoryResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = B2bCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?string $modelLabel = 'B2B kategorija';

    protected static ?string $pluralModelLabel = 'B2B kategorije';

    protected static ?int $navigationSort = 1;

    protected static function permissionPrefix(): string
    {
        return 'b2b_categories';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Naziv')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
            Forms\Components\TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Forms\Components\Textarea::make('description')
                ->label('Opis')
                ->rows(3),
            Forms\Components\Select::make('parent_id')
                ->label('Roditeljska kategorija')
                ->relationship('parent', 'name')
                ->searchable()
                ->preload()
                ->nullable(),
            Forms\Components\TextInput::make('sort_order')
                ->label('Redoslijed')
                ->numeric()
                ->default(0),
            Forms\Components\Toggle::make('is_active')
                ->label('Aktivna')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Naziv')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\TextColumn::make('products_count')->label('Proizvodi')->counts('products'),
                Tables\Columns\IconColumn::make('is_active')->label('Aktivna')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Redoslijed')->sortable(),
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
            'index' => Pages\ListB2bCategories::route('/'),
            'create' => Pages\CreateB2bCategory::route('/create'),
            'edit' => Pages\EditB2bCategory::route('/{record}/edit'),
        ];
    }
}
