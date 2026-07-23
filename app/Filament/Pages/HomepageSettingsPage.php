<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Product;
use App\Services\Homepage\HomepageSettings;
use App\Support\CategoryAdminSearch;
use App\Support\ProductAdminSearch;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class HomepageSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Početna stranica';

    protected static ?string $title = 'Postavke početne stranice';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.homepage-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_products') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(HomepageSettings $settings): void
    {
        $weeklyOffer = $settings->weeklyOffer();
        $weeklyOffer['product_ids'] = array_map(
            strval(...),
            (array) ($weeklyOffer['product_ids'] ?? []),
        );

        $categoryChips = $settings->categoryChips();
        $categoryChips['category_ids'] = array_map(
            strval(...),
            (array) ($categoryChips['category_ids'] ?? []),
        );

        $this->form->fill([
            'weekly_offer' => $weeklyOffer,
            'category_chips' => $categoryChips,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Ponuda sedmice')
                    ->description('Odaberite proizvode i način prikaza u hero sekciji.')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Prikaži sekciju')
                            ->default(true)
                            ->live(),
                        TextInput::make('title')
                            ->label('Naslov sekcije')
                            ->required()
                            ->maxLength(120)
                            ->default('Ponuda sedmice'),
                        TextInput::make('subtitle')
                            ->label('Podnaslov (opcionalno)')
                            ->maxLength(255),
                        Select::make('layout')
                            ->label('Način prikaza')
                            ->options(HomepageSettings::WEEKLY_OFFER_LAYOUTS)
                            ->required()
                            ->live()
                            ->default('spotlight_card')
                            ->helperText('Svi odabrani proizvodi i layout prikazuju se u hero sekciji desno od naslova.'),
                        Select::make('product_limit')
                            ->label('Maksimalan broj proizvoda')
                            ->options([
                                1 => '1 proizvod',
                                2 => '2 proizvoda',
                                3 => '3 proizvoda',
                                4 => '4 proizvoda',
                                5 => '5 proizvoda',
                                6 => '6 proizvoda',
                            ])
                            ->required()
                            ->default(1)
                            ->live()
                            ->disabled(fn (Get $get): bool => $get('layout') === 'spotlight_card')
                            ->dehydrated()
                            ->helperText(fn (Get $get): ?string => $get('layout') === 'spotlight_card'
                                ? 'Za hero karticu uvijek se prikazuje 1 proizvod.'
                                : null),
                        Select::make('product_ids')
                            ->label('Proizvodi')
                            ->multiple()
                            ->searchable()
                            ->searchDebounce(300)
                            ->getSearchResultsUsing(fn (string $search): array => ProductAdminSearch::optionsForSearch($search))
                            ->getOptionLabelsUsing(fn (array $values): array => self::productLabels($values))
                            ->helperText('Pretražite po nazivu, brendu, SKU-u, barkodu ili ID-u. Redoslijed odabira određuje red prikaza.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->statePath('weekly_offer'),
                Section::make('Kategorije na početnoj')
                    ->description('Odaberite koje kategorije se prikazuju u sekciji „Šta danas tražite?”.')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Prikaži sekciju')
                            ->default(true),
                        TextInput::make('title')
                            ->label('Naslov sekcije')
                            ->required()
                            ->maxLength(120)
                            ->default('Šta danas tražite?'),
                        TextInput::make('subtitle')
                            ->label('Podnaslov (opcionalno)')
                            ->maxLength(255)
                            ->default('Odaberite kategoriju — jednostavno i brzo.'),
                        Select::make('category_limit')
                            ->label('Maksimalan broj kategorija')
                            ->options([
                                2 => '2 kategorije',
                                3 => '3 kategorije',
                                4 => '4 kategorije',
                                5 => '5 kategorija',
                                6 => '6 kategorija',
                                8 => '8 kategorija',
                                10 => '10 kategorija',
                                12 => '12 kategorija',
                            ])
                            ->required()
                            ->default(6)
                            ->live(),
                        Select::make('category_ids')
                            ->label('Kategorije')
                            ->multiple()
                            ->searchable()
                            ->searchDebounce(300)
                            ->getSearchResultsUsing(fn (string $search): array => CategoryAdminSearch::optionsForSearch($search))
                            ->getOptionLabelsUsing(fn (array $values): array => self::categoryLabels($values))
                            ->helperText('Redoslijed odabira određuje red prikaza. Ako ostavite prazno, koriste se podrazumijevane kategorije (računari, laptopi, monitori, printeri).')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->statePath('category_chips'),
            ])
            ->statePath('data');
    }

    /**
     * @param  array<int|string>  $values
     * @return array<string, string>
     */
    private static function productLabels(array $values): array
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

    /**
     * @param  array<int|string>  $values
     * @return array<string, string>
     */
    private static function categoryLabels(array $values): array
    {
        if ($values === []) {
            return [];
        }

        return Category::query()
            ->whereIn('id', $values)
            ->get(['id', 'name', 'display_name', 'full_slug'])
            ->mapWithKeys(fn (Category $category): array => [
                (string) $category->id => CategoryAdminSearch::formatOptionLabel($category),
            ])
            ->all();
    }

    public function save(HomepageSettings $settings): void
    {
        if (! static::canAccess()) {
            Notification::make()
                ->title('Nemate dozvolu za izmjenu postavki.')
                ->danger()
                ->send();

            return;
        }

        $state = $this->form->getState();
        $weeklyOffer = (array) ($state['weekly_offer'] ?? []);
        $categoryChips = (array) ($state['category_chips'] ?? []);

        $limit = (int) ($weeklyOffer['product_limit'] ?? 1);
        $ids = array_map(
            intval(...),
            array_slice(array_values((array) ($weeklyOffer['product_ids'] ?? [])), 0, $limit),
        );
        $weeklyOffer['product_ids'] = $ids;

        if (($weeklyOffer['layout'] ?? '') === 'spotlight_card') {
            $weeklyOffer['product_limit'] = 1;
            $weeklyOffer['product_ids'] = array_slice($ids, 0, 1);
        }

        $categoryLimit = (int) ($categoryChips['category_limit'] ?? 6);
        $categoryIds = array_map(
            intval(...),
            array_slice(array_values((array) ($categoryChips['category_ids'] ?? [])), 0, $categoryLimit),
        );
        $categoryChips['category_ids'] = $categoryIds;

        $settings->saveWeeklyOffer($weeklyOffer);
        $settings->saveCategoryChips($categoryChips);

        $savedWeeklyOffer = $settings->weeklyOffer();
        $savedWeeklyOffer['product_ids'] = array_map(
            strval(...),
            (array) ($savedWeeklyOffer['product_ids'] ?? []),
        );

        $savedCategoryChips = $settings->categoryChips();
        $savedCategoryChips['category_ids'] = array_map(
            strval(...),
            (array) ($savedCategoryChips['category_ids'] ?? []),
        );

        $this->form->fill([
            'weekly_offer' => $savedWeeklyOffer,
            'category_chips' => $savedCategoryChips,
        ]);

        Notification::make()
            ->title('Postavke početne stranice su sačuvane.')
            ->success()
            ->send();
    }
}
