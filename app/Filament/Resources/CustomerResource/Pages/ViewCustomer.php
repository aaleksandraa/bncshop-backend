<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Concerns\CanAccessLoyalty;
use App\Filament\Resources\CustomerResource;
use App\Models\EmailTemplate;
use App\Models\MarketingContact;
use App\Services\Loyalty\LoyaltyCardService;
use App\Services\Marketing\BrevoService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

class ViewCustomer extends ViewRecord
{
    use CanAccessLoyalty;

    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncBrevo')
                ->label('Sinhronizuj u Brevo')
                ->icon('heroicon-o-cloud-arrow-up')
                ->visible(fn (): bool => auth()->user()?->can('customers.update') || auth()->user()?->hasRole(['Super Admin', 'Admin']))
                ->action(function (MarketingContact $record, BrevoService $brevo): void {
                    try {
                        $brevo->syncContact($record);
                        Notification::make()->title('Kontakt sinhronizovan u Brevo.')->success()->send();
                    } catch (RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('sendEmail')
                ->label('Pošalji e-mail / ponudu')
                ->icon('heroicon-o-envelope')
                ->visible(fn (): bool => auth()->user()?->can('customers.update') || auth()->user()?->hasRole(['Super Admin', 'Admin']))
                ->form([
                    Forms\Components\Select::make('email_template_id')
                        ->label('E-mail šablon')
                        ->options(fn (): array => EmailTemplate::query()
                            ->where('is_active', true)
                            ->orderBy('slug')
                            ->pluck('subject', 'id')
                            ->all())
                        ->required()
                        ->searchable()
                        ->helperText('Šabloni se uređuju u Marketing → E-mail šabloni.'),
                ])
                ->action(function (MarketingContact $record, array $data, BrevoService $brevo): void {
                    $template = EmailTemplate::query()->find($data['email_template_id']);

                    if ($template === null) {
                        Notification::make()->title('Šablon nije pronađen.')->danger()->send();

                        return;
                    }

                    try {
                        if (! $record->isSyncedWithBrevo()) {
                            $brevo->syncContact($record);
                        }

                        $brevo->sendTemplate($record, $template);
                        Notification::make()->title('E-mail poslan preko Brevo.')->success()->send();
                    } catch (RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('issueCard')
                ->label('Izdaj karticu')
                ->icon('heroicon-o-credit-card')
                ->visible(fn (MarketingContact $record): bool => static::canIssueCards()
                    && $record->customer !== null
                    && $record->customer->activeLoyaltyCard() === null)
                ->requiresConfirmation()
                ->action(function (MarketingContact $record, LoyaltyCardService $service): void {
                    if ($record->customer === null) {
                        return;
                    }

                    try {
                        $card = $service->issueCard($record->customer, auth()->user());
                        Notification::make()->title('Kartica izdata: '.$card->card_number)->success()->send();
                    } catch (RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('replaceCard')
                ->label('Zamijeni karticu')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (MarketingContact $record): bool => static::canIssueCards()
                    && $record->customer?->activeLoyaltyCard() !== null)
                ->requiresConfirmation()
                ->action(function (MarketingContact $record, LoyaltyCardService $service): void {
                    $card = $record->customer?->activeLoyaltyCard();
                    if ($card === null) {
                        return;
                    }

                    $newCard = $service->replaceCard($card, auth()->user());
                    Notification::make()->title('Nova kartica: '.$newCard->card_number)->success()->send();
                }),
            Actions\Action::make('blockCard')
                ->label('Blokiraj karticu')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (MarketingContact $record): bool => static::canBlockCards()
                    && $record->customer?->activeLoyaltyCard() !== null)
                ->form([
                    Forms\Components\Textarea::make('reason')->label('Razlog')->required(),
                ])
                ->action(function (MarketingContact $record, array $data, LoyaltyCardService $service): void {
                    $card = $record->customer?->activeLoyaltyCard();
                    if ($card === null) {
                        return;
                    }

                    $service->blockCard($card, $data['reason']);
                    Notification::make()->title('Kartica blokirana.')->success()->send();
                }),
            Actions\Action::make('printCard')
                ->label('Štampaj karticu')
                ->icon('heroicon-o-printer')
                ->url(fn (MarketingContact $record): ?string => ($card = $record->customer?->activeLoyaltyCard())
                    ? route('filament.admin.loyalty-cards.print', $card)
                    : null)
                ->openUrlInNewTab()
                ->visible(fn (MarketingContact $record): bool => $record->customer?->activeLoyaltyCard() !== null),
            Actions\EditAction::make()
                ->visible(fn (MarketingContact $record): bool => $record->isRegistered()),
        ];
    }
}
