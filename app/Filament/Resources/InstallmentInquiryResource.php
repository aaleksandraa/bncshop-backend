<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\InstallmentInquiryResource\Pages;
use App\Models\InstallmentInquiry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InstallmentInquiryResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = InstallmentInquiry::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Prodaja';

    protected static ?string $navigationLabel = 'Kupovina na rate';

    protected static ?string $modelLabel = 'Upit za rate';

    protected static ?string $pluralModelLabel = 'Upiti za rate';

    protected static ?int $navigationSort = 3;

    protected static function permissionPrefix(): string
    {
        return 'installment_inquiries';
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

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Status')
                    ->schema([
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => InstallmentInquiry::statusOptions()[$state] ?? $state)
                            ->color(fn (string $state): string => match ($state) {
                                'nova' => 'warning',
                                'kontaktirana' => 'info',
                                'zatvorena' => 'success',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Primljeno')
                            ->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Kupac')
                    ->schema([
                        Infolists\Components\TextEntry::make('first_name')
                            ->label('Ime'),
                        Infolists\Components\TextEntry::make('last_name')
                            ->label('Prezime'),
                        Infolists\Components\TextEntry::make('phone')
                            ->label('Telefon'),
                        Infolists\Components\TextEntry::make('email')
                            ->label('Email'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Proizvod')
                    ->schema([
                        Infolists\Components\TextEntry::make('product_name')
                            ->label('Naziv'),
                        Infolists\Components\TextEntry::make('product_slug')
                            ->label('Slug'),
                        Infolists\Components\TextEntry::make('quantity')
                            ->label('Količina'),
                        Infolists\Components\TextEntry::make('base_price')
                            ->label('Bazna cijena')
                            ->money('BAM'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Plan otplate')
                    ->schema([
                        Infolists\Components\TextEntry::make('installment_type')
                            ->label('Tip')
                            ->formatStateUsing(fn (string $state): string => InstallmentInquiry::typeOptions()[$state] ?? $state),
                        Infolists\Components\TextEntry::make('months')
                            ->label('Broj rata'),
                        Infolists\Components\TextEntry::make('monthly_amount')
                            ->label('Mjesečna rata')
                            ->money('BAM'),
                        Infolists\Components\TextEntry::make('total_amount')
                            ->label('Ukupno')
                            ->money('BAM'),
                        Infolists\Components\TextEntry::make('interest_rate')
                            ->label('Kamata')
                            ->formatStateUsing(fn ($state): string => number_format((float) $state * 100, 2, ',', '.').'%'),
                        Infolists\Components\TextEntry::make('provision_rate')
                            ->label('Provizija')
                            ->formatStateUsing(fn ($state): string => number_format((float) $state * 100, 2, ',', '.').'%'),
                        Infolists\Components\TextEntry::make('calculation_snapshot')
                            ->label('Snapshot kalkulacije')
                            ->formatStateUsing(fn ($state): string => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Tehnički podaci')
                    ->schema([
                        Infolists\Components\TextEntry::make('ip_address')
                            ->label('IP adresa'),
                        Infolists\Components\TextEntry::make('user_agent')
                            ->label('User agent')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Datum')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Kupac')
                    ->formatStateUsing(fn (InstallmentInquiry $record): string => $record->full_name)
                    ->searchable(['first_name', 'last_name', 'email', 'phone']),
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Proizvod')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('installment_type')
                    ->label('Tip')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => InstallmentInquiry::typeOptions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('monthly_amount')
                    ->label('Rata')
                    ->money('BAM')
                    ->sortable(),
                Tables\Columns\TextColumn::make('months')
                    ->label('Mj.')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => InstallmentInquiry::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'nova' => 'warning',
                        'kontaktirana' => 'info',
                        'zatvorena' => 'success',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(InstallmentInquiry::statusOptions()),
                Tables\Filters\SelectFilter::make('installment_type')
                    ->label('Tip')
                    ->options(InstallmentInquiry::typeOptions()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('changeStatus')
                    ->label('Promijeni status')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (): bool => static::userCan('update'))
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Novi status')
                            ->options(InstallmentInquiry::statusOptions())
                            ->required(),
                    ])
                    ->action(function (InstallmentInquiry $record, array $data): void {
                        $record->update(['status' => $data['status']]);

                        Notification::make()
                            ->title('Status ažuriran')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInstallmentInquiries::route('/'),
            'view' => Pages\ViewInstallmentInquiry::route('/{record}'),
        ];
    }
}
