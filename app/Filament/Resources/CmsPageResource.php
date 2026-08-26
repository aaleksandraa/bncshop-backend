<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\CmsPageResource\Pages;
use App\Models\CmsPage;
use App\Models\Product;
use App\Rules\ValidCmsPageSlug;
use App\Support\ProductAdminSearch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CmsPageResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = CmsPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $modelLabel = 'Stranica';

    protected static ?string $pluralModelLabel = 'Stranice';

    protected static ?int $navigationSort = 2;

    protected static function permissionPrefix(): string
    {
        return 'pages';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Sadržaj')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Naslov')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state, ?CmsPage $record): void {
                                if ($record !== null) {
                                    return;
                                }

                                $set('slug', Str::slug($state ?? ''));
                            }),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(fn (?CmsPage $record): array => [
                                new ValidCmsPageSlug($record?->id),
                            ])
                            ->helperText('URL: /{slug}'),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Objavljena',
                                'draft' => 'Nacrt',
                            ])
                            ->default('draft')
                            ->required(),
                        Forms\Components\RichEditor::make('body')
                            ->label('Sadržaj')
                            ->helperText('Sadržaj stranice. Ako je katalog uključen, prikazuje se kao uvod iznad proizvoda.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Katalog proizvoda')
                    ->description('Odaberite proizvode koji se prikazuju na ovoj stranici, slično kampanji — bez bedža na karticama.')
                    ->schema([
                        Forms\Components\Toggle::make('has_product_listing')
                            ->label('Prikaži odabrane proizvode na stranici')
                            ->default(false)
                            ->live()
                            ->helperText('Uključite da stranica bude katalog, npr. /polovni-laptopi sa ručno odabranim laptopima.'),
                        Forms\Components\Select::make('products')
                            ->label('Proizvodi')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->searchable()
                            ->searchDebounce(300)
                            ->getSearchResultsUsing(fn (string $search): array => ProductAdminSearch::optionsForSearch($search))
                            ->getOptionLabelsUsing(fn (array $values): array => self::productOptionLabels($values))
                            ->helperText('Pretražite po nazivu, brendu, SKU-u ili ID-u. Redoslijed odabira ne mijenja sortiranje na shopu (cijena, najnovije…).')
                            ->visible(fn (Get $get): bool => (bool) $get('has_product_listing'))
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('SEO')
                    ->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->label('Meta naslov')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('meta_description')
                            ->label('Meta opis')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Naslov')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                Tables\Columns\IconColumn::make('has_product_listing')
                    ->label('Katalog')
                    ->boolean(),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Proizvoda')
                    ->counts('products')
                    ->sortable(),
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
            'index' => Pages\ListCmsPages::route('/'),
            'create' => Pages\CreateCmsPage::route('/create'),
            'edit' => Pages\EditCmsPage::route('/{record}/edit'),
        ];
    }

    /**
     * @param  array<int|string>  $values
     * @return array<string, string>
     */
    private static function productOptionLabels(array $values): array
    {
        if ($values === []) {
            return [];
        }

        return Product::query()
            ->whereIn('id', $values)
            ->get(['id', 'name', 'sku'])
            ->mapWithKeys(fn (Product $product): array => [
                (string) $product->id => ProductAdminSearch::formatOptionLabel($product),
            ])
            ->all();
    }
}
