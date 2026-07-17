<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Concerns\HasSeoFormFields;
use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers\AttributeMappingsRelationManager;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoryResource extends Resource
{
    use AuthorizesWithPermissions;
    use HasSeoFormFields;

    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?string $modelLabel = 'Kategorija';

    protected static ?string $pluralModelLabel = 'Kategorije';

    protected static ?int $navigationSort = 2;

    protected static function permissionPrefix(): string
    {
        return 'categories';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Kategorija')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Prikaz na shopu')
                            ->icon('heroicon-o-eye')
                            ->schema([
                                Forms\Components\Section::make('Struktura')
                                    ->schema([
                                        Forms\Components\Select::make('parent_id')
                                            ->label('Roditeljska kategorija')
                                            ->relationship('parent', 'name')
                                            ->getOptionLabelFromRecordUsing(fn (Category $record): string => $record->publicName())
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->disabled(fn (?Category $record): bool => (bool) $record?->system),
                                        Forms\Components\TextInput::make('name')
                                            ->label('Naziv iz importa')
                                            ->helperText('Originalni naziv iz API sync-a. Ne mijenja se ručno za sistemske kategorije.')
                                            ->required()
                                            ->maxLength(255)
                                            ->disabled(fn (?Category $record): bool => (bool) $record?->system),
                                        Forms\Components\TextInput::make('display_name')
                                            ->label('Naziv na shopu')
                                            ->helperText('Prikazuje se kupcima na frontendu. Ostavite prazno da se koristi naziv iz importa.')
                                            ->maxLength(255)
                                            ->live(onBlur: true),
                                        Forms\Components\TextInput::make('full_slug')
                                            ->label('URL slug')
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(ignoreRecord: true)
                                            ->helperText('Putanja kategorije, npr. racunari/laptopi')
                                            ->disabled(fn (?Category $record): bool => (bool) $record?->system),
                                        Forms\Components\Select::make('status')
                                            ->label('Status')
                                            ->options([
                                                'active' => 'Aktivna',
                                                'inactive' => 'Neaktivna',
                                            ])
                                            ->required(),
                                    ])
                                    ->columns(2),
                                Forms\Components\Section::make('Sadržaj stranice')
                                    ->schema([
                                        Forms\Components\Textarea::make('short_description')
                                            ->label('Kratki opis')
                                            ->helperText(fn (?string $state): string => 'Hero tekst ispod naslova. Preporučeno 120–160 znakova. Trenutno: '.mb_strlen($state ?? '').'/160')
                                            ->rows(3)
                                            ->maxLength(320),
                                        Forms\Components\RichEditor::make('description')
                                            ->label('Detaljan opis')
                                            ->helperText('SEO sadržaj na dnu stranice kategorije (HTML dozvoljen).')
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('image_url')
                                            ->label('Slika URL')
                                            ->url()
                                            ->maxLength(2048),
                                        Forms\Components\TextInput::make('icon_url')
                                            ->label('Ikona URL')
                                            ->url()
                                            ->maxLength(2048),
                                    ])
                                    ->columns(2),
                            ]),
                        Forms\Components\Tabs\Tab::make('Filteri shopa')
                            ->icon('heroicon-o-funnel')
                            ->schema([
                                Forms\Components\Section::make('Redoslijed i vidljivost filtera')
                                    ->description('Povucite filtere da promijenite redoslijed. Isključite toggle za filtere koje ne želite prikazati kupcima na ovoj kategoriji.')
                                    ->schema([
                                        Forms\Components\Repeater::make('filter_layout')
                                            ->label('Filteri')
                                            ->schema([
                                                Forms\Components\Hidden::make('type'),
                                                Forms\Components\Hidden::make('key'),
                                                Forms\Components\Hidden::make('attribute_definition_id'),
                                                Forms\Components\Hidden::make('label'),
                                                Forms\Components\Placeholder::make('filter_label')
                                                    ->label('Filter')
                                                    ->content(fn (Get $get): string => (string) ($get('label') ?? '—')),
                                                Forms\Components\Toggle::make('enabled')
                                                    ->label('Omogućen na shopu')
                                                    ->default(true),
                                            ])
                                            ->reorderableWithDragAndDrop()
                                            ->addable(false)
                                            ->deletable(false)
                                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Forms\Components\Tabs\Tab::make('SEO')
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Forms\Components\Section::make('SEO optimizacija')
                                    ->description('Meta tagovi i SEO sadržaj za stranicu kategorije.')
                                    ->relationship('seo')
                                    ->schema([
                                        Forms\Components\TextInput::make('meta_title')
                                            ->label('Meta naslov')
                                            ->helperText(fn (?string $state): string => 'Preporučeno do 60 znakova. Trenutno: '.mb_strlen($state ?? '').'/60')
                                            ->maxLength(70)
                                            ->live(onBlur: true)
                                            ->placeholder(fn (Get $get): string => (string) ($get('../../display_name') ?: $get('../../name') ?: '')),
                                        Forms\Components\Textarea::make('meta_description')
                                            ->label('Meta opis')
                                            ->helperText(fn (?string $state): string => 'Preporučeno 140–160 znakova. Trenutno: '.mb_strlen($state ?? '').'/160')
                                            ->rows(3)
                                            ->maxLength(320)
                                            ->live(onBlur: true),
                                        Forms\Components\TextInput::make('og_image_url')
                                            ->label('OG slika URL')
                                            ->helperText('Slika za dijeljenje na društvenim mrežama (1200×630 px).')
                                            ->url()
                                            ->maxLength(2048),
                                        Forms\Components\TextInput::make('h1')
                                            ->label('H1 naslov')
                                            ->helperText('Glavni naslov na stranici. Ako je prazno, koristi se naziv na shopu.')
                                            ->maxLength(255)
                                            ->placeholder(fn (Get $get): string => (string) ($get('../../display_name') ?: $get('../../name') ?: '')),
                                        Forms\Components\Textarea::make('intro_text')
                                            ->label('Uvodni SEO tekst')
                                            ->helperText('Kratki uvod iznad liste proizvoda (1–2 rečenice).')
                                            ->rows(3)
                                            ->maxLength(500),
                                        Forms\Components\Textarea::make('footer_text')
                                            ->label('Footer SEO tekst')
                                            ->helperText('Dodatni tekst ispod proizvoda za SEO (2–4 rečenice).')
                                            ->rows(4)
                                            ->maxLength(2000),
                                    ])
                                    ->columns(1),
                                Forms\Components\Section::make('Napredni SEO override')
                                    ->collapsed()
                                    ->relationship('seoOverride')
                                    ->schema(static::seoFormFields())
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
                Forms\Components\Hidden::make('system'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('public_name')
                    ->label('Naziv na shopu')
                    ->getStateUsing(fn (Category $record): string => $record->publicName())
                    ->description(fn (Category $record): ?string => $record->display_name ? 'Import: '.$record->name : null)
                    ->formatStateUsing(function (string $state, Category $record): string {
                        $indent = str_repeat('  ', max(0, (int) $record->depth));

                        return $indent.$state;
                    })
                    ->searchable(['display_name', 'name'])
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("COALESCE(NULLIF(display_name, ''), name) {$direction}");
                    }),
                Tables\Columns\TextColumn::make('full_slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('short_description')
                    ->label('Kratki opis')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('seo_complete')
                    ->label('SEO')
                    ->getStateUsing(fn (Category $record): bool => $record->hasCompleteSeo())
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-circle')
                    ->trueColor('success')
                    ->falseColor('warning'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Proizvodi')
                    ->counts('products')
                    ->sortable(),
                Tables\Columns\TextColumn::make('enabled_filters')
                    ->label('Filteri')
                    ->getStateUsing(function (Category $record): string {
                        $parts = [];

                        if ($record->filter_price_enabled) {
                            $parts[] = 'Cijena';
                        }
                        if ($record->filter_brand_enabled) {
                            $parts[] = 'Brend';
                        }

                        $attributeCount = (int) ($record->enabled_filter_attributes_count ?? 0);

                        if ($attributeCount > 0) {
                            $parts[] = "{$attributeCount} atr.";
                        }

                        return $parts !== [] ? implode(', ', $parts) : '—';
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('depth')
                    ->label('Nivo')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('path')
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Roditelj')
                    ->relationship('parent', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Category $record): string => $record->publicName())
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktivna',
                        'inactive' => 'Neaktivna',
                    ]),
                Tables\Filters\TernaryFilter::make('seo_complete')
                    ->label('SEO kompletan')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('seo', function (Builder $seoQuery): void {
                            $seoQuery
                                ->whereNotNull('meta_title')->where('meta_title', '!=', '')
                                ->whereNotNull('meta_description')->where('meta_description', '!=', '');
                        })->where(function (Builder $query): void {
                            $query
                                ->whereNotNull('short_description')->where('short_description', '!=', '')
                                ->orWhereHas('seo', fn (Builder $seoQuery) => $seoQuery
                                    ->whereNotNull('intro_text')->where('intro_text', '!=', ''));
                        }),
                        false: fn (Builder $query) => $query->where(function (Builder $query): void {
                            $query
                                ->whereDoesntHave('seo')
                                ->orWhereHas('seo', function (Builder $seoQuery): void {
                                    $seoQuery
                                        ->whereNull('meta_title')->orWhere('meta_title', '=', '')
                                        ->orWhereNull('meta_description')->orWhere('meta_description', '=', '');
                                })
                                ->orWhere(function (Builder $query): void {
                                    $query
                                        ->where(function (Builder $query): void {
                                            $query->whereNull('short_description')->orWhere('short_description', '=', '');
                                        })
                                        ->whereDoesntHave('seo', fn (Builder $seoQuery) => $seoQuery
                                            ->whereNotNull('intro_text')->where('intro_text', '!=', ''));
                                });
                        }),
                    ),
                Tables\Filters\TernaryFilter::make('system')
                    ->label('Sistemska'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Category $record): bool => ! $record->system),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->paginated([25, 50, 100]);
    }

    public static function getRelations(): array
    {
        return [
            AttributeMappingsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['parent', 'seo'])
            ->withCount([
                'attributeMappings as enabled_filter_attributes_count' => fn (Builder $query): Builder => $query
                    ->where('is_filter_enabled', true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
