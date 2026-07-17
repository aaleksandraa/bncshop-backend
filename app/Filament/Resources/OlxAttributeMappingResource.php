<?php

namespace App\Filament\Resources;

use App\Filament\Pages\OlxSyncSettingsPage;
use App\Filament\Resources\OlxAttributeMappingResource\Pages;
use App\Models\AttributeDefinition;
use App\Models\OlxAttributeMapping;
use App\Models\OlxCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OlxAttributeMappingResource extends Resource
{
    protected static ?string $model = OlxAttributeMapping::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'OLX';

    protected static ?string $navigationLabel = 'Mapiranje atributa';

    protected static ?string $modelLabel = 'OLX mapiranje atributa';

    protected static ?string $pluralModelLabel = 'OLX mapiranje atributa';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return OlxSyncSettingsPage::canAccess();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('olx_category_id')
                ->label('OLX kategorija')
                ->options(fn (): array => OlxCategory::query()->orderBy('path')->pluck('path', 'id')->all())
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('olx_attribute_id')
                ->label('OLX attribute ID')
                ->numeric()
                ->required(),
            Forms\Components\Select::make('attribute_definition_id')
                ->label('BNC atribut')
                ->options(fn (): array => AttributeDefinition::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable(),
            Forms\Components\TagsInput::make('bnc_attribute_aliases')
                ->label('BNC aliasi'),
            Forms\Components\TextInput::make('parser_pattern')
                ->label('Parser regex'),
            Forms\Components\TextInput::make('default_value')
                ->label('Default vrijednost'),
            Forms\Components\KeyValue::make('value_mappings')
                ->label('Mapiranje vrijednosti'),
            Forms\Components\Toggle::make('is_required_for_publish')
                ->label('Obavezno za publish'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('olx_category_id')->label('OLX cat'),
                Tables\Columns\TextColumn::make('olx_attribute_id')->label('OLX attr'),
                Tables\Columns\TextColumn::make('attributeDefinition.name')->label('BNC attr'),
                Tables\Columns\TextColumn::make('bnc_attribute_aliases')->label('Alias')->limit(30),
                Tables\Columns\IconColumn::make('is_required_for_publish')->label('Req.')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->headerActions([Tables\Actions\CreateAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageOlxAttributeMappings::route('/'),
        ];
    }
}
