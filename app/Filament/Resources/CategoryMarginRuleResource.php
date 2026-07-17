<?php

namespace App\Filament\Resources;

use App\Filament\Forms\MarginCategoryScopeFields;
use App\Filament\Resources\CategoryMarginRuleResource\Pages;
use App\Models\CategoryMarginRule;
use App\Services\Pricing\ProductPriceRecalculator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryMarginRuleResource extends Resource
{
    protected static ?string $model = CategoryMarginRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?string $navigationLabel = 'Marže po kategorijama (A1)';

    protected static ?string $modelLabel = 'Marža po kategoriji';

    protected static ?string $pluralModelLabel = 'Marže po kategorijama (A1 nova roba)';

    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return static::userCanMargin('view');
    }

    public static function canCreate(): bool
    {
        return static::userCanMargin('update');
    }

    public static function canEdit($record): bool
    {
        return static::userCanMargin('update');
    }

    public static function canDelete($record): bool
    {
        return static::userCanMargin('update');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pravilo marže')
                    ->description('Primjenjuje se samo na A1 Technoshop proizvode označene kao nova roba (is_new). Ne utiče na eLine.')
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->label('Kategorija')
                            ->relationship('category', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->publicName())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('margin_percentage')
                            ->label('Marža (%)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(500)
                            ->suffix('%'),
                        ...MarginCategoryScopeFields::schema(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktivno')
                            ->default(true),
                        Forms\Components\Textarea::make('notes')
                            ->label('Napomena')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategorija')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('margin_percentage')
                    ->label('Marža')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subcategory_scope')
                    ->label('Obuhvat')
                    ->formatStateUsing(fn (CategoryMarginRule $record): string => $record->scopeSummaryLabel())
                    ->badge()
                    ->color(fn (CategoryMarginRule $record): string => match ($record->subcategory_scope) {
                        'all_descendants' => 'info',
                        'selected' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktivno')
                    ->boolean(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Napomena')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->defaultSort('category.name')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(fn (CategoryMarginRule $record) => static::recalculate($record)),
                Tables\Actions\DeleteAction::make()
                    ->after(fn (CategoryMarginRule $record) => static::recalculate($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategoryMarginRules::route('/'),
            'create' => Pages\CreateCategoryMarginRule::route('/create'),
            'edit' => Pages\EditCategoryMarginRule::route('/{record}/edit'),
        ];
    }

    private static function recalculate(CategoryMarginRule $record): void
    {
        $count = app(ProductPriceRecalculator::class)->forCategoryMarginRule($record);

        Notification::make()
            ->title('Cijene preračunate')
            ->body("Ažurirano {$count} A1 proizvoda (nova roba).")
            ->success()
            ->send();
    }

    private static function userCanMargin(string $action): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        return match ($action) {
            'view' => $user->can('margin_rules.view') || $user->can('view_margin') || $user->can('manage_products'),
            'update' => $user->can('margin_rules.update') || $user->can('manage_products'),
            default => false,
        };
    }
}
