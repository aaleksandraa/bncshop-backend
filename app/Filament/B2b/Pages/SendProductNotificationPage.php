<?php

namespace App\Filament\B2b\Pages;

use App\Models\B2bProduct;
use App\Services\B2b\B2bManualProductNotificationService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class SendProductNotificationPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?string $navigationLabel = 'Pošalji obavijest';

    protected static ?string $title = 'Pošalji obavijest o proizvodima';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.b2b.pages.send-product-notification';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        return $user->can('b2b_products.update');
    }

    public function mount(): void
    {
        $service = app(B2bManualProductNotificationService::class);

        $this->form->fill([
            'product_ids' => $service->defaultRecentProductIds(),
            'custom_intro' => (string) config('b2b.product_notification.default_custom_intro'),
        ]);
    }

    public function form(Form $form): Form
    {
        $predefinedIntro = (string) config('b2b.product_notification.predefined_intro');

        return $form
            ->schema([
                Section::make('Proizvodi')
                    ->description('Odaberite jedan ili više aktivnih proizvoda koje želite uključiti u obavijest.')
                    ->schema([
                        Select::make('product_ids')
                            ->label('Proizvodi')
                            ->multiple()
                            ->required()
                            ->rules(['array', 'min:1'])
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => $this->productOptions())
                            ->getSearchResultsUsing(fn (string $search): array => $this->productSearchOptions($search))
                            ->getOptionLabelsUsing(fn (array $values): array => B2bProduct::query()
                                ->whereIn('id', $values)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->helperText('Po defaultu su označeni proizvodi dodani u posljednjih '.config('b2b.product_notification.new_product_days', 30).' dana.'),
                    ]),
                Section::make('Tekst obavijesti')
                    ->description('Dodatni tekst se prikazuje ispred predefinisanog dijela emaila.')
                    ->schema([
                        Textarea::make('custom_intro')
                            ->label('Dodatni uvodni tekst')
                            ->rows(4)
                            ->maxLength(2000)
                            ->helperText('Opcionalno. Možete prilagoditi ili obrisati predefinisani tekst.'),
                        Placeholder::make('predefined_intro_preview')
                            ->label('Predefinisani tekst (uvijek u emailu)')
                            ->content(new HtmlString(
                                '<div class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-200">'
                                .e($predefinedIntro)
                                .'<br><br>• [naziv proizvoda] (šifra)<br>'
                                .'&nbsp;&nbsp;Cijena: [cijena] KM<br>'
                                .'&nbsp;&nbsp;Pogledajte: https://bncshop.ba/b2b/proizvod/[slug]<br>'
                                .'<br>Pregled cijelog kataloga: [link]'
                                .'</div>'
                            )),
                        Placeholder::make('recipient_count')
                            ->label('Primaoci')
                            ->content(fn (): string => (string) app(B2bManualProductNotificationService::class)->activeCustomerCount().' aktivnih B2B kupaca'),
                    ]),
            ])
            ->statePath('data');
    }

    public function sendNotification(): void
    {
        $data = $this->form->getState();

        try {
            $recipientCount = app(B2bManualProductNotificationService::class)->send(
                $data['product_ids'] ?? [],
                $data['custom_intro'] ?? null,
            );
        } catch (\Throwable $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title("Obavijest je poslana u queue za {$recipientCount} kupaca.")
            ->success()
            ->send();
    }

    /**
     * @return array<int, string>
     */
    private function productOptions(): array
    {
        return B2bProduct::query()
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->limit(200)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function productSearchOptions(string $search): array
    {
        $operator = B2bProduct::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return B2bProduct::query()
            ->where('is_active', true)
            ->where(function ($query) use ($search, $operator): void {
                $query->where('name', $operator, "%{$search}%")
                    ->orWhere('sku', $operator, "%{$search}%");
            })
            ->orderBy('name')
            ->limit(50)
            ->pluck('name', 'id')
            ->all();
    }
}
