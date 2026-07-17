<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\EmailTemplate;
use App\Models\MarketingContact;
use App\Models\Order;
use App\Services\Marketing\BrevoService;
use App\Services\Marketing\MarketingContactSyncService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CustomerResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = MarketingContact::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Prodaja';

    protected static ?string $modelLabel = 'Kupac';

    protected static ?string $pluralModelLabel = 'Kupci';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function permissionPrefix(): string
    {
        return 'customers';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Marketing')
                    ->schema([
                        Forms\Components\Toggle::make('marketing_opt_in')
                            ->label('Saglasnost za marketing'),
                    ]),
                Forms\Components\Section::make('Podaci registrovanog kupca')
                    ->relationship('customer')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Telefon')
                            ->tel(),
                        Forms\Components\TextInput::make('company_name')
                            ->label('Firma'),
                        Forms\Components\TextInput::make('jib')
                            ->label('JIB'),
                    ])
                    ->visible(fn (?MarketingContact $record): bool => $record?->isRegistered() ?? false),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Kontakt')
                    ->schema([
                        Infolists\Components\TextEntry::make('type')
                            ->label('Tip')
                            ->formatStateUsing(fn (MarketingContact $record): string => $record->typeLabel())
                            ->badge()
                            ->color(fn (MarketingContact $record): string => $record->isRegistered() ? 'success' : 'gray'),
                        Infolists\Components\TextEntry::make('name')
                            ->label('Ime'),
                        Infolists\Components\TextEntry::make('email')
                            ->label('E-mail')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('phone')
                            ->label('Telefon'),
                        Infolists\Components\TextEntry::make('company_name')
                            ->label('Firma'),
                        Infolists\Components\IconEntry::make('marketing_opt_in')
                            ->label('Marketing saglasnost')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('registered_at')
                            ->label('Registracija')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Narudžbe')
                    ->schema([
                        Infolists\Components\TextEntry::make('orders_count')
                            ->label('Broj narudžbi'),
                        Infolists\Components\TextEntry::make('orders_total')
                            ->label('Ukupna vrijednost')
                            ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' KM'),
                        Infolists\Components\TextEntry::make('last_order_at')
                            ->label('Posljednja narudžba')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Brevo')
                    ->schema([
                        Infolists\Components\IconEntry::make('brevo_synced_at')
                            ->label('Sinhronizovan')
                            ->boolean()
                            ->getStateUsing(fn (MarketingContact $record): bool => $record->isSyncedWithBrevo()),
                        Infolists\Components\TextEntry::make('brevo_synced_at')
                            ->label('Zadnja sinhronizacija')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('Još nije'),
                        Infolists\Components\TextEntry::make('brevo_contact_id')
                            ->label('Brevo ID')
                            ->placeholder('—'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Loyalty')
                    ->schema([
                        Infolists\Components\TextEntry::make('customer.loyalty_points_balance')
                            ->label('BNC bodovi')
                            ->formatStateUsing(fn ($state): string => number_format((int) $state, 0, ',', '.').' bodova'),
                        Infolists\Components\TextEntry::make('loyalty_card')
                            ->label('Kartica')
                            ->state(fn (MarketingContact $record): string => $record->customer?->activeLoyaltyCard()?->card_number ?? '—'),
                    ])
                    ->columns(2)
                    ->visible(fn (MarketingContact $record): bool => $record->isRegistered()),
                Infolists\Components\Section::make('Zadnje narudžbe')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('recent_orders')
                            ->label('')
                            ->state(function (MarketingContact $record): array {
                                $query = Order::query()->latest('created_at')->limit(10);

                                if ($record->customer_id) {
                                    $query->where(function (Builder $builder) use ($record): void {
                                        $builder->where('customer_id', $record->customer_id)
                                            ->orWhere('email', $record->email);
                                    });
                                } else {
                                    $query->where('email', $record->email)
                                        ->whereNull('customer_id');
                                }

                                return $query->get()->map(fn (Order $order): array => [
                                    'number' => $order->order_number,
                                    'status' => $order->status,
                                    'total' => number_format((float) $order->total, 2, ',', '.').' KM',
                                    'created_at' => $order->created_at?->format('d.m.Y H:i'),
                                ])->all();
                            })
                            ->schema([
                                Infolists\Components\TextEntry::make('number')->label('Narudžba'),
                                Infolists\Components\TextEntry::make('status')->label('Status')->badge(),
                                Infolists\Components\TextEntry::make('total')->label('Iznos'),
                                Infolists\Components\TextEntry::make('created_at')->label('Datum'),
                            ])
                            ->columns(4),
                    ])
                    ->visible(fn (MarketingContact $record): bool => $record->orders_count > 0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Tip')
                    ->formatStateUsing(fn (MarketingContact $record): string => $record->typeLabel())
                    ->badge()
                    ->color(fn (MarketingContact $record): string => match ($record->type) {
                        MarketingContact::TYPE_REGISTERED => 'success',
                        MarketingContact::TYPE_GUEST => 'gray',
                        default => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Ime')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Firma')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Narudžbe')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('orders_total')
                    ->label('Promet')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' KM')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_order_at')
                    ->label('Zadnja narudžba')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('brevo_synced_at')
                    ->label('Brevo')
                    ->boolean()
                    ->getStateUsing(fn (MarketingContact $record): bool => $record->isSyncedWithBrevo())
                    ->tooltip(fn (MarketingContact $record): string => $record->brevo_synced_at
                        ? 'Sinhronizovan '.$record->brevo_synced_at->format('d.m.Y H:i')
                        : 'Nije u Brevo'),
                Tables\Columns\IconColumn::make('marketing_opt_in')
                    ->label('Marketing')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('registered_at')
                    ->label('Registracija')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_order_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Tip kupca')
                    ->options([
                        MarketingContact::TYPE_REGISTERED => 'Registrovan',
                        MarketingContact::TYPE_GUEST => 'Gost',
                    ]),
                TernaryFilter::make('brevo_synced')
                    ->label('Brevo sinhronizacija')
                    ->trueLabel('Sinhronizovan')
                    ->falseLabel('Nije sinhronizovan')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('brevo_synced_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('brevo_synced_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('marketing_opt_in')
                    ->label('Marketing saglasnost'),
                Filter::make('has_orders')
                    ->label('Ima narudžbe')
                    ->query(fn (Builder $query): Builder => $query->where('orders_count', '>', 0)),
                Filter::make('registered_between')
                    ->label('Registracija')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Od'),
                        Forms\Components\DatePicker::make('until')->label('Do'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('registered_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('registered_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (MarketingContact $record): bool => $record->isRegistered()),
                Tables\Actions\Action::make('syncBrevo')
                    ->label('Brevo')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->visible(fn (): bool => static::userCan('update'))
                    ->action(function (MarketingContact $record, BrevoService $brevo): void {
                        try {
                            $brevo->syncContact($record);
                            Notification::make()->title('Kontakt sinhronizovan u Brevo.')->success()->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('sendEmail')
                    ->label('E-mail')
                    ->icon('heroicon-o-envelope')
                    ->visible(fn (): bool => static::userCan('update'))
                    ->form([
                        Forms\Components\Select::make('email_template_id')
                            ->label('Šablon')
                            ->options(fn (): array => EmailTemplate::query()
                                ->where('is_active', true)
                                ->orderBy('slug')
                                ->pluck('subject', 'id')
                                ->all())
                            ->required()
                            ->searchable(),
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
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('syncBrevoBulk')
                    ->label('Sinhronizuj u Brevo')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->visible(fn (): bool => static::userCan('update'))
                    ->requiresConfirmation()
                    ->action(function ($records, BrevoService $brevo): void {
                        $synced = 0;

                        foreach ($records as $record) {
                            try {
                                $brevo->syncContact($record);
                                $synced++;
                            } catch (RuntimeException) {
                                // Skip failed rows in bulk.
                            }
                        }

                        Notification::make()
                            ->title("Sinhronizovano {$synced} kontakata.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['customer.user', 'customer.loyaltyCards']);
    }
}
