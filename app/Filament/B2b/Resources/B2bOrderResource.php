<?php

namespace App\Filament\B2b\Resources;

use App\Filament\B2b\Resources\B2bOrderResource\Pages;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Models\B2bOrder;
use App\Support\B2bOrderStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class B2bOrderResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = B2bOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Narudžbe';

    protected static ?string $modelLabel = 'B2B narudžba';

    protected static ?string $pluralModelLabel = 'B2B narudžbe';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'order_number';

    protected static function permissionPrefix(): string
    {
        return 'b2b_orders';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return static::userCan('delete');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Narudžba')->schema([
                Forms\Components\TextInput::make('order_number')->disabled(),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(B2bOrderStatus::labels())
                    ->required(),
                Forms\Components\TextInput::make('total')->label('Ukupno (KM)')->disabled(),
                Forms\Components\TextInput::make('shipping_fee')->label('Dostava (KM)')->disabled(),
                Forms\Components\Textarea::make('notes')->label('Napomena kupca')->disabled()->rows(2),
            ])->columns(2),
            Forms\Components\Section::make('Kupac')->schema([
                Forms\Components\TextInput::make('company_name')->disabled(),
                Forms\Components\TextInput::make('contact_name')->disabled(),
                Forms\Components\TextInput::make('contact_email')->disabled(),
                Forms\Components\TextInput::make('contact_phone')->disabled(),
                Forms\Components\Textarea::make('shipping_address')->label('Adresa dostave')->disabled()->rows(2),
            ])->columns(2),
            Forms\Components\Repeater::make('items')
                ->relationship()
                ->label('Stavke')
                ->schema([
                    Forms\Components\TextInput::make('product_name')->disabled(),
                    Forms\Components\TextInput::make('quantity')->disabled(),
                    Forms\Components\TextInput::make('unit_final_price')->label('Cijena')->disabled(),
                    Forms\Components\TextInput::make('line_total')->label('Ukupno')->disabled(),
                ])
                ->columns(4)
                ->columnSpanFull()
                ->addable(false)
                ->deletable(false)
                ->reorderable(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->label('Broj')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('company_name')->label('Firma')->searchable(),
                Tables\Columns\TextColumn::make('contact_name')->label('Kontakt'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => B2bOrderStatus::label($state)),
                Tables\Columns\TextColumn::make('total')->money('BAM')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('downloadInvoice')
                    ->label('Faktura PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (B2bOrder $record): string => route('filament.b2b-admin.b2b-orders.invoice', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make()
                    ->modalHeading('Obriši B2B narudžbu')
                    ->modalDescription('Narudžba i sve povezane stavke bit će trajno obrisane. Koristite za test narudžbe — ova radnja se ne može poništiti.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->modalHeading('Obriši odabrane B2B narudžbe')
                        ->modalDescription('Odabrane narudžbe i sve povezane stavke bit će trajno obrisane. Ova radnja se ne može poništiti.'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListB2bOrders::route('/'),
            'edit' => Pages\EditB2bOrder::route('/{record}/edit'),
        ];
    }
}
