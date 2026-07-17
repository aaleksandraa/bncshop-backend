<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\BlogPostResource\Pages;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Components\Builder;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BlogPostResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = BlogPost::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $modelLabel = 'Blog članak';

    protected static ?string $pluralModelLabel = 'Blog članci';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    protected static function permissionPrefix(): string
    {
        return 'blog_posts';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Osnovno')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Naslov')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Set $set, Get $get, ?string $state, ?BlogPost $record): void {
                                        if ($record !== null) {
                                            return;
                                        }

                                        $set('slug', Str::slug($state ?? ''));

                                        if (blank($get('meta_title'))) {
                                            $set('meta_title', $state);
                                        }
                                    }),
                                Forms\Components\TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('URL: /blog/{slug}'),
                                Forms\Components\Textarea::make('excerpt')
                                    ->label('Kratak opis')
                                    ->rows(3)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Forms\Set $set, Get $get, ?string $state): void {
                                        if (blank($get('meta_description')) && filled($state)) {
                                            $set('meta_description', Str::limit($state, 160));
                                        }
                                    })
                                    ->helperText('Prikazuje se u listi članaka, SEO-u i dijeljenju na društvenim mrežama.')
                                    ->columnSpanFull(),
                                Forms\Components\RichEditor::make('intro')
                                    ->label('Uvod')
                                    ->helperText('Kratki uvod prije glavnog sadržaja.')
                                    ->columnSpanFull(),
                            ]),
                        Forms\Components\Section::make('Sadržaj članka')
                            ->description('Dodajte blokove teksta, proizvoda, kategorija ili brendova.')
                            ->schema([
                                Builder::make('content_blocks')
                                    ->label('')
                                    ->blocks(static::contentBlocks())
                                    ->blockNumbers(false)
                                    ->collapsible()
                                    ->cloneable()
                                    ->addActionLabel('Dodaj blok')
                                    ->columnSpanFull(),
                            ]),
                        Forms\Components\Section::make('SEO')
                            ->schema([
                                Forms\Components\TextInput::make('meta_title')
                                    ->label('Meta naslov')
                                    ->maxLength(255)
                                    ->helperText('Ako ostane prazno, koristi se naslov članka.'),
                                Forms\Components\Textarea::make('meta_description')
                                    ->label('Meta opis')
                                    ->rows(3)
                                    ->helperText('Ako ostane prazno, koristi se kratak opis.'),
                                Forms\Components\TextInput::make('og_image_url')
                                    ->label('OG slika (URL)')
                                    ->url()
                                    ->maxLength(2048)
                                    ->helperText('Za dijeljenje na društvenim mrežama. Ako ostane prazno, koristi se naslovna slika.')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Objava')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'draft' => 'Nacrt',
                                        'published' => 'Objavljeno',
                                    ])
                                    ->default('draft')
                                    ->required()
                                    ->live(),
                                Forms\Components\DateTimePicker::make('published_at')
                                    ->label('Datum objave')
                                    ->native(false)
                                    ->helperText('Automatski se postavlja pri prvoj objavi.'),
                                Forms\Components\Placeholder::make('public_url')
                                    ->label('Javni link')
                                    ->content(fn (?BlogPost $record): string => $record?->slug
                                        ? $record->frontendUrl()
                                        : 'Dostupno nakon snimanja.'),
                            ]),
                        Forms\Components\Section::make('Naslovna slika')
                            ->schema([
                                Forms\Components\FileUpload::make('featured_image_path')
                                    ->label('Upload slike')
                                    ->image()
                                    ->disk('public')
                                    ->directory('blog/featured')
                                    ->visibility('public')
                                    ->maxSize(4096)
                                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                    ->imagePreviewHeight('180')
                                    ->helperText('Preporučeno 1200×630 px. Koristi se kao thumbnail i OG slika.')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('featured_image_url')
                                    ->label('Ili eksterni URL slike')
                                    ->url()
                                    ->maxLength(2048)
                                    ->helperText('Koristi se samo ako nije uploadovana slika.')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    /**
     * @return array<int, Builder\Block>
     */
    protected static function contentBlocks(): array
    {
        return [
            Builder\Block::make('rich_text')
                ->label('Tekst')
                ->icon('heroicon-o-document-text')
                ->schema([
                    Forms\Components\RichEditor::make('body')
                        ->label('Sadržaj')
                        ->required()
                        ->columnSpanFull(),
                ]),
            Builder\Block::make('products_showcase')
                ->label('Proizvodi')
                ->icon('heroicon-o-shopping-bag')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Naslov bloka')
                        ->maxLength(255),
                    Forms\Components\Select::make('layout')
                        ->label('Prikaz')
                        ->options(static::showcaseLayoutOptions())
                        ->default('carousel')
                        ->required(),
                    Forms\Components\Select::make('product_ids')
                        ->label('Proizvodi')
                        ->multiple()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Product::query()
                            ->where('name', 'ilike', '%'.$search.'%')
                            ->orderBy('name')
                            ->limit(50)
                            ->pluck('name', 'id')
                            ->all())
                        ->getOptionLabelsUsing(fn (array $values): array => Product::query()
                            ->whereIn('id', $values)
                            ->pluck('name', 'id')
                            ->all())
                        ->helperText('Odaberite jedan ili više proizvoda za prikaz u carouselu, mreži ili redu.')
                        ->required(),
                ]),
            Builder\Block::make('categories_showcase')
                ->label('Kategorije')
                ->icon('heroicon-o-rectangle-stack')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Naslov bloka')
                        ->maxLength(255),
                    Forms\Components\Select::make('layout')
                        ->label('Prikaz')
                        ->options([
                            'carousel' => 'Carousel',
                            'grid' => 'Mreža',
                        ])
                        ->default('carousel')
                        ->required(),
                    Forms\Components\Select::make('category_ids')
                        ->label('Kategorije')
                        ->multiple()
                        ->searchable()
                        ->options(fn (): array => Category::query()
                            ->forAdminSelect()
                            ->get()
                            ->mapWithKeys(fn (Category $category): array => [
                                $category->id => str_repeat('— ', max(0, (int) $category->depth)).$category->publicName(),
                            ])
                            ->all())
                        ->required(),
                ]),
            Builder\Block::make('brands_showcase')
                ->label('Brendovi')
                ->icon('heroicon-o-building-storefront')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Naslov bloka')
                        ->maxLength(255),
                    Forms\Components\Select::make('layout')
                        ->label('Prikaz')
                        ->options([
                            'carousel' => 'Carousel',
                            'grid' => 'Mreža',
                        ])
                        ->default('carousel')
                        ->required(),
                    Forms\Components\Select::make('manufacturer_ids')
                        ->label('Brendovi')
                        ->multiple()
                        ->searchable()
                        ->options(fn (): array => Manufacturer::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->required(),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image_path')
                    ->label('Slika')
                    ->disk('public')
                    ->height(56)
                    ->width(84)
                    ->defaultImageUrl(fn (BlogPost $record): ?string => $record->featured_image_url),
                Tables\Columns\TextColumn::make('title')
                    ->label('Naslov')
                    ->searchable()
                    ->sortable()
                    ->description(fn (BlogPost $record): string => '/blog/'.$record->slug)
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'published' => 'Objavljeno',
                        default => 'Nacrt',
                    })
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Objavljeno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('author.name')
                    ->label('Autor')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Izmjena')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Nacrt',
                        'published' => 'Objavljeno',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('viewOnSite')
                    ->label('Web')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (BlogPost $record): string => $record->frontendUrl())
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected static function showcaseLayoutOptions(): array
    {
        return [
            'carousel' => 'Carousel',
            'grid' => 'Mreža kartica',
            'rows' => 'Redovi (detaljno)',
            'spotlight' => 'Istaknuti proizvod',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogPosts::route('/'),
            'create' => Pages\CreateBlogPost::route('/create'),
            'edit' => Pages\EditBlogPost::route('/{record}/edit'),
        ];
    }
}
