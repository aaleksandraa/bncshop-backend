<?php

namespace App\Filament\B2b\Resources;

use App\Filament\B2b\Resources\B2bAccessRequestResource\Pages;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Models\B2bAccessRequest;
use App\Services\B2b\B2bAccessMailer;
use App\Services\B2b\B2bCustomerProvisioner;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class B2bAccessRequestResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = B2bAccessRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Kupci';

    protected static ?string $modelLabel = 'Zahtjev za pristup';

    protected static ?string $pluralModelLabel = 'Zahtjevi za pristup';

    protected static ?int $navigationSort = 1;

    protected static function permissionPrefix(): string
    {
        return 'b2b_access_requests';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Kontakt')->schema([
                Infolists\Components\TextEntry::make('first_name')->label('Ime'),
                Infolists\Components\TextEntry::make('last_name')->label('Prezime'),
                Infolists\Components\TextEntry::make('email'),
                Infolists\Components\TextEntry::make('phone')->label('Telefon'),
            ])->columns(2),
            Infolists\Components\Section::make('Firma')->schema([
                Infolists\Components\TextEntry::make('company_name')->label('Naziv firme'),
                Infolists\Components\TextEntry::make('company_address')->label('Adresa'),
                Infolists\Components\TextEntry::make('jib')->label('JIB'),
                Infolists\Components\TextEntry::make('pdv_number')->label('PDV broj'),
            ])->columns(2),
            Infolists\Components\TextEntry::make('status')->badge(),
            Infolists\Components\TextEntry::make('created_at')->dateTime('d.m.Y H:i'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company_name')->label('Firma')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('first_name')->label('Ime'),
                Tables\Columns\TextColumn::make('last_name')->label('Prezime'),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Odobri')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (B2bAccessRequest $record): bool => $record->isPending())
                    ->action(function (B2bAccessRequest $record): void {
                        try {
                            app(B2bCustomerProvisioner::class)->approveAccessRequest($record, auth()->user());
                            Notification::make()->title('Zahtjev odobren. Email poslan korisniku.')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Odbij')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (B2bAccessRequest $record): bool => $record->isPending())
                    ->action(function (B2bAccessRequest $record): void {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);

                        app(B2bAccessMailer::class)->sendAccessRejected($record);

                        Notification::make()->title('Zahtjev odbijen. Email poslan korisniku.')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListB2bAccessRequests::route('/'),
            'view' => Pages\ViewB2bAccessRequest::route('/{record}'),
        ];
    }
}
