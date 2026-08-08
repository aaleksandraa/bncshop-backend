<?php

namespace App\Filament\B2b\Resources;

use App\Filament\B2b\Resources\B2bCustomerResource\Pages;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Models\B2bCustomer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class B2bCustomerResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = B2bCustomer::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Kupci';

    protected static ?string $modelLabel = 'B2B kupac';

    protected static ?string $pluralModelLabel = 'B2B kupci';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'company_name';

    protected static function permissionPrefix(): string
    {
        return 'b2b_customers';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'creator'])
            ->withCount('orders');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Kontakt')
                ->description('Podaci za prijavu i komunikaciju sa kupcem.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Ime i prezime')
                        ->required()
                        ->maxLength(255)
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->dehydrated(false)
                        ->rules(fn (?B2bCustomer $record): array => [
                            Rule::unique('users', 'email')->ignore($record?->user_id),
                        ]),
                    Forms\Components\TextInput::make('phone')
                        ->label('Telefon')
                        ->tel()
                        ->required()
                        ->maxLength(50),
                    Forms\Components\TextInput::make('password')
                        ->label('Lozinka')
                        ->password()
                        ->revealable()
                        ->dehydrated(false)
                        ->minLength(8)
                        ->same('password_confirmation')
                        ->helperText('Opciono. Ako ostavite prazno, korisnik će dobiti email za postavljanje lozinke prije prve prijave.'),
                    Forms\Components\TextInput::make('password_confirmation')
                        ->label('Potvrda lozinke')
                        ->password()
                        ->revealable()
                        ->dehydrated(false),
                ])
                ->columns(2),
            Forms\Components\Section::make('Firma')->schema([
                Forms\Components\TextInput::make('company_name')
                    ->label('Naziv firme')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('company_address')
                    ->label('Adresa')
                    ->required()
                    ->rows(2)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('jib')
                    ->label('JIB')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('pdv_number')
                    ->label('PDV broj')
                    ->maxLength(50),
            ])->columns(2),
            Forms\Components\Section::make('Popust i status')->schema([
                Forms\Components\TextInput::make('discount_percent')
                    ->label('Popust (%)')
                    ->helperText('Ostavite prazno za globalni default popust.')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktivan')
                    ->default(true),
            ])->columns(2),
            Forms\Components\Section::make('Pregled')
                ->schema([
                    Forms\Components\Placeholder::make('creator_name')
                        ->label('Kreirao')
                        ->content(fn (?B2bCustomer $record): string => $record?->creator?->name ?? '—'),
                    Forms\Components\Placeholder::make('orders_count_display')
                        ->label('Broj narudžbi')
                        ->content(fn (?B2bCustomer $record): string => (string) ($record?->orders_count ?? 0)),
                    Forms\Components\Placeholder::make('registered_at')
                        ->label('Datum registracije')
                        ->content(fn (?B2bCustomer $record): string => $record?->created_at?->format('d.m.Y H:i') ?? '—'),
                    Forms\Components\Placeholder::make('effective_discount')
                        ->label('Efektivni popust')
                        ->content(fn (?B2bCustomer $record): string => $record
                            ? number_format($record->effectiveDiscountPercent(), 2, ',', '.').' %'
                            : '—'),
                ])
                ->columns(2)
                ->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Firma')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Kontakt')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon')
                    ->searchable(),
                Tables\Columns\TextColumn::make('jib')
                    ->label('JIB')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pdv_number')
                    ->label('PDV')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('company_address')
                    ->label('Adresa')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('discount_percent')
                    ->label('Popust %')
                    ->placeholder('Default'),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Narudžbe')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktivan')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrovan')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('company_name')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListB2bCustomers::route('/'),
            'create' => Pages\CreateB2bCustomer::route('/create'),
            'edit' => Pages\EditB2bCustomer::route('/{record}/edit'),
        ];
    }
}
