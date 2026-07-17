<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\CouponResource\Pages;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\Tag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Prodaja';

    protected static ?string $modelLabel = 'Kupon';

    protected static ?string $pluralModelLabel = 'Kuponi';

    protected static ?int $navigationSort = 3;

    protected static function permissionPrefix(): string
    {
        return 'coupons';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Osnovno')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Kod')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Select::make('type')
                            ->label('Tip')
                            ->options([
                                'percentage' => 'Postotak',
                                'fixed' => 'Fiksni iznos',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('value')
                            ->label('Vrijednost')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('min_cart_amount')
                            ->label('Min. iznos korpe')
                            ->numeric()
                            ->prefix('KM')
                            ->helperText('Minimalni iznos korpe pri checkoutu (KM).'),
                        Forms\Components\TextInput::make('max_uses')
                            ->label('Max. korištenja')
                            ->numeric(),
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Počinje')
                            ->native(false),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('Završava')
                            ->native(false),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktivan')
                            ->default(true),
                        Forms\Components\Toggle::make('single_use_per_customer')
                            ->label('Jednokratno po kupcu'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Primjena')
                    ->description('Odaberite gdje se kupon primjenjuje. Ako ostane "Cijeli shop", vrijedi za sve proizvode.')
                    ->schema([
                        Forms\Components\Select::make('applicable_scope')
                            ->label('Primjenjivo na')
                            ->options([
                                'all' => 'Cijeli shop',
                                'products' => 'Određeni proizvodi',
                                'categories' => 'Odabrane kategorije',
                                'brands' => 'Odabrani brendovi',
                                'tags' => 'Odabrane oznake (tagovi)',
                            ])
                            ->default('all')
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('applicable_product_ids')
                            ->label('Proizvodi')
                            ->options(fn (): array => Product::query()
                                ->where('is_public', true)
                                ->where('status', 'active')
                                ->orderBy('name')
                                ->limit(500)
                                ->pluck('name', 'id')
                                ->all())
                            ->getSearchResultsUsing(fn (string $search): array => Product::query()
                                ->where('is_public', true)
                                ->where('status', 'active')
                                ->where('name', 'ilike', "%{$search}%")
                                ->orderBy('name')
                                ->limit(50)
                                ->pluck('name', 'id')
                                ->all())
                            ->getOptionLabelsUsing(fn (array $values): array => Product::query()
                                ->whereIn('id', $values)
                                ->pluck('name', 'id')
                                ->all())
                            ->multiple()
                            ->searchable()
                            ->required(fn (Get $get): bool => $get('applicable_scope') === 'products')
                            ->visible(fn (Get $get): bool => $get('applicable_scope') === 'products')
                            ->helperText('Možete odabrati jedan ili više proizvoda.'),
                        Forms\Components\Select::make('applicable_category_ids')
                            ->label('Kategorije')
                            ->options(fn (): array => Category::query()
                                ->orderBy('path')
                                ->get()
                                ->mapWithKeys(fn (Category $category): array => [
                                    $category->id => str_repeat('— ', max(0, (int) $category->depth)).$category->publicName(),
                                ])
                                ->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get): bool => $get('applicable_scope') === 'categories')
                            ->visible(fn (Get $get): bool => $get('applicable_scope') === 'categories')
                            ->helperText('Možete odabrati jednu ili više kategorija.'),
                        Forms\Components\Toggle::make('include_subcategories')
                            ->label('Uključi podkategorije')
                            ->default(true)
                            ->visible(fn (Get $get): bool => $get('applicable_scope') === 'categories'),
                        Forms\Components\Select::make('applicable_manufacturer_ids')
                            ->label('Brendovi')
                            ->options(fn (): array => Manufacturer::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get): bool => $get('applicable_scope') === 'brands')
                            ->visible(fn (Get $get): bool => $get('applicable_scope') === 'brands')
                            ->helperText('Možete odabrati jedan ili više brendova.'),
                        Forms\Components\Select::make('applicable_tag_ids')
                            ->label('Oznake (tagovi)')
                            ->options(fn (): array => Tag::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get): bool => $get('applicable_scope') === 'tags')
                            ->visible(fn (Get $get): bool => $get('applicable_scope') === 'tags')
                            ->helperText('Možete odabrati jednu ili više oznaka.'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Marketing link')
                    ->description('Koristite ove linkove u bannerima. Posjetilac odmah vidi sniženu cijenu i kupon se automatski primjenjuje.')
                    ->schema([
                        Forms\Components\Placeholder::make('marketing_general_link')
                            ->label('Opći link')
                            ->content(function (Get $get): string {
                                $code = $get('code');
                                if (! $code) {
                                    return 'Unesite kod kupona da generišete link.';
                                }

                                $base = rtrim((string) config('bnc.frontend_url'), '/');

                                return "{$base}?kupon=".urlencode((string) $code);
                            }),
                        Forms\Components\Placeholder::make('marketing_product_link')
                            ->label('Link za proizvod')
                            ->content(function (Get $get): string {
                                $code = $get('code');
                                if (! $code) {
                                    return 'Unesite kod kupona da generišete link.';
                                }

                                $base = rtrim((string) config('bnc.frontend_url'), '/');
                                $productIds = $get('applicable_product_ids') ?? [];

                                if ($get('applicable_scope') === 'products' && $productIds !== []) {
                                    $product = Product::query()->find((int) $productIds[0]);
                                    if ($product) {
                                        return "{$base}/proizvod/{$product->slug}?kupon=".urlencode((string) $code);
                                    }
                                }

                                return "{$base}/proizvod/{slug}?kupon=".urlencode((string) $code);
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kod')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tip')
                    ->badge(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Vrijednost'),
                Tables\Columns\TextColumn::make('scope_label')
                    ->label('Primjena')
                    ->getStateUsing(function (Coupon $record): string {
                        $applicable = is_array($record->applicable_to) ? $record->applicable_to : [];
                        $scope = $applicable['scope'] ?? 'all';

                        if ($scope === 'all' && $applicable !== []) {
                            if (($applicable['category_ids'] ?? []) !== []) {
                                $scope = 'categories';
                            } elseif (($applicable['manufacturer_ids'] ?? []) !== []) {
                                $scope = 'brands';
                            }
                        }

                        if ($scope === 'all' && $applicable !== []) {
                            if (($applicable['product_ids'] ?? []) !== []) {
                                $scope = 'products';
                            } elseif (($applicable['tag_ids'] ?? []) !== []) {
                                $scope = 'tags';
                            }
                        }

                        return match ($scope) {
                            'products' => 'Proizvodi ('.count($applicable['product_ids'] ?? []).')',
                            'categories' => 'Kategorije ('.count($applicable['category_ids'] ?? []).')',
                            'brands' => 'Brendovi ('.count($applicable['manufacturer_ids'] ?? []).')',
                            'tags' => 'Tagovi ('.count($applicable['tag_ids'] ?? []).')',
                            default => 'Cijeli shop',
                        };
                    }),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('Korišteno'),
                Tables\Columns\TextColumn::make('max_uses')
                    ->label('Limit'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktivan')
                    ->boolean(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Ističe')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->filters([
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
