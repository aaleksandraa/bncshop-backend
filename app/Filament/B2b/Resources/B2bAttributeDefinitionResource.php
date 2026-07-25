<?php

namespace App\Filament\B2b\Resources;

use App\Filament\B2b\Resources\B2bAttributeDefinitionResource\Pages;
use App\Filament\B2b\Resources\B2bAttributeDefinitionResource\RelationManagers;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Models\B2bAttributeDefinition;
use App\Services\B2b\B2bProductAttributeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class B2bAttributeDefinitionResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = B2bAttributeDefinition::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?string $modelLabel = 'B2B atribut';

    protected static ?string $pluralModelLabel = 'B2B atributi';

    protected static ?int $navigationSort = 3;

    protected static function permissionPrefix(): string
    {
        return 'b2b_categories';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Naziv')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Forms\Set $set, ?string $state, ?string $operation): void {
                    if ($operation === 'edit') {
                        return;
                    }

                    $set('slug', B2bProductAttributeService::slugFromName($state ?? ''));
                }),
            Forms\Components\TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('Koristi se u filterima i API-ju.'),
            Forms\Components\Select::make('input_type')
                ->label('Tip polja')
                ->options([
                    B2bAttributeDefinition::INPUT_SELECT => 'Odabir (select)',
                    B2bAttributeDefinition::INPUT_MULTISELECT => 'Višestruki odabir',
                    B2bAttributeDefinition::INPUT_TEXT => 'Tekst',
                ])
                ->required()
                ->default(B2bAttributeDefinition::INPUT_SELECT),
            Forms\Components\Toggle::make('is_filterable')
                ->label('Koristi u filterima')
                ->default(true),
            Forms\Components\Toggle::make('is_active')
                ->label('Aktivan')
                ->default(true),
            Forms\Components\TextInput::make('sort_order')
                ->label('Redoslijed')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Naziv')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\TextColumn::make('input_type')->label('Tip')->badge(),
                Tables\Columns\TextColumn::make('options_count')->label('Opcije')->counts('options'),
                Tables\Columns\IconColumn::make('is_filterable')->label('Filter')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->label('Aktivan')->boolean(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListB2bAttributeDefinitions::route('/'),
            'create' => Pages\CreateB2bAttributeDefinition::route('/create'),
            'edit' => Pages\EditB2bAttributeDefinition::route('/{record}/edit'),
        ];
    }
}
