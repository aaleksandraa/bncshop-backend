<?php

namespace App\Filament\Resources\LoyaltyCardResource\Pages;

use App\Filament\Concerns\CanAccessLoyalty;
use App\Filament\Resources\LoyaltyCardResource;
use App\Models\Customer;
use App\Services\Loyalty\LoyaltyCardService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use RuntimeException;

class ListLoyaltyCards extends ListRecords
{
    use CanAccessLoyalty;

    protected static string $resource = LoyaltyCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('issueCard')
                ->label('Izdaj karticu')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => static::canIssueCards())
                ->form(static::issueCardFormSchema())
                ->action(function (array $data, LoyaltyCardService $service): void {
                    $customer = Customer::query()
                        ->with('user')
                        ->findOrFail($data['customer_id']);

                    try {
                        $card = $service->issueCard(
                            $customer,
                            auth()->user(),
                            filled($data['notes'] ?? null) ? (string) $data['notes'] : null,
                        );

                        Notification::make()
                            ->title('Kartica izdata: '.$card->card_number)
                            ->success()
                            ->send();
                    } catch (RuntimeException $e) {
                        Notification::make()
                            ->title($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function issueCardFormSchema(): array
    {
        return [
            Forms\Components\Select::make('customer_id')
                ->label('Kupac')
                ->placeholder('Pretražite po imenu ili e-mailu')
                ->searchable()
                ->getSearchResultsUsing(function (string $search): array {
                    return Customer::query()
                        ->with('user')
                        ->eligibleForLoyaltyCard()
                        ->where(function ($query) use ($search): void {
                            $query->whereHas('user', function ($userQuery) use ($search): void {
                                $userQuery
                                    ->where('name', 'like', '%'.$search.'%')
                                    ->orWhere('email', 'like', '%'.$search.'%');
                            })->orWhere('phone', 'like', '%'.$search.'%');
                        })
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Customer $customer): array => [
                            $customer->id => static::formatCustomerOptionLabel($customer),
                        ])
                        ->all();
                })
                ->getOptionLabelUsing(function ($value): ?string {
                    $customer = Customer::query()->with('user')->find($value);

                    return $customer ? static::formatCustomerOptionLabel($customer) : null;
                })
                ->required(),
            Forms\Components\Textarea::make('notes')
                ->label('Napomena')
                ->rows(2),
        ];
    }

    private static function formatCustomerOptionLabel(Customer $customer): string
    {
        $name = $customer->user?->name ?? 'Kupac';
        $email = $customer->user?->email ?? 'bez e-maila';

        return "{$name} — {$email}";
    }
}
