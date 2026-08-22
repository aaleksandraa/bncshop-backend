<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\ShopCampaignResource\Pages;
use App\Filament\Support\OptimizedMediaUpload;
use App\Models\Category;
use App\Models\ShopCampaign;
use App\Rules\ValidShopCampaignSlug;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ShopCampaignResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = ShopCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $modelLabel = 'Kampanja';

    protected static ?string $pluralModelLabel = 'Kampanje';

    protected static ?int $navigationSort = 1;

    protected static function permissionPrefix(): string
    {
        return 'shop_campaigns';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Osnovno')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Naziv')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state, ?ShopCampaign $record): void {
                            if ($record !== null) {
                                return;
                            }

                            $set('slug', Str::slug($state ?? ''));
                            $set('page_title', $state);
                        }),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->rules(fn (?ShopCampaign $record): array => [
                            new ValidShopCampaignSlug($record?->id),
                        ])
                        ->helperText('URL: /{slug}'),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Redoslijed bedža')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),
                    OptimizedMediaUpload::configure(
                        Forms\Components\FileUpload::make('badge_path')
                            ->label('Bedž')
                            ->helperText('PNG, JPG, WebP ili SVG. Maks. 2 MB.')
                            ->required(fn (?ShopCampaign $record): bool => $record === null)
                            ->image()
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                            ->imagePreviewHeight('80'),
                        'campaigns/badges',
                    )->columnSpanFull(),
                    Forms\Components\TextInput::make('badge_alt')
                        ->label('Alt tekst bedža')
                        ->maxLength(255),
                ])
                ->columns(2),
            Forms\Components\Section::make('Trajanje')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktivna')
                        ->default(true)
                        ->helperText('Isključite da kampanju ručno ugasite.'),
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('Početak')
                        ->native(false)
                        ->helperText('Opcionalno — kampanja se ne prikazuje prije ovog trenutka.'),
                    Forms\Components\DateTimePicker::make('ends_at')
                        ->label('Kraj')
                        ->native(false)
                        ->helperText('Opcionalno — kampanja se gasi nakon ovog trenutka.'),
                ])
                ->columns(3),
            Forms\Components\Section::make('Ciljanje proizvoda')
                ->schema([
                    Forms\Components\Radio::make('targeting_mode')
                        ->label('Način odabira')
                        ->options([
                            ShopCampaign::TARGETING_CATEGORIES => 'Po kategorijama',
                            ShopCampaign::TARGETING_PRODUCTS => 'Ručno odabrani proizvodi',
                        ])
                        ->default(ShopCampaign::TARGETING_PRODUCTS)
                        ->required()
                        ->live(),
                    Forms\Components\Select::make('categories')
                        ->label('Kategorije')
                        ->relationship(
                            name: 'categories',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn ($query) => $query->forAdminSelect(),
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn (Category $record): string => str_repeat('— ', max(0, (int) $record->depth)).$record->publicName(),
                        )
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => $get('targeting_mode') === ShopCampaign::TARGETING_CATEGORIES)
                        ->visible(fn (Get $get): bool => $get('targeting_mode') === ShopCampaign::TARGETING_CATEGORIES)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('include_subcategories')
                        ->label('Uključi podkategorije')
                        ->default(true)
                        ->visible(fn (Get $get): bool => $get('targeting_mode') === ShopCampaign::TARGETING_CATEGORIES),
                    Forms\Components\Select::make('products')
                        ->label('Proizvodi')
                        ->relationship('products', 'name')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => $get('targeting_mode') === ShopCampaign::TARGETING_PRODUCTS)
                        ->visible(fn (Get $get): bool => $get('targeting_mode') === ShopCampaign::TARGETING_PRODUCTS)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('excludedProducts')
                        ->label('Isključeni proizvodi')
                        ->relationship('excludedProducts', 'name')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => $get('targeting_mode') === ShopCampaign::TARGETING_CATEGORIES)
                        ->helperText('Opcionalno — proizvodi iz odabranih kategorija koji ne trebaju bedž.')
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Stranica kampanje')
                ->schema([
                    Forms\Components\Toggle::make('has_landing_page')
                        ->label('Napravi stranicu')
                        ->default(true)
                        ->live()
                        ->helperText('Npr. bnc.ba/back-to-school sa listom proizvoda.'),
                    Forms\Components\TextInput::make('page_title')
                        ->label('Naslov stranice')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => (bool) $get('has_landing_page')),
                    Forms\Components\Textarea::make('page_description')
                        ->label('Opis iznad proizvoda')
                        ->rows(3)
                        ->visible(fn (Get $get): bool => (bool) $get('has_landing_page'))
                        ->columnSpanFull(),
                    OptimizedMediaUpload::configure(
                        Forms\Components\FileUpload::make('hero_image_path')
                            ->label('Hero slika')
                            ->helperText('Opcionalna slika iznad naslova.')
                            ->image()
                            ->maxSize(4096)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->imagePreviewHeight('120')
                            ->visible(fn (Get $get): bool => (bool) $get('has_landing_page')),
                        'campaigns/heroes',
                    )->columnSpanFull(),
                    Forms\Components\TextInput::make('meta_title')
                        ->label('Meta naslov')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => (bool) $get('has_landing_page')),
                    Forms\Components\Textarea::make('meta_description')
                        ->label('Meta opis')
                        ->rows(3)
                        ->visible(fn (Get $get): bool => (bool) $get('has_landing_page')),
                    Forms\Components\Placeholder::make('preview_url')
                        ->label('Preview URL')
                        ->content(fn (Get $get): string => $get('slug')
                            ? rtrim((string) config('bnc.frontend_url'), '/').'/'.$get('slug')
                            : '—')
                        ->visible(fn (Get $get): bool => (bool) $get('has_landing_page') && filled($get('slug'))),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('badge_path')
                    ->label('Bedž')
                    ->disk(app(\App\Services\Media\MediaStorage::class)->diskName())
                    ->height(40),
                Tables\Columns\TextColumn::make('name')
                    ->label('Naziv')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('targeting_mode')
                    ->label('Ciljanje')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        ShopCampaign::TARGETING_CATEGORIES => 'Kategorije',
                        ShopCampaign::TARGETING_PRODUCTS => 'Proizvodi',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Početak')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Kraj')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktivna')
                    ->boolean(),
                Tables\Columns\IconColumn::make('has_landing_page')
                    ->label('Stranica')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
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
            'index' => Pages\ListShopCampaigns::route('/'),
            'create' => Pages\CreateShopCampaign::route('/create'),
            'edit' => Pages\EditShopCampaign::route('/{record}/edit'),
        ];
    }
}
