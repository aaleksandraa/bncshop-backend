<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Concerns\HasProductBulkActions;
use App\Filament\Concerns\HasSeoFormFields;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Jobs\RunOlxSyncJob;
use App\Models\Product;
use App\Models\ProductGratisOffer;
use App\Models\Supplier;
use App\Filament\Support\OptimizedMediaUpload;
use App\Services\Catalog\ProductGratisService;
use App\Services\Catalog\ProductSetService;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\ProductPriceRecalculator;
use App\Services\Pricing\ProductSalePriceService;
use App\Support\PublicStorageUrl;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    use AuthorizesWithPermissions;
    use HasProductBulkActions;
    use HasSeoFormFields;

    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?string $modelLabel = 'Proizvod';

    protected static ?string $pluralModelLabel = 'Proizvodi';

    protected static ?int $navigationSort = 1;

    protected static function permissionPrefix(): string
    {
        return 'products';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Proizvod')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Osnovno')
                            ->schema([
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
                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('barcode')
                                    ->label('Barkod')
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('description')
                                    ->label('Opis')
                                    ->rows(5)
                                    ->columnSpanFull(),
                                Forms\Components\Textarea::make('short_description')
                                    ->label('Kratki opis')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                Forms\Components\Toggle::make('is_public')
                                    ->label('Javno vidljiv'),
                                Forms\Components\Toggle::make('is_gaming')
                                    ->label('Gaming'),
                                Forms\Components\Toggle::make('is_new')
                                    ->label('Novo')
                                    ->helperText(fn (Forms\Get $get, ?Product $record): ?string => ($get('is_set') || ($record?->import_source === 'manual') || $record === null)
                                        ? 'Ručni proizvodi i setovi bez ove oznake prikazuju se kao polovni (Refurbished).'
                                        : null),
                                Forms\Components\Toggle::make('is_set')
                                    ->label('Ovo je set proizvoda')
                                    ->live(),
                                Forms\Components\Toggle::make('is_refurbished')
                                    ->label('Refurbished')
                                    ->visible(fn (?Product $record): bool => $record?->import_source === 'eline'),
                                Forms\Components\TextInput::make('eline_sifra')
                                    ->label('eLine šifra')
                                    ->disabled()
                                    ->visible(fn (?Product $record): bool => $record?->import_source === 'eline'),
                                Forms\Components\Placeholder::make('import_source_label')
                                    ->label('Izvor')
                                    ->content(fn (?Product $record): string => match ($record?->import_source) {
                                        'eline' => 'eLine ERP',
                                        'manual' => 'Ručno',
                                        default => 'A1 Technoshop',
                                    })
                                    ->visible(fn (?Product $record): bool => $record !== null),
                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'active' => 'Aktivan',
                                        'inactive' => 'Neaktivan',
                                        'archived' => 'Arhiviran',
                                    ])
                                    ->required(),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('Set')
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('is_set'))
                            ->schema([
                                Forms\Components\Repeater::make('set_items')
                                    ->label('Proizvodi u setu')
                                    ->schema([
                                        Forms\Components\Select::make('component_product_id')
                                            ->label('Proizvod')
                                            ->searchable()
                                            ->required()
                                            ->getSearchResultsUsing(function (string $search, ?Product $record): array {
                                                return Product::query()
                                                    ->where('is_set', false)
                                                    ->when($record?->id, fn (Builder $query) => $query->where('id', '!=', $record->id))
                                                    ->where(function (Builder $query) use ($search): void {
                                                        $query
                                                            ->where('name', 'ilike', "%{$search}%")
                                                            ->orWhere('sku', 'ilike', "%{$search}%");
                                                    })
                                                    ->orderBy('name')
                                                    ->limit(50)
                                                    ->get()
                                                    ->mapWithKeys(fn (Product $product): array => [
                                                        $product->id => $product->name.' — '.number_format((float) $product->display_price, 2, ',', '.').' KM',
                                                    ])
                                                    ->all();
                                            })
                                            ->getOptionLabelUsing(fn ($value): ?string => Product::query()->find($value)?->name)
                                            ->live(),
                                        Forms\Components\TextInput::make('quantity')
                                            ->label('Količina')
                                            ->numeric()
                                            ->minValue(1)
                                            ->default(1)
                                            ->required()
                                            ->live(),
                                    ])
                                    ->minItems(2)
                                    ->reorderable()
                                    ->columnSpanFull()
                                    ->live(),
                                Forms\Components\Placeholder::make('set_components_sum')
                                    ->label('Zbir cijena dijelova')
                                    ->content(function (Forms\Get $get): string {
                                        $sum = static::calculateSetComponentsSumFromForm($get('set_items'));

                                        return number_format($sum, 2, ',', '.').' KM';
                                    }),
                                Forms\Components\TextInput::make('manual_price')
                                    ->label('Cijena seta (KM)')
                                    ->numeric()
                                    ->prefix('KM')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->helperText(function (Forms\Get $get): ?string {
                                        $sum = static::calculateSetComponentsSumFromForm($get('set_items'));
                                        $setPrice = (float) ($get('manual_price') ?? 0);

                                        if ($sum <= 0 || $setPrice <= 0) {
                                            return 'Upišite cijenu seta nižu od zbira dijelova za akcijsku ponudu.';
                                        }

                                        if ($setPrice >= $sum) {
                                            return 'Upozorenje: cijena seta nije niža od zbira — nema uštede za kupca.';
                                        }

                                        $savings = $sum - $setPrice;

                                        return 'Ušteda za kupca: '.number_format($savings, 2, ',', '.').' KM';
                                    }),
                                Forms\Components\Section::make('Slika seta')
                                    ->description('Opciono. Bez slike na shopu piše „Nema slike“. Dodatne slike uređujte na tabu Slike.')
                                    ->schema([
                                        Forms\Components\Placeholder::make('set_image_preview')
                                            ->label('Trenutna slika')
                                            ->content(function (?Product $record): HtmlString|string {
                                                if ($record === null || $record->defaultImage === null) {
                                                    return 'Nema slike';
                                                }

                                                $url = PublicStorageUrl::absoluteFromResolved($record->defaultImage->resolvedUrl());

                                                if ($url === null || $url === '') {
                                                    return 'Nema slike';
                                                }

                                                return new HtmlString(
                                                    '<img src="'.e($url).'" alt="'.e($record->name).'" style="max-height:120px;max-width:220px;object-fit:contain;background:#fff;padding:8px;border-radius:8px;border:1px solid #e5e5e5;" />'
                                                );
                                            })
                                            ->visible(fn (?Product $record): bool => $record !== null),
                                        OptimizedMediaUpload::configure(
                                            Forms\Components\FileUpload::make('set_image_upload')
                                                ->label('Upload slike')
                                                ->helperText('PNG, JPG ili WebP. Uploadom zamjenjujete postojeću glavnu sliku.')
                                                ->image()
                                                ->maxSize(5120)
                                                ->imagePreviewHeight('160'),
                                            fn (?Product $record): string => $record
                                                ? 'products/'.Str::slug((string) $record->external_product_id, '_')
                                                : 'products/pending',
                                        ),
                                        Forms\Components\Toggle::make('clear_set_image')
                                            ->label('Ukloni sliku')
                                            ->helperText('Sačuvajte proizvod da set ostane bez slike.')
                                            ->visible(fn (?Product $record): bool => $record?->defaultImage !== null),
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('Gratis')
                            ->visible(fn (Forms\Get $get): bool => ! (bool) $get('is_set'))
                            ->schema([
                                Forms\Components\Placeholder::make('gratis_help')
                                    ->label('Gratis ponude')
                                    ->content('Dodajte jednu ili više gratis ponuda. Tip „Proizvod iz kataloga“ automatski dodaje gift u korpu (0 KM). Tip „Samo tekst i slika“ je samo marketing prikaz na shopu.')
                                    ->columnSpanFull(),
                                Forms\Components\Repeater::make('gratis_offers')
                                    ->label('Ponude')
                                    ->dehydrated(false)
                                    ->schema([
                                        Forms\Components\Hidden::make('id'),
                                        Forms\Components\Radio::make('type')
                                            ->label('Tip ponude')
                                            ->options([
                                                ProductGratisOffer::TYPE_PRODUCT => 'Proizvod iz kataloga (automatski u korpi)',
                                                ProductGratisOffer::TYPE_TEXT => 'Samo tekst i slika (marketing)',
                                            ])
                                            ->default(ProductGratisOffer::TYPE_TEXT)
                                            ->required()
                                            ->live(),
                                        Forms\Components\Select::make('gift_product_id')
                                            ->label('Gratis proizvod')
                                            ->searchable()
                                            ->visible(fn (Forms\Get $get): bool => $get('type') === ProductGratisOffer::TYPE_PRODUCT)
                                            ->required(fn (Forms\Get $get): bool => $get('type') === ProductGratisOffer::TYPE_PRODUCT)
                                            ->getSearchResultsUsing(function (string $search, ?Product $record): array {
                                                return Product::query()
                                                    ->where('is_set', false)
                                                    ->when($record?->id, fn (Builder $query) => $query->where('id', '!=', $record->id))
                                                    ->where(function (Builder $query) use ($search): void {
                                                        $query
                                                            ->where('name', 'ilike', "%{$search}%")
                                                            ->orWhere('sku', 'ilike', "%{$search}%");
                                                    })
                                                    ->orderBy('name')
                                                    ->limit(50)
                                                    ->get()
                                                    ->mapWithKeys(fn (Product $product): array => [
                                                        $product->id => $product->name.' — '.number_format((float) $product->display_price, 2, ',', '.').' KM',
                                                    ])
                                                    ->all();
                                            })
                                            ->getOptionLabelUsing(fn ($value): ?string => Product::query()->find($value)?->name),
                                        Forms\Components\TextInput::make('gift_quantity_per_parent')
                                            ->label('Gratis komada po 1 komadu ovog proizvoda')
                                            ->numeric()
                                            ->minValue(1)
                                            ->default(1)
                                            ->visible(fn (Forms\Get $get): bool => $get('type') === ProductGratisOffer::TYPE_PRODUCT),
                                        Forms\Components\TextInput::make('title')
                                            ->label('Naslov')
                                            ->maxLength(255)
                                            ->required(fn (Forms\Get $get): bool => $get('type') === ProductGratisOffer::TYPE_TEXT)
                                            ->helperText(fn (Forms\Get $get): ?string => $get('type') === ProductGratisOffer::TYPE_PRODUCT
                                                ? 'Opcionalno — ako ostane prazno, koristi se naziv gift proizvoda.'
                                                : null),
                                        Forms\Components\Textarea::make('description')
                                            ->label('Opis')
                                            ->rows(3)
                                            ->columnSpanFull(),
                                        OptimizedMediaUpload::configure(
                                            Forms\Components\FileUpload::make('image_path')
                                                ->label('Promo slika')
                                                ->helperText('Opcionalno. PNG, JPG ili WebP, maks. 5 MB. Za tekstualnu ponudu preporučeno.')
                                                ->image()
                                                ->maxSize(5120)
                                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                                ->imagePreviewHeight('120'),
                                            'products/gratis',
                                        )->columnSpanFull(),
                                        Forms\Components\DateTimePicker::make('starts_at')
                                            ->label('Početak')
                                            ->native(false),
                                        Forms\Components\DateTimePicker::make('ends_at')
                                            ->label('Kraj')
                                            ->native(false),
                                        Forms\Components\Toggle::make('until_stock')
                                            ->label('Do isteka zaliha gift proizvoda')
                                            ->visible(fn (Forms\Get $get): bool => $get('type') === ProductGratisOffer::TYPE_PRODUCT),
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('Aktivno')
                                            ->default(true),
                                    ])
                                    ->columns(2)
                                    ->reorderable()
                                    ->collapsible()
                                    ->columnSpanFull()
                                    ->itemLabel(fn (array $state): ?string => filled($state['title'] ?? null)
                                        ? (string) $state['title']
                                        : (Product::query()->find($state['gift_product_id'] ?? null)?->name)),
                                Forms\Components\Placeholder::make('gratis_preview')
                                    ->label('Aktivne ponude na shopu')
                                    ->visible(fn (?Product $record): bool => $record !== null)
                                    ->content(function (?Product $record): string {
                                        if ($record === null) {
                                            return '—';
                                        }

                                        $offers = app(ProductGratisService::class)->displayPayloadsFor($record->fresh());

                                        if ($offers === []) {
                                            return 'Nema aktivnih gratis ponuda.';
                                        }

                                        return collect($offers)
                                            ->map(fn (array $offer): string => sprintf(
                                                '%s (%s)',
                                                $offer['title'],
                                                $offer['type'] === ProductGratisOffer::TYPE_PRODUCT ? 'proizvod' : 'tekst',
                                            ))
                                            ->implode("\n");
                                    })
                                    ->columnSpanFull(),
                            ]),
                        Forms\Components\Tabs\Tab::make('Cijene')
                            ->visible(fn (Forms\Get $get): bool => ! (bool) $get('is_set'))
                            ->schema([
                                Forms\Components\Placeholder::make('pricing_supplier')
                                    ->label('Odabrani dobavljač')
                                    ->visible(fn (): bool => auth()->user()?->can('view_margin') ?? false)
                                    ->content(function (?Product $record): string {
                                        if (! $record) {
                                            return '—';
                                        }

                                        $result = app(PriceCalculator::class)->calculate($record);

                                        return $result->supplierName ?? '—';
                                    }),
                                Forms\Components\Placeholder::make('pricing_wholesale')
                                    ->label('Nabavna cijena')
                                    ->visible(fn (): bool => auth()->user()?->can('view_margin') ?? false)
                                    ->content(function (?Product $record): string {
                                        if (! $record) {
                                            return '—';
                                        }

                                        $result = app(PriceCalculator::class)->calculate($record);

                                        return $result->wholesalePrice !== null
                                            ? number_format($result->wholesalePrice, 2, '.', '').' KM'
                                            : '—';
                                    }),
                                Forms\Components\Placeholder::make('pricing_margin')
                                    ->label('Primijenjena marža')
                                    ->visible(fn (): bool => auth()->user()?->can('view_margin') ?? false)
                                    ->content(function (?Product $record): string {
                                        if (! $record) {
                                            return '—';
                                        }

                                        $result = app(PriceCalculator::class)->calculate($record);

                                        if ($result->appliedMargin === null) {
                                            return '—';
                                        }

                                        return number_format($result->appliedMargin, 2, '.', '').'% ('.$result->marginSource.')';
                                    }),
                                Forms\Components\Placeholder::make('pricing_calculated')
                                    ->label('Izračunata prodajna cijena')
                                    ->visible(fn (): bool => auth()->user()?->can('view_margin') ?? false)
                                    ->content(function (?Product $record): string {
                                        if (! $record) {
                                            return '—';
                                        }

                                        $result = app(PriceCalculator::class)->calculate($record);
                                        $stored = (float) ($record->regular_price ?? 0);
                                        $calculated = $result->regularPrice;
                                        $formatted = number_format($calculated, 2, '.', '').' KM';

                                        if (round($stored, 2) !== round($calculated, 2)) {
                                            return new HtmlString(
                                                '<span class="text-warning-600 dark:text-warning-400">'
                                                .e($formatted)
                                                .' — nije upisano u redovnu cijenu ('
                                                .e(number_format($stored, 2, '.', ''))
                                                .' KM). Kliknite <strong>Preračunaj cijenu</strong> gore desno.'
                                                .'</span>'
                                            );
                                        }

                                        return $formatted;
                                    }),
                                Forms\Components\TextInput::make('calculated_price')
                                    ->label('Izračunata cijena (spremljena)')
                                    ->numeric()
                                    ->prefix('KM')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Nabavna × marža × PDV, zaokruženo na cijeli KM. Preračun upisuje ovu vrijednost u redovnu cijenu ako cijena nije zaključana.')
                                    ->visible(fn (): bool => auth()->user()?->can('view_margin') ?? false),
                                Forms\Components\Select::make('preferred_supplier_id')
                                    ->label('Preferirani dobavljač')
                                    ->options(function (?Product $record): array {
                                        if (! $record) {
                                            return [];
                                        }

                                        return $record->supplierOffers()
                                            ->with('supplier')
                                            ->get()
                                            ->mapWithKeys(fn ($offer) => [
                                                $offer->supplier_id => $offer->supplier?->label() ?? 'Dobavljač #'.$offer->supplier_id,
                                            ])
                                            ->all();
                                    })
                                    ->searchable()
                                    ->nullable(),
                                Forms\Components\TextInput::make('api_price')
                                    ->label('API cijena')
                                    ->numeric()
                                    ->prefix('KM')
                                    ->disabled(),
                                Forms\Components\TextInput::make('api_final_price')
                                    ->label('API finalna cijena')
                                    ->numeric()
                                    ->prefix('KM')
                                    ->disabled(),
                                Forms\Components\TextInput::make('regular_price')
                                    ->label('Redovna cijena')
                                    ->numeric()
                                    ->prefix('KM'),
                                Forms\Components\TextInput::make('display_price')
                                    ->label('Prikazna cijena')
                                    ->numeric()
                                    ->prefix('KM'),
                                Forms\Components\TextInput::make('manual_price')
                                    ->label('Ručna cijena')
                                    ->numeric()
                                    ->prefix('KM'),
                                Forms\Components\TextInput::make('margin_percentage')
                                    ->label('Marža (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(500)
                                    ->suffix('%')
                                    ->helperText('Automatski se upisuje primijenjena marža (kategorija/pravilo). Ručna izmjena zaključava samo ovaj proizvod. Izmjena kategorije ažurira sve proizvode koji nisu ručno zaključani.')
                                    ->visible(fn (): bool => auth()->user()?->can('view_margin') ?? false),
                                Forms\Components\TextInput::make('api_rebate')
                                    ->label('API rabat')
                                    ->numeric()
                                    ->prefix('KM')
                                    ->disabled(),
                                Forms\Components\DateTimePicker::make('api_rebate_valid_until')
                                    ->label('Rabat važi do')
                                    ->disabled(),
                                Forms\Components\Toggle::make('price_locked')
                                    ->label('Zaključaj cijenu'),
                                Forms\Components\Section::make('Akcijska cijena')
                                    ->description('Lokalna akcija na shopu. Prazno polje uklanja akciju.')
                                    ->schema([
                                        Forms\Components\TextInput::make('sale_price')
                                            ->label('Akcijska cijena')
                                            ->numeric()
                                            ->prefix('KM')
                                            ->dehydrated(false)
                                            ->rule(function (Forms\Get $get, ?Product $record): \Closure {
                                                return function (string $attribute, mixed $value, \Closure $fail) use ($get, $record): void {
                                                    if ($value === null || $value === '') {
                                                        return;
                                                    }

                                                    $regularPrice = (float) ($get('regular_price') ?? 0);
                                                    $apiPrice = (float) ($get('api_price') ?? 0);

                                                    if ($record !== null) {
                                                        $regularPrice = app(ProductSalePriceService::class)
                                                            ->resolveEffectiveRegularPrice($record->fresh());
                                                    } elseif ($apiPrice > 0) {
                                                        $regularPrice = $apiPrice;
                                                    }

                                                    if ((float) $value >= $regularPrice) {
                                                        $fail('Akcijska cijena mora biti manja od redovne cijene.');
                                                    }
                                                };
                                            })
                                            ->helperText(function (Forms\Get $get): string {
                                                if ((bool) $get('price_locked')) {
                                                    return 'Zaključana ručna cijena preskače automatske popuste, ali ručno postavljena akcijska cijena i dalje važi.';
                                                }

                                                return 'Mora biti manja od redovne cijene. Ostavite prazno da uklonite akciju.';
                                            }),
                                        Forms\Components\Radio::make('sale_validity')
                                            ->label('Važenje akcije')
                                            ->options([
                                                ProductSalePriceService::VALIDITY_NO_END => 'Nema kraja',
                                                ProductSalePriceService::VALIDITY_UNTIL_DATE => 'Do datuma',
                                                ProductSalePriceService::VALIDITY_UNTIL_STOCK => 'Do isteka zaliha',
                                            ])
                                            ->default(ProductSalePriceService::VALIDITY_NO_END)
                                            ->live()
                                            ->dehydrated(false),
                                        Forms\Components\DateTimePicker::make('sale_ends_at')
                                            ->label('Akcija važi do')
                                            ->native(false)
                                            ->visible(fn (Forms\Get $get): bool => $get('sale_validity') === ProductSalePriceService::VALIDITY_UNTIL_DATE)
                                            ->dehydrated(false),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('Zalihe')
                            ->schema([
                                Forms\Components\Placeholder::make('set_available_stock')
                                    ->label('Dostupno setova')
                                    ->visible(fn (Forms\Get $get, ?Product $record): bool => (bool) $get('is_set') && $record !== null)
                                    ->content(function (?Product $record): string {
                                        if ($record === null) {
                                            return 'Sačuvajte set da vidite dostupnost.';
                                        }

                                        $available = app(ProductSetService::class)->availableSetQuantity($record);

                                        return (string) $available;
                                    }),
                                Forms\Components\TextInput::make('api_stock')
                                    ->label('API zaliha')
                                    ->numeric()
                                    ->disabled(),
                                Forms\Components\TextInput::make('reserved_stock')
                                    ->label('Rezervisano')
                                    ->numeric()
                                    ->disabled(),
                                Forms\Components\TextInput::make('available_stock')
                                    ->label('Dostupno')
                                    ->numeric()
                                    ->disabled(fn (Forms\Get $get): bool => (bool) $get('is_set')),
                                Forms\Components\TextInput::make('manual_stock_override')
                                    ->label('Ručni override zalihe')
                                    ->numeric()
                                    ->visible(fn (Forms\Get $get): bool => ! (bool) $get('is_set')),
                                Forms\Components\Select::make('stock_status')
                                    ->label('Status zalihe')
                                    ->options([
                                        'in_stock' => 'Na stanju',
                                        'store_available' => 'Dostupno u radnji (eLine)',
                                        'out_of_stock' => 'Nema na stanju',
                                        'backorder' => 'Prednarudžba',
                                    ]),
                                Forms\Components\Toggle::make('allow_backorder')
                                    ->label('Dozvoli prednarudžbu'),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('Kategorija/Brend')
                            ->schema([
                                Forms\Components\Select::make('category_id')
                                    ->label('Kategorija')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('manufacturer_id')
                                    ->label('Brend')
                                    ->relationship('manufacturer', 'name')
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\Select::make('tags')
                                    ->label('Oznake')
                                    ->relationship('tags', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('OLX')
                            ->schema([
                                Forms\Components\TextInput::make('olx_listing_id')
                                    ->label('OLX listing ID')
                                    ->disabled(),
                                Forms\Components\TextInput::make('olx_listing_status')
                                    ->label('OLX status')
                                    ->disabled(),
                                Forms\Components\DateTimePicker::make('olx_synced_at')
                                    ->label('Zadnji OLX sync')
                                    ->disabled(),
                                Forms\Components\Textarea::make('olx_last_error')
                                    ->label('OLX greška')
                                    ->disabled()
                                    ->columnSpanFull(),
                                Forms\Components\Toggle::make('olx_export_enabled')
                                    ->label('Export na OLX')
                                    ->helperText('Null = prati globalna pravila; isključite za pojedinačni skip.'),
                                Forms\Components\Toggle::make('olx_managed')
                                    ->label('Managed OLX oglas')
                                    ->helperText('Legacy oglasi su read-only dok ovo nije uključeno.'),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('SEO')
                            ->schema([
                                Forms\Components\Section::make()
                                    ->relationship('seoOverride')
                                    ->schema(static::seoFormFields())
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('defaultImage.public_url')
                    ->label('Slika')
                    ->defaultImageUrl(fn (Product $record) => $record->defaultImage?->image_url ?? $record->images()->where('is_primary', true)->value('public_url'))
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Naziv')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('eline_sifra')
                    ->label('eLine šifra')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('import_source')
                    ->label('Izvor')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'eline' => 'eLine',
                        'manual' => 'Ručno',
                        default => 'A1',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('barcode')
                    ->label('Barkod')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('manufacturer.name')
                    ->label('Brend')
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategorija')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('preferredSupplier.display_name')
                    ->label('Dobavljač')
                    ->placeholder(fn (Product $record): string => $record->supplierOffers()
                        ->where('is_selected_price_source', true)
                        ->with('supplier')
                        ->first()?->supplier?->label() ?? '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('regular_price')
                    ->label('Cijena')
                    ->money('BAM')
                    ->sortable(),
                Tables\Columns\TextColumn::make('calculated_price')
                    ->label('Izračunata')
                    ->money('BAM')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => auth()->user()?->can('view_margin') ?? false),
                Tables\Columns\TextColumn::make('display_price')
                    ->label('Finalna cijena')
                    ->money('BAM')
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_stock')
                    ->label('Zaliha')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'archived' => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\IconColumn::make('is_public')
                    ->label('Javno')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_new')
                    ->label('Novo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('is_set')
                    ->label('Tip')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Set' : 'Proizvod')
                    ->color(fn (bool $state): string => $state ? 'info' : 'gray'),
                Tables\Columns\IconColumn::make('is_refurbished')
                    ->label('Refurbished')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sync_status')
                    ->label('Sync')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'synced' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Izmjena')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('manufacturer_id')
                    ->label('Brend')
                    ->relationship('manufacturer', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('category_id')
                    ->label('Kategorija')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktivan',
                        'inactive' => 'Neaktivan',
                        'archived' => 'Arhiviran',
                    ]),
                SelectFilter::make('stock_status')
                    ->label('Zaliha')
                    ->options([
                        'in_stock' => 'Na stanju',
                        'store_available' => 'Dostupno u radnji (eLine)',
                        'out_of_stock' => 'Nema na stanju',
                        'backorder' => 'Prednarudžba',
                    ]),
                TernaryFilter::make('is_public')
                    ->label('Javno'),
                TernaryFilter::make('is_gaming')
                    ->label('Gaming'),
                TernaryFilter::make('is_new')
                    ->label('Novo'),
                TernaryFilter::make('is_set')
                    ->label('Set'),
                TernaryFilter::make('is_refurbished')
                    ->label('Refurbished'),
                SelectFilter::make('import_source')
                    ->label('Izvor')
                    ->options([
                        'a1' => 'A1 Technoshop',
                        'eline' => 'eLine ERP',
                        'manual' => 'Ručno',
                    ]),
                TernaryFilter::make('has_image')
                    ->label('Ima sliku')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('images'),
                        false: fn (Builder $query) => $query->whereDoesntHave('images'),
                    ),
                TernaryFilter::make('has_seo')
                    ->label('Ima SEO')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('seoOverride'),
                        false: fn (Builder $query) => $query->whereDoesntHave('seoOverride'),
                    ),
                Filter::make('on_sale')
                    ->label('Na akciji')
                    ->query(fn (Builder $query): Builder => $query->whereHas('discounts', fn (Builder $q) => $q
                        ->where('is_active', true)
                        ->where(fn (Builder $inner) => $inner
                            ->whereNull('starts_at')
                            ->orWhere('starts_at', '<=', now()))
                        ->where(fn (Builder $inner) => $inner
                            ->whereNull('ends_at')
                            ->orWhere('ends_at', '>=', now())))),
                Filter::make('price_mismatch')
                    ->label('Cijena nije usklađena')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('price_locked', false)
                        ->where('is_set', false)
                        ->notFromEline()
                        ->where(function (Builder $inner): void {
                            $inner
                                ->whereNull('calculated_price')
                                ->orWhereRaw('ROUND(COALESCE(regular_price, 0), 2) <> ROUND(calculated_price, 2)');
                        })),
                SelectFilter::make('supplier')
                    ->label('Dobavljač')
                    ->options(fn (): array => Supplier::query()
                        ->orderBy('sort_order')
                        ->get()
                        ->mapWithKeys(fn (Supplier $supplier): array => [$supplier->id => $supplier->label()])
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $supplierId): Builder => $q->whereHas('supplierOffers', fn (Builder $offer) => $offer->where('supplier_id', $supplierId))
                    )),
                SelectFilter::make('sync_status')
                    ->label('Sync status')
                    ->options([
                        'synced' => 'Sinhronizovano',
                        'error' => 'Greška',
                        'pending' => 'Na čekanju',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('recalculatePrice')
                    ->label('Preračunaj cijenu')
                    ->icon('heroicon-o-calculator')
                    ->requiresConfirmation()
                    ->modalHeading('Preračunaj cijenu')
                    ->modalDescription('Upisuje nabavna × marža × PDV u izračunatu i redovnu cijenu. Zaključane cijene se ne prepisuju.')
                    ->visible(fn (Product $record): bool => ! $record->isSet() && ! $record->isFromEline())
                    ->action(function (Product $record): void {
                        app(ProductPriceRecalculator::class)->forProduct($record);

                        $fresh = $record->fresh();

                        \Filament\Notifications\Notification::make()
                            ->title('Cijena preračunata')
                            ->body('Redovna cijena: '.number_format((float) $fresh->regular_price, 2, '.', '').' KM')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('togglePublic')
                    ->label(fn (Product $record) => $record->is_public ? 'Sakrij' : 'Prikaži')
                    ->icon('heroicon-o-eye')
                    ->action(fn (Product $record) => $record->update(['is_public' => ! $record->is_public])),
                Tables\Actions\Action::make('disableElineImport')
                    ->label('Isključi iz eLine')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Product $record): bool => $record->import_source === 'eline'
                        && filled($record->eline_sifra))
                    ->action(function (Product $record): void {
                        \App\Models\ElineProductOverride::query()->updateOrCreate(
                            ['eline_sifra' => (string) $record->eline_sifra],
                            ['is_enabled' => false],
                        );

                        $record->update(['is_public' => false]);
                    }),
                Tables\Actions\Action::make('syncOlx')
                    ->label('Sync OLX')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (Product $record): bool => $record->olx_export_enabled !== false)
                    ->action(function (Product $record): void {
                        RunOlxSyncJob::dispatch(productId: $record->id);
                    }),
                Tables\Actions\Action::make('archive')
                    ->label('Arhiviraj')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (Product $record) => $record->update(['status' => 'archived'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ...static::productBulkActions(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AttributeValuesRelationManager::class,
            RelationManagers\ImagesRelationManager::class,
            RelationManagers\SupplierOffersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['manufacturer', 'category', 'defaultImage', 'seoOverride', 'preferredSupplier']);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $items
     */
    public static function calculateSetComponentsSumFromForm(?array $items): float
    {
        if ($items === null || $items === []) {
            return 0.0;
        }

        $ids = collect($items)
            ->pluck('component_product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return 0.0;
        }

        $products = Product::query()->whereIn('id', $ids)->get()->keyBy('id');

        return round(
            collect($items)->sum(function (array $row) use ($products): float {
                $product = $products->get((int) ($row['component_product_id'] ?? 0));

                if ($product === null) {
                    return 0.0;
                }

                return (float) $product->display_price * max(1, (int) ($row['quantity'] ?? 1));
            }),
            2,
        );
    }
}
