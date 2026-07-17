<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\MenuResource\Pages;
use App\Filament\Resources\MenuResource\RelationManagers;
use App\Models\Menu;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MenuResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = Menu::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $modelLabel = 'Meni';

    protected static ?string $pluralModelLabel = 'Meni';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Navigacija';

    protected static function permissionPrefix(): string
    {
        return 'menus';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Meni')
                    ->description('Za header meni: prvo su linkovi (Akcija, Kategorije…), zatim kategorije s podkategorijama. Redoslijed mijenjate prevlačenjem stavki; uključivanje/isključivanje preko polja Aktivna.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Naziv')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->label('Identifikator')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('header = glavni meni, footer = podnožje'),
                        Forms\Components\Textarea::make('description')
                            ->label('Opis')
                            ->rows(2),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktivan')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Naziv')
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Identifikator')
                    ->badge(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Stavke')
                    ->counts('items'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktivan')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Izmjena')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Uredi meni'),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MenuItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenus::route('/'),
            'edit' => Pages\EditMenu::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
