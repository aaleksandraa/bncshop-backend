<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Services\Homepage\HomepageSettings;
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
        $data = $settings->weeklyOffer();
        $data['product_ids'] = array_map(
            strval(...),
            (array) ($data['product_ids'] ?? []),
        );

        $this->form->fill($data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Ponuda sedmice')
                    ->description('Odaberite proizvode i način prikaza na početnoj stranici.')
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
                            ->getSearchResultsUsing(fn (string $search): array => self::searchProducts($search))
                            ->getOptionLabelsUsing(fn (array $values): array => self::productLabels($values))
                            ->helperText('Pretražite po nazivu, SKU-u, barkodu ili ID-u. Redoslijed odabira određuje red prikaza.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    /**
     * @return array<string, string>
     */
    private static function searchProducts(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        return Product::query()
            ->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $query->orWhere('id', (int) $search)
                        ->orWhere('external_product_id', $search);
                }
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'sku'])
            ->mapWithKeys(fn (Product $product): array => [
                (string) $product->id => self::formatProductOption($product),
            ])
            ->all();
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
                (string) $product->id => self::formatProductOption($product),
            ])
            ->all();
    }

    private static function formatProductOption(Product $product): string
    {
        $label = $product->name;

        if ($product->sku) {
            $label .= " ({$product->sku})";
        }

        return $label;
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
        $limit = (int) ($state['product_limit'] ?? 1);
        $ids = array_map(
            intval(...),
            array_slice(array_values((array) ($state['product_ids'] ?? [])), 0, $limit),
        );
        $state['product_ids'] = $ids;

        if (($state['layout'] ?? '') === 'spotlight_card') {
            $state['product_limit'] = 1;
            $state['product_ids'] = array_slice($ids, 0, 1);
        }

        $settings->saveWeeklyOffer($state);

        $saved = $settings->weeklyOffer();
        $saved['product_ids'] = array_map(
            strval(...),
            (array) ($saved['product_ids'] ?? []),
        );

        $this->form->fill($saved);

        Notification::make()
            ->title('Postavke početne stranice su sačuvane.')
            ->success()
            ->send();
    }
}
