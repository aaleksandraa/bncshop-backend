<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\CanAccessLoyalty;
use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Services\Loyalty\LoyaltyCardService;
use App\Services\Loyalty\LoyaltyService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use RuntimeException;

class InStoreLoyaltyPage extends Page implements HasForms
{
    use CanAccessLoyalty;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Radnja — BNC bodovi';

    protected static ?string $title = 'Radnja — BNC bodovi';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.in-store-loyalty';

    public ?array $search = [];

    public ?array $sale = [];

    public ?array $redeem = [];

    public ?Customer $selectedCustomer = null;

    /** @var array<int, array<string, mixed>> */
    public array $recentTransactions = [];

    public static function canAccess(): bool
    {
        return static::canOperateInStore();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canOperateInStore();
    }

    public function mount(): void
    {
        $this->searchForm->fill([]);
        $this->saleForm->fill(['store_location' => 'Glavna radnja']);
        $this->redeemForm->fill(['store_location' => 'Glavna radnja']);
    }

    protected function getForms(): array
    {
        return ['searchForm', 'saleForm', 'redeemForm'];
    }

    public function searchForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Pretraga kupca')
                    ->schema([
                        TextInput::make('card_number')
                            ->label('Broj kartice')
                            ->placeholder('BNC-00012345'),
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email(),
                        TextInput::make('phone')
                            ->label('Telefon'),
                    ])
                    ->columns(3),
            ])
            ->statePath('search');
    }

    public function saleForm(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('amount_km')
                    ->label('Iznos nakon popusta (KM)')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->step(0.01),
                TextInput::make('receipt_number')
                    ->label('Broj računa')
                    ->required()
                    ->maxLength(100),
                TextInput::make('store_location')
                    ->label('Lokacija radnje')
                    ->maxLength(100),
                Textarea::make('notes')
                    ->label('Napomena')
                    ->rows(2),
            ])
            ->columns(2)
            ->statePath('sale');
    }

    public function redeemForm(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('reward_id')
                    ->label('Nagrada')
                    ->options(fn (): array => $this->selectedCustomer
                        ? app(LoyaltyService::class)
                            ->getAvailableRewards($this->selectedCustomer)
                            ->mapWithKeys(fn (LoyaltyReward $reward): array => [
                                $reward->id => $reward->name.' ('.$reward->points_required.' b.)',
                            ])
                            ->all()
                        : [])
                    ->required()
                    ->searchable(),
                TextInput::make('receipt_number')
                    ->label('Broj računa')
                    ->required()
                    ->maxLength(100),
                TextInput::make('store_location')
                    ->label('Lokacija radnje')
                    ->maxLength(100),
                Textarea::make('notes')
                    ->label('Napomena')
                    ->rows(2),
            ])
            ->columns(2)
            ->statePath('redeem');
    }

    public function searchCustomer(LoyaltyCardService $cardService, LoyaltyService $loyaltyService): void
    {
        $data = $this->searchForm->getState();

        $customer = $cardService->findCustomerForInStore(
            cardNumber: filled($data['card_number'] ?? null) ? (string) $data['card_number'] : null,
            email: filled($data['email'] ?? null) ? (string) $data['email'] : null,
            phone: filled($data['phone'] ?? null) ? (string) $data['phone'] : null,
        );

        if (! $customer) {
            $this->selectedCustomer = null;
            $this->recentTransactions = [];
            Notification::make()->title('Kupac nije pronađen.')->danger()->send();

            return;
        }

        $this->selectedCustomer = $customer->load(['user', 'loyaltyCards' => fn ($q) => $q->where('status', 'active')]);
        $this->recentTransactions = collect($loyaltyService->getInStoreTransactionHistory($customer))
            ->map(fn ($tx): array => [
                'date' => $tx->created_at?->format('d.m.Y H:i'),
                'type' => $tx->type,
                'points' => $tx->points,
                'description' => $tx->description,
            ])
            ->all();

        Notification::make()->title('Kupac pronađen.')->success()->send();
    }

    public function recordSale(LoyaltyService $loyaltyService): void
    {
        if (! $this->selectedCustomer) {
            Notification::make()->title('Prvo pretražite kupca.')->warning()->send();

            return;
        }

        try {
            $data = $this->saleForm->getState();
            $transaction = $loyaltyService->awardForInStoreSale(
                $this->selectedCustomer,
                (float) $data['amount_km'],
                [
                    'receipt_number' => $data['receipt_number'],
                    'store_location' => $data['store_location'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'staff_user_id' => auth()->id(),
                    'loyalty_card_id' => $this->selectedCustomer->activeLoyaltyCard()?->id,
                ],
            );

            $this->selectedCustomer->refresh();
            $this->searchCustomer(app(LoyaltyCardService::class), $loyaltyService);

            Notification::make()
                ->title("Dodijeljeno {$transaction->points} bodova.")
                ->success()
                ->send();
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function redeemReward(LoyaltyService $loyaltyService): void
    {
        if (! $this->selectedCustomer) {
            Notification::make()->title('Prvo pretražite kupca.')->warning()->send();

            return;
        }

        try {
            $data = $this->redeemForm->getState();
            $reward = LoyaltyReward::query()->findOrFail($data['reward_id']);

            $loyaltyService->redeemInStore(
                $this->selectedCustomer,
                $reward,
                [
                    'receipt_number' => $data['receipt_number'],
                    'store_location' => $data['store_location'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'staff_user_id' => auth()->id(),
                    'loyalty_card_id' => $this->selectedCustomer->activeLoyaltyCard()?->id,
                ],
            );

            $this->selectedCustomer->refresh();
            $this->searchCustomer(app(LoyaltyCardService::class), $loyaltyService);

            Notification::make()->title('Nagrada iskorištena.')->success()->send();
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public function issueCard(LoyaltyCardService $cardService, LoyaltyService $loyaltyService): void
    {
        if (! $this->selectedCustomer) {
            Notification::make()->title('Prvo pretražite kupca.')->warning()->send();

            return;
        }

        if ($this->selectedCustomer->activeLoyaltyCard() !== null) {
            Notification::make()->title('Kupac već ima aktivnu karticu.')->warning()->send();

            return;
        }

        if (! static::canIssueCards()) {
            Notification::make()->title('Nemate dozvolu za izdavanje kartica.')->danger()->send();

            return;
        }

        try {
            $card = $cardService->issueCard($this->selectedCustomer, auth()->user());
            $this->searchCustomer($cardService, $loyaltyService);

            Notification::make()
                ->title('Kartica izdata: '.$card->card_number)
                ->success()
                ->send();
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
