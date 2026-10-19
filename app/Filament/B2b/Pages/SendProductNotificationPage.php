<?php

namespace App\Filament\B2b\Pages;

use App\Models\B2bProduct;
use App\Services\B2b\B2bManualProductNotificationService;
use Carbon\Carbon;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
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
                    ->description('Prečice zamjenjuju trenutni odabir. Brisanje skida proizvode samo iz ove obavijesti, ne iz kataloga.')
                    ->schema([
                        Actions::make([
                            Action::make('selectAddedToday')
                                ->label('Dodani danas')
                                ->color('gray')
                                ->action(fn () => $this->selectAddedToday()),
                            Action::make('selectAddedYesterday')
                                ->label('Dodani jučer')
                                ->color('gray')
                                ->action(fn () => $this->selectAddedYesterday()),
                            Action::make('selectAddedThisWeek')
                                ->label('Ove sedmice')
                                ->color('gray')
                                ->action(fn () => $this->selectAddedThisWeek()),
                            Action::make('selectAddedLast7Days')
                                ->label('Posljednjih 7 dana')
                                ->color('gray')
                                ->action(fn () => $this->selectAddedInLastDays(7)),
                            Action::make('selectAddedLast30Days')
                                ->label('Posljednjih 30 dana')
                                ->color('gray')
                                ->action(fn () => $this->selectAddedInLastDays(30)),
                            Action::make('selectAddedThisMonth')
                                ->label('Ovaj mjesec')
                                ->color('gray')
                                ->action(fn () => $this->selectAddedThisMonth()),
                            Action::make('clearSelectedProducts')
                                ->label('Obriši sve označene')
                                ->color('danger')
                                ->requiresConfirmation()
                                ->modalHeading('Ukloniti sve označene proizvode?')
                                ->modalDescription('Proizvodi ostaju u katalogu. Uklanja se samo odabir za ovu obavijest.')
                                ->modalSubmitActionLabel('Obriši označene')
                                ->action(fn () => $this->clearSelectedProducts()),
                        ]),
                        Select::make('product_ids')
                            ->label('Proizvodi')
                            ->multiple()
                            ->live()
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
                            ->helperText('Po defaultu su označeni proizvodi dodani u posljednjih '.config('b2b.product_notification.new_product_days', 30).' dana. Pojedinačan proizvod skidate klikom na ×.'),
                        Placeholder::make('selected_product_count')
                            ->label('Trenutno u obavijesti')
                            ->content(function (Get $get): string {
                                $count = count($get('product_ids') ?? []);

                                return $count === 1 ? '1 proizvod' : $count.' proizvoda';
                            }),
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
                                .'&nbsp;&nbsp;B2B cijena: [cijena] KM<br>'
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

    public function selectAddedToday(): void
    {
        $this->replaceSelection(now()->startOfDay(), null, 'dodani danas');
    }

    public function selectAddedYesterday(): void
    {
        $this->replaceSelection(
            now()->subDay()->startOfDay(),
            now()->startOfDay(),
            'dodani jučer',
        );
    }

    public function selectAddedThisWeek(): void
    {
        $this->replaceSelection(now()->startOfWeek(Carbon::MONDAY), null, 'dodani ove sedmice');
    }

    public function selectAddedThisMonth(): void
    {
        $this->replaceSelection(now()->startOfMonth(), null, 'dodani ovaj mjesec');
    }

    public function selectAddedInLastDays(int $days): void
    {
        if (! in_array($days, [7, 30], true)) {
            return;
        }

        $this->replaceSelection(now()->subDays($days), null, "dodani u posljednjih {$days} dana");
    }

    public function clearSelectedProducts(): void
    {
        $this->applyProductSelection([]);

        Notification::make()
            ->title('Označeni proizvodi su uklonjeni iz obavijesti.')
            ->body('Katalog nije mijenjan.')
            ->success()
            ->send();
    }

    private function replaceSelection(
        \DateTimeInterface $from,
        ?\DateTimeInterface $until,
        string $label,
    ): void {
        $result = app(B2bManualProductNotificationService::class)
            ->activeProductIdsCreatedBetween($from, $until);

        $this->applyProductSelection($result['ids']);

        if ($result['total'] === 0) {
            Notification::make()
                ->title('Nema aktivnih proizvoda ('.$label.').')
                ->warning()
                ->send();

            return;
        }

        $message = 'Označeno '.$result['returned'].' proizvoda ('.$label.').';

        if ($result['truncated']) {
            $message .= ' Ukupno ih je '.$result['total'].'; u odabir staju prvih '.$result['returned'].'.';
        }

        Notification::make()
            ->title($message)
            ->success()
            ->send();
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function applyProductSelection(array $ids): void
    {
        $this->form->fill([
            'product_ids' => array_values(array_map(static fn ($id): int => (int) $id, $ids)),
            'custom_intro' => $this->data['custom_intro'] ?? null,
        ]);
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
