<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Concerns\HasProductBulkActions;
use App\Filament\Concerns\HasSeoFormFields;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Jobs\RunOlxSyncJob;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Pricing\PriceCalculator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                                    ->label('Novo'),
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
                        Forms\Components\Tabs\Tab::make('Cijene')
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
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('Zalihe')
                            ->schema([
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
                                    ->numeric(),
                                Forms\Components\TextInput::make('manual_stock_override')
                                    ->label('Ručni override zalihe')
                                    ->numeric(),
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
}
