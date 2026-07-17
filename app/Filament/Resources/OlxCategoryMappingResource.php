<?php

namespace App\Filament\Resources;

use App\Filament\Pages\OlxSyncSettingsPage;
use App\Filament\Resources\OlxCategoryMappingResource\Pages;
use App\Models\Category;
use App\Models\OlxCategory;
use App\Models\OlxCategoryMapping;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OlxCategoryMappingResource extends Resource
{
    protected static ?string $model = OlxCategoryMapping::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'OLX';

    protected static ?string $navigationLabel = 'Mapiranje kategorija';

    protected static ?string $modelLabel = 'OLX mapiranje kategorije';

    protected static ?string $pluralModelLabel = 'OLX mapiranje kategorija';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return OlxSyncSettingsPage::canAccess();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('category_id')
                ->label('BNC kategorija')
                ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required(),
            Forms\Components\Select::make('olx_category_id')
                ->label('OLX kategorija')
                ->options(fn (): array => OlxCategory::query()->orderBy('path')->pluck('path', 'id')->all())
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('olx_category_path')
                ->label('OLX putanja (cache)')
                ->disabled(),
            Forms\Components\Toggle::make('is_enabled')
                ->label('Uključeno za export')
                ->default(false),
            Forms\Components\Toggle::make('include_descendants')
                ->label('Uključi podkategorije')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.name')->label('BNC')->searchable(),
                Tables\Columns\TextColumn::make('olx_category_path')->label('OLX putanja')->wrap(),
                Tables\Columns\TextColumn::make('olx_category_id')->label('OLX ID'),
                Tables\Columns\IconColumn::make('is_enabled')->label('Uključeno')->boolean(),
                Tables\Columns\IconColumn::make('include_descendants')->label('Podkategorije')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_enabled')->label('Uključeno'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageOlxCategoryMappings::route('/'),
        ];
    }
}
