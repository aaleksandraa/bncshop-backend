<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Concerns\OrderStatusLabels;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\Commerce\OrderService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Js;

class OrderResource extends Resource
{
    use AuthorizesWithPermissions;
    use OrderStatusLabels;

    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Prodaja';

    protected static ?string $modelLabel = 'Narudžba';

    protected static ?string $pluralModelLabel = 'Narudžbe';

    protected static ?int $navigationSort = 1;

    protected static function permissionPrefix(): string
    {
        return 'orders';
    }

    public static function canPrint(): bool
    {
        return static::userCan('view');
    }

    public static function canManageStatus(): bool
    {
        return static::userCan('update');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('items')
            ->with(['coupon', 'loyaltyReward']);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function changeStatusForm(Order $record): array
    {
        $service = app(OrderService::class);
        $allowed = $service->allowedTransitions(static::orderCurrentStatus($record));
        $options = array_intersect_key(static::orderStatusOptions(), array_flip($allowed));

        return [
            Forms\Components\Select::make('status')
                ->label('Novi status')
                ->options($options)
                ->required(),
            Forms\Components\Textarea::make('note')
                ->label('Napomena')
                ->rows(2),
            Forms\Components\Toggle::make('restore_stock_on_return')
                ->label('Vrati zalihu (vraćeno)')
                ->visible(fn (Get $get): bool => $get('status') === 'vraćeno'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function customerEmailStatuses(): array
    {
        return ['poslano', 'isporučeno', 'otkazano'];
    }

    public static function statusChangeSuccessBody(string $newStatus): ?string
    {
        if (! in_array($newStatus, static::customerEmailStatuses(), true)) {
            return null;
        }

        return match ($newStatus) {
            'poslano' => 'Kupcu je poslan e-mail da je narudžba poslana.',
            'isporučeno' => 'Kupcu je poslan e-mail da je narudžba isporučena.',
            'otkazano' => 'Kupcu je poslan e-mail o otkazivanju narudžbe.',
            default => null,
        };
    }

    public static function applyStatusChange(Order $record, array $data): bool
    {
        try {
            app(OrderService::class)->transition(
                $record,
                $data['status'],
                auth()->user(),
                $data['note'] ?? null,
                (bool) ($data['restore_stock_on_return'] ?? false),
            );

            $notification = Notification::make()
                ->title('Status ažuriran')
                ->success();

            if ($body = static::statusChangeSuccessBody($data['status'])) {
                $notification->body($body);
            }

            $notification->send();

            return true;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Greška')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return false;
        }
    }

    public static function applyMarkShipped(Order $record, ?string $note = null): bool
    {
        try {
            app(OrderService::class)->markAsShippedForSeller(
                $record,
                auth()->user(),
                $note,
            );

            Notification::make()
                ->title('Narudžba označena kao poslana')
                ->body(static::statusChangeSuccessBody('poslano'))
                ->success()
                ->send();

            return true;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Greška')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return false;
        }
    }

    public static function applyCancel(Order $record, ?string $note = null): bool
    {
        try {
            app(OrderService::class)->cancelForSeller(
                $record,
                auth()->user(),
                $note,
            );

            Notification::make()
                ->title('Narudžba otkazana')
                ->body(static::statusChangeSuccessBody('otkazano'))
                ->success()
                ->send();

            return true;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Greška')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return false;
        }
    }

    public static function canTransitionTo(Order $record, string $status): bool
    {
        return in_array(
            $status,
            app(OrderService::class)->allowedTransitions(static::orderCurrentStatus($record)),
            true,
        );
    }

    protected static function orderCurrentStatus(Order $record): string
    {
        $status = $record->status;

        if ($status === null || $status === '') {
            return 'nova';
        }

        return $status;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return static::userCan('delete');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Narudžba')
                    ->schema([
                        Infolists\Components\TextEntry::make('order_number')
                            ->label('Broj narudžbe')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => static::orderStatusOptions()[$state] ?? $state)
                            ->color(fn (string $state): string => static::orderStatusColor($state)),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Kreirano')
                            ->dateTime('d.m.Y H:i'),
                        Infolists\Components\TextEntry::make('payment_method')
                            ->label('Način plaćanja')
                            ->formatStateUsing(fn (?string $state): string => static::paymentMethodLabel($state)),
                        Infolists\Components\TextEntry::make('shipping_method')
                            ->label('Dostava')
                            ->formatStateUsing(fn (?string $state): string => static::shippingMethodLabel($state)),
                        Infolists\Components\TextEntry::make('tracking_token')
                            ->label('Tracking link')
                            ->formatStateUsing(function (?string $state): string {
                                if (! $state) {
                                    return '—';
                                }

                                return rtrim((string) config('bnc.frontend_url', config('app.url')), '/').'/narudzba/'.$state;
                            })
                            ->url(fn (Order $record): ?string => $record->tracking_token
                                ? rtrim((string) config('bnc.frontend_url', config('app.url')), '/').'/narudzba/'.$record->tracking_token
                                : null)
                            ->openUrlInNewTab()
                            ->copyable(),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Kupac')
                    ->schema([
                        Infolists\Components\TextEntry::make('first_name')
                            ->label('Ime'),
                        Infolists\Components\TextEntry::make('last_name')
                            ->label('Prezime'),
                        Infolists\Components\TextEntry::make('email')
                            ->label('E-mail')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('phone')
                            ->label('Telefon')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('address')
                            ->label('Adresa')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('city')
                            ->label('Grad'),
                        Infolists\Components\TextEntry::make('postal_code')
                            ->label('Poštanski broj'),
                        Infolists\Components\TextEntry::make('company_name')
                            ->label('Firma')
                            ->visible(fn (Order $record): bool => filled($record->company_name)),
                        Infolists\Components\TextEntry::make('jib')
                            ->label('JIB')
                            ->visible(fn (Order $record): bool => filled($record->jib)),
                        Infolists\Components\TextEntry::make('pdv_number')
                            ->label('PDV broj')
                            ->visible(fn (Order $record): bool => filled($record->pdv_number)),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Napomene kupca')
                            ->columnSpanFull()
                            ->visible(fn (Order $record): bool => filled($record->notes)),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Stavke narudžbe')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('product_name')
                                    ->label('Proizvod'),
                                Infolists\Components\TextEntry::make('sku')
                                    ->label('Šifra')
                                    ->formatStateUsing(fn ($state, \App\Models\OrderItem $record): string => $record->displayCode() ?: '—'),
                                Infolists\Components\TextEntry::make('brand_name')
                                    ->label('Brend'),
                                Infolists\Components\TextEntry::make('quantity')
                                    ->label('Kol.'),
                                Infolists\Components\TextEntry::make('final_price')
                                    ->label('Cijena')
                                    ->formatStateUsing(fn ($state): string => static::formatMoney($state)),
                                Infolists\Components\TextEntry::make('line_total')
                                    ->label('Ukupno')
                                    ->formatStateUsing(fn ($state): string => static::formatMoney($state)),
                            ])
                            ->columns(6)
                            ->columnSpanFull(),
                    ]),
                Infolists\Components\Section::make('Iznosi')
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal')
                            ->label('Međuzbir')
                            ->formatStateUsing(fn ($state): string => static::formatMoney($state)),
                        Infolists\Components\TextEntry::make('discount_total')
                            ->label('Popust')
                            ->formatStateUsing(fn ($state): string => static::formatMoney($state)),
                        Infolists\Components\TextEntry::make('shipping_fee')
                            ->label('Dostava')
                            ->formatStateUsing(fn ($state): string => static::formatMoney($state)),
                        Infolists\Components\TextEntry::make('loyalty_discount_amount')
                            ->label('Loyalty popust')
                            ->formatStateUsing(fn ($state): string => static::formatMoney($state))
                            ->visible(fn (Order $record): bool => (float) $record->loyalty_discount_amount > 0),
                        Infolists\Components\TextEntry::make('coupon.code')
                            ->label('Kupon')
                            ->visible(fn (Order $record): bool => $record->coupon_id !== null),
                        Infolists\Components\TextEntry::make('loyaltyReward.name')
                            ->label('Loyalty nagrada')
                            ->visible(fn (Order $record): bool => $record->loyalty_reward_id !== null),
                        Infolists\Components\TextEntry::make('points_redeemed')
                            ->label('Iskorišteni bodovi')
                            ->visible(fn (Order $record): bool => (int) $record->points_redeemed > 0),
                        Infolists\Components\TextEntry::make('points_earned')
                            ->label('Zarađeni bodovi')
                            ->visible(fn (Order $record): bool => (int) $record->points_earned > 0),
                        Infolists\Components\TextEntry::make('total')
                            ->label('Ukupno')
                            ->formatStateUsing(fn ($state): string => static::formatMoney($state))
                            ->weight('bold')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Historija statusa')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('statusHistory')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Datum')
                                    ->dateTime('d.m.Y H:i'),
                                Infolists\Components\TextEntry::make('old_status')
                                    ->label('Stari status')
                                    ->formatStateUsing(fn (?string $state): string => $state ? (static::orderStatusOptions()[$state] ?? $state) : '—'),
                                Infolists\Components\TextEntry::make('new_status')
                                    ->label('Novi status')
                                    ->formatStateUsing(fn (string $state): string => static::orderStatusOptions()[$state] ?? $state)
                                    ->badge()
                                    ->color(fn (string $state): string => static::orderStatusColor($state)),
                                Infolists\Components\TextEntry::make('changedByUser.name')
                                    ->label('Promijenio')
                                    ->default('Sistem'),
                                Infolists\Components\TextEntry::make('note')
                                    ->label('Napomena')
                                    ->columnSpanFull(),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Order $record): bool => $record->statusHistory->isNotEmpty())
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Broj')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::orderStatusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => static::orderStatusColor($state)),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Kupac')
                    ->formatStateUsing(fn (Order $record): string => trim("{$record->first_name} {$record->last_name}"))
                    ->searchable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Plaćanje')
                    ->formatStateUsing(fn (?string $state): string => static::paymentMethodLabel($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('shipping_method')
                    ->label('Dostava')
                    ->formatStateUsing(fn (?string $state): string => static::shippingMethodLabel($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total')
                    ->label('Ukupno')
                    ->money('BAM')
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Stavke')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Datum')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(static::orderStatusOptions())
                    ->multiple(),
                SelectFilter::make('payment_method')
                    ->label('Plaćanje')
                    ->options(static::paymentMethodOptions()),
                SelectFilter::make('shipping_method')
                    ->label('Dostava')
                    ->options(static::shippingMethodOptions()),
                Filter::make('created_between')
                    ->label('Datum narudžbe')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Od'),
                        Forms\Components\DatePicker::make('until')->label('Do'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'Od '.(\Illuminate\Support\Carbon::parse($data['from'])->format('d.m.Y'));
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Do '.(\Illuminate\Support\Carbon::parse($data['until'])->format('d.m.Y'));
                        }

                        return $indicators;
                    }),
                Filter::make('total_between')
                    ->label('Iznos')
                    ->form([
                        Forms\Components\TextInput::make('min_total')
                            ->label('Min. ukupno')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\TextInput::make('max_total')
                            ->label('Max. ukupno')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['min_total'] ?? null),
                                fn (Builder $q): Builder => $q->where('total', '>=', (float) $data['min_total']),
                            )
                            ->when(
                                filled($data['max_total'] ?? null),
                                fn (Builder $q): Builder => $q->where('total', '<=', (float) $data['max_total']),
                            );
                    }),
                Filter::make('has_coupon')
                    ->label('Sa kuponom')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('coupon_id')),
            ])
            ->filtersFormColumns(2)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\Action::make('print')
                        ->label('Štampaj')
                        ->icon('heroicon-o-printer')
                        ->url(fn (Order $record): string => route('filament.admin.orders.print', $record))
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('printPacking')
                        ->label('Pakovanje')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->url(fn (Order $record): string => route('filament.admin.orders.packing-print', $record))
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('exportPdf')
                        ->label('PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->url(fn (Order $record): string => route('filament.admin.orders.export-pdf', $record)),
                    Tables\Actions\Action::make('exportExcel')
                        ->label('Excel')
                        ->icon('heroicon-o-table-cells')
                        ->url(fn (Order $record): string => route('filament.admin.orders.export-excel', $record)),
                    Tables\Actions\Action::make('changeStatus')
                        ->label('Status')
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn (): bool => static::canManageStatus())
                        ->form(fn (Order $record): array => static::changeStatusForm($record))
                        ->action(fn (Order $record, array $data): mixed => static::applyStatusChange($record, $data)),
                ]),
                Tables\Actions\DeleteAction::make()
                    ->modalHeading('Obriši narudžbu')
                    ->modalDescription('Narudžba i sve povezane stavke bit će trajno obrisane. Koristite za test narudžbe — ova radnja se ne može poništiti.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->modalHeading('Obriši odabrane narudžbe')
                        ->modalDescription('Odabrane narudžbe i sve povezane stavke bit će trajno obrisane. Ova radnja se ne može poništiti.'),
                    Tables\Actions\BulkAction::make('printSelected')
                        ->label('Štampaj odabrane')
                        ->icon('heroicon-o-printer')
                        ->action(fn (Collection $records, Tables\Actions\BulkAction $action): mixed => static::openBatchUrl(
                            $action,
                            'filament.admin.orders.print-batch',
                            $records,
                        )),
                    Tables\Actions\BulkAction::make('printPackingSelected')
                        ->label('Pakovanje (odabrane)')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->action(fn (Collection $records, Tables\Actions\BulkAction $action): mixed => static::openBatchUrl(
                            $action,
                            'filament.admin.orders.packing-print-batch',
                            $records,
                        )),
                    Tables\Actions\BulkAction::make('exportExcelSelected')
                        ->label('Excel (odabrane)')
                        ->icon('heroicon-o-table-cells')
                        ->action(fn (Collection $records, Tables\Actions\BulkAction $action): mixed => static::downloadBatchUrl(
                            $action,
                            'filament.admin.orders.export-excel-batch',
                            $records,
                        )),
                    Tables\Actions\BulkAction::make('exportPdfSelected')
                        ->label('PDF (odabrane)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(fn (Collection $records, Tables\Actions\BulkAction $action): mixed => static::downloadBatchUrl(
                            $action,
                            'filament.admin.orders.export-pdf-batch',
                            $records,
                        )),
                    Tables\Actions\BulkAction::make('exportPackingPdfSelected')
                        ->label('PDF pakovanje (odabrane)')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->action(fn (Collection $records, Tables\Actions\BulkAction $action): mixed => static::downloadBatchUrl(
                            $action,
                            'filament.admin.orders.export-packing-pdf-batch',
                            $records,
                        )),
                ]),
            ]);
    }

    public static function openBatchUrl(Tables\Actions\BulkAction $action, string $routeName, Collection $records): void
    {
        if (! static::canPrint()) {
            return;
        }

        $url = route($routeName, ['ids' => $records->pluck('id')->implode(',')]);
        $action->getLivewire()->js('window.open('.Js::from($url).', "_blank")');
    }

    public static function downloadBatchUrl(Tables\Actions\BulkAction $action, string $routeName, Collection $records): void
    {
        if (! static::canPrint()) {
            return;
        }

        $url = route($routeName, ['ids' => $records->pluck('id')->implode(',')]);
        $action->getLivewire()->js('window.location.href = '.Js::from($url));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
