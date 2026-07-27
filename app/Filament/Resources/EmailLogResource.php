<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\EmailLogResource\Pages;
use App\Models\EmailLog;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EmailLogResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = EmailLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?string $navigationGroup = 'Sistem';

    protected static ?string $modelLabel = 'Email log';

    protected static ?string $pluralModelLabel = 'Email logovi';

    protected static ?int $navigationSort = 3;

    protected static function permissionPrefix(): string
    {
        return 'email_logs';
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
                Infolists\Components\Section::make('Email')
                    ->schema([
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                EmailLog::STATUS_SENT => 'success',
                                EmailLog::STATUS_FAILED => 'danger',
                                EmailLog::STATUS_SKIPPED => 'warning',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                EmailLog::STATUS_SENT => 'Poslano',
                                EmailLog::STATUS_FAILED => 'Neuspješno',
                                EmailLog::STATUS_SKIPPED => 'Preskočeno',
                                default => $state,
                            }),
                        Infolists\Components\TextEntry::make('channel')
                            ->label('Kanal')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                EmailLog::CHANNEL_LARAVEL => 'Laravel Mail',
                                EmailLog::CHANNEL_BREVO => 'Brevo',
                                default => $state,
                            }),
                        Infolists\Components\TextEntry::make('recipient')
                            ->label('Primatelj')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('subject')
                            ->label('Naslov')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('mailable_class')
                            ->label('Mailable')
                            ->formatStateUsing(fn (?string $state, EmailLog $record): string => $record->mailableLabel())
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('template_slug')
                            ->label('Šablon')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('mailer')
                            ->label('Mailer')
                            ->placeholder('—'),
                        Infolists\Components\IconEntry::make('queued')
                            ->label('Queue')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Vrijeme')
                            ->dateTime('d.m.Y H:i:s'),
                        Infolists\Components\TextEntry::make('error_message')
                            ->label('Greška / razlog')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Kontekst')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('context')
                            ->label('Detalji')
                            ->getStateUsing(fn (EmailLog $record): array => self::flattenContext($record->context))
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (EmailLog $record): bool => filled($record->context)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Vrijeme')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        EmailLog::STATUS_SENT => 'success',
                        EmailLog::STATUS_FAILED => 'danger',
                        EmailLog::STATUS_SKIPPED => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        EmailLog::STATUS_SENT => 'Poslano',
                        EmailLog::STATUS_FAILED => 'Neuspješno',
                        EmailLog::STATUS_SKIPPED => 'Preskočeno',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('channel')
                    ->label('Kanal')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('recipient')
                    ->label('Primatelj')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Naslov')
                    ->searchable()
                    ->limit(40)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('mailable_class')
                    ->label('Tip')
                    ->formatStateUsing(fn (?string $state, EmailLog $record): string => $record->mailableLabel())
                    ->toggleable(),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Greška')
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        EmailLog::STATUS_SENT => 'Poslano',
                        EmailLog::STATUS_FAILED => 'Neuspješno',
                        EmailLog::STATUS_SKIPPED => 'Preskočeno',
                    ]),
                Tables\Filters\SelectFilter::make('channel')
                    ->label('Kanal')
                    ->options([
                        EmailLog::CHANNEL_LARAVEL => 'Laravel Mail',
                        EmailLog::CHANNEL_BREVO => 'Brevo',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('Od'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Do'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailLogs::route('/'),
            'view' => Pages\ViewEmailLog::route('/{record}'),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $context
     * @return array<string, string>
     */
    private static function flattenContext(?array $context, string $prefix = ''): array
    {
        if ($context === null || $context === []) {
            return [];
        }

        $flat = [];

        foreach ($context as $key => $value) {
            $label = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flat = array_merge($flat, self::flattenContext($value, $label));

                continue;
            }

            $flat[$label] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                $value === null => '',
                default => (string) $value,
            };
        }

        return $flat;
    }
}
