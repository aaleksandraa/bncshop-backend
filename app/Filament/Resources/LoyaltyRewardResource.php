<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Concerns\CanAccessLoyalty;
use App\Filament\Resources\LoyaltyRewardResource\Pages;
use App\Models\LoyaltyReward;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LoyaltyRewardResource extends Resource
{
    use AuthorizesWithPermissions;
    use CanAccessLoyalty;

    protected static ?string $model = LoyaltyReward::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $modelLabel = 'Nagrada';

    protected static ?string $pluralModelLabel = 'Nagrade lojalnosti';

    protected static ?int $navigationSort = 2;

    protected static function permissionPrefix(): string
    {
        return 'loyalty_rewards';
    }

    public static function canViewAny(): bool
    {
        return static::canAccessLoyalty() || static::userCan('view');
    }

    public static function canCreate(): bool
    {
        return static::canAccessLoyalty(requireUpdate: true) || static::userCan('create');
    }

    public static function canEdit($record): bool
    {
        return static::canAccessLoyalty(requireUpdate: true) || static::userCan('update');
    }

    public static function canDelete($record): bool
    {
        return static::canAccessLoyalty(requireUpdate: true) || static::userCan('delete');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Osnovno')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Naziv')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->label('Opis')
                            ->rows(3),
                        Forms\Components\Select::make('type')
                            ->label('Tip nagrade')
                            ->options([
                                'percentage' => 'Postotni popust',
                                'fixed' => 'Fiksni popust (KM)',
                                'free_product' => 'Besplatan proizvod',
                            ])
                            ->required()
                            ->live(),
                        Forms\Components\TextInput::make('points_required')
                            ->label('Potrebno bodova')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                        Forms\Components\TextInput::make('reward_value')
                            ->label('Vrijednost (% ili KM)')
                            ->numeric()
                            ->visible(fn (Get $get): bool => in_array($get('type'), ['percentage', 'fixed'], true)),
                        Forms\Components\Select::make('product_id')
                            ->label('Proizvod')
                            ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(fn (Get $get): bool => $get('type') === 'free_product')
                            ->visible(fn (Get $get): bool => $get('type') === 'free_product'),
                        Forms\Components\Select::make('apply_to')
                            ->label('Primjena popusta')
                            ->options([
                                'cart' => 'Cijela korpa',
                                'eligible_items_only' => 'Samo eligible stavke',
                            ])
                            ->default('cart')
                            ->visible(fn (Get $get): bool => in_array($get('type'), ['percentage', 'fixed'], true)),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktivna')
                            ->default(true),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Redoslijed')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Limiti')
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Počinje')
                            ->native(false),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('Završava')
                            ->native(false),
                        Forms\Components\TextInput::make('max_uses_per_customer')
                            ->label('Max. korištenja po kupcu')
                            ->numeric(),
                        Forms\Components\TextInput::make('total_max_uses')
                            ->label('Ukupno max. korištenja')
                            ->numeric(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Naziv')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tip')
                    ->badge(),
                Tables\Columns\TextColumn::make('points_required')
                    ->label('Bodova')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reward_value')
                    ->label('Vrijednost'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktivna')
                    ->boolean(),
                Tables\Columns\TextColumn::make('times_redeemed')
                    ->label('Iskorišteno')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoyaltyRewards::route('/'),
            'create' => Pages\CreateLoyaltyReward::route('/create'),
            'edit' => Pages\EditLoyaltyReward::route('/{record}/edit'),
        ];
    }
}
