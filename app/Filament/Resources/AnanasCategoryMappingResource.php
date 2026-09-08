<?php

namespace App\Filament\Resources;

use App\Filament\Forms\CategoryMappingSelect;
use App\Filament\Pages\AnanasSyncSettingsPage;
use App\Filament\Resources\AnanasCategoryMappingResource\Pages;
use App\Models\AnanasCategoryMapping;
use App\Models\AnanasProductType;
use App\Support\CategoryAdminSearch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AnanasCategoryMappingResource extends Resource
{
    protected static ?string $model = AnanasCategoryMapping::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Ananas';

    protected static ?string $navigationLabel = 'Mapiranje kategorija';

    protected static ?string $modelLabel = 'Ananas mapiranje kategorije';

    protected static ?string $pluralModelLabel = 'Ananas mapiranje kategorija';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return AnanasSyncSettingsPage::canAccess();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            CategoryMappingSelect::make('category_id')
                ->label('BNC kategorija')
                ->required(),
            Forms\Components\Select::make('ananas_product_type')
                ->label('Ananas product type')
                ->options(fn (): array => AnanasProductType::query()->orderBy('name')->pluck('name', 'name')->all())
                ->searchable()
                ->required()
                ->helperText('Prvo osvježite listu u Ananas postavkama (Osvježi product types).'),
            Forms\Components\TextInput::make('ananas_category')
                ->label('Ananas category (free text)')
                ->maxLength(255),
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
            ->modifyQueryUsing(fn ($query) => $query->with(['category' => fn ($q) => $q->withCount('products')]))
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->label('BNC')
                    ->formatStateUsing(fn ($state, AnanasCategoryMapping $record): string => $record->category !== null
                        ? CategoryAdminSearch::formatOptionLabel($record->category)
                        : '—')
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('ananas_product_type')->label('Product type')->wrap(),
                Tables\Columns\TextColumn::make('ananas_category')->label('Ananas category')->wrap(),
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
            'index' => Pages\ManageAnanasCategoryMappings::route('/'),
        ];
    }
}
