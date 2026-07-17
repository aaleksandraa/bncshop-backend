<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\DiscountResource\Pages;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\Discount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DiscountResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = Discount::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Prodaja';

    protected static ?string $modelLabel = 'Popust';

    protected static ?string $pluralModelLabel = 'Popusti';

    protected static ?int $navigationSort = 4;

    protected static function permissionPrefix(): string
    {
        return 'discounts';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Osnovno')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Naziv')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('type')
                            ->label('Tip popusta')
                            ->options([
                                'product' => 'Proizvod',
                                'category' => 'Kategorija',
                                'brand' => 'Brend',
                                'tag' => 'Oznaka',
                                'attribute' => 'Atribut',
                            ])
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('discount_type')
                            ->label('Vrsta')
                            ->options([
                                'percentage' => 'Postotak',
                                'fixed' => 'Fiksni iznos',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('value')
                            ->label('Vrijednost')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('badge_text')
                            ->label('Badge tekst'),
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Počinje')
                            ->native(false),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('Završava')
                            ->native(false),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktivan')
                            ->default(true),
                        Forms\Components\Toggle::make('combines_with_coupons')
                            ->label('Kombinuje se s kuponima'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Cilj')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Proizvod')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('type') === 'product'),
                        Forms\Components\Select::make('categories')
                            ->label('Kategorije')
                            ->relationship(
                                name: 'categories',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->forAdminSelect(),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (Category $record): string => str_repeat('— ', max(0, (int) $record->depth)).$record->publicName()
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Odaberite jednu ili više kategorija.')
                            ->visible(fn (Get $get): bool => $get('type') === 'category'),
                        Forms\Components\Toggle::make('include_subcategories')
                            ->label('Uključi podkategorije')
                            ->default(true)
                            ->helperText('Popust vrijedi i za proizvode u podkategorijama odabranih kategorija.')
                            ->visible(fn (Get $get): bool => $get('type') === 'category'),
                        Forms\Components\Select::make('manufacturers')
                            ->label('Brendovi')
                            ->relationship('manufacturers', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Odaberite jedan ili više brendova.')
                            ->visible(fn (Get $get): bool => $get('type') === 'brand'),
                        Forms\Components\Select::make('tag_id')
                            ->label('Oznaka')
                            ->relationship('tag', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('type') === 'tag'),
                        Forms\Components\Select::make('conditions_json.attribute_definition_id')
                            ->label('Atribut')
                            ->options(fn (): array => AttributeDefinition::query()->pluck('name', 'id')->all())
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('type') === 'attribute'),
                        Forms\Components\TextInput::make('conditions_json.value')
                            ->label('Vrijednost atributa')
                            ->visible(fn (Get $get): bool => $get('type') === 'attribute'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Isključenja')
                    ->schema([
                        Forms\Components\Select::make('excludedProducts')
                            ->label('Isključeni proizvodi')
                            ->relationship('excludedProducts', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('excludedBrands')
                            ->label('Isključeni brendovi')
                            ->relationship('excludedBrands', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Naziv')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('scope_summary')
                    ->label('Cilj')
                    ->getStateUsing(function (Discount $record): string {
                        return match ($record->type) {
                            'category' => 'Kat. '.($record->categories_count ?? $record->categories()->count()),
                            'brand' => 'Brend. '.($record->manufacturers_count ?? $record->manufacturers()->count()),
                            'product' => $record->product?->name ?? '—',
                            'tag' => $record->tag?->name ?? '—',
                            default => $record->type,
                        };
                    }),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tip')
                    ->badge(),
                Tables\Columns\TextColumn::make('discount_type')
                    ->label('Vrsta'),
                Tables\Columns\TextColumn::make('value')
                    ->label('Vrijednost'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktivan')
                    ->boolean(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Ističe')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tip')
                    ->options([
                        'product' => 'Proizvod',
                        'category' => 'Kategorija',
                        'brand' => 'Brend',
                        'tag' => 'Oznaka',
                        'attribute' => 'Atribut',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktivan'),
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

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['categories', 'manufacturers'])
            ->with(['product', 'tag']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiscounts::route('/'),
            'create' => Pages\CreateDiscount::route('/create'),
            'edit' => Pages\EditDiscount::route('/{record}/edit'),
        ];
    }
}
