<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Concerns\HasSeoFormFields;
use App\Filament\Resources\ManufacturerResource\Pages;
use App\Models\Manufacturer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ManufacturerResource extends Resource
{
    use AuthorizesWithPermissions;
    use HasSeoFormFields;

    protected static ?string $model = Manufacturer::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?string $modelLabel = 'Brend';

    protected static ?string $pluralModelLabel = 'Brendovi';

    protected static ?int $navigationSort = 3;

    protected static function permissionPrefix(): string
    {
        return 'manufacturers';
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
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Textarea::make('description')
                            ->label('Opis')
                            ->rows(4),
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo brenda')
                            ->helperText('Upload logotipa (PNG, JPG, WebP). Prikazuje se na stranici proizvoda. Maks. 2 MB.')
                            ->image()
                            ->disk('public')
                            ->directory('manufacturers/logos')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                            ->imagePreviewHeight('80')
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('featured')
                            ->label('Istaknut'),
                        Forms\Components\Toggle::make('system')
                            ->label('Sistemski')
                            ->disabled(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('SEO')
                    ->relationship('seoOverride')
                    ->schema(static::seoFormFields())
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->height(40)
                    ->square(false),
                Tables\Columns\TextColumn::make('name')
                    ->label('Naziv')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\IconColumn::make('featured')
                    ->label('Istaknut')
                    ->boolean(),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Proizvodi')
                    ->counts('products'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Izmjena')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('featured')
                    ->label('Istaknut'),
            ])
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManufacturers::route('/'),
            'create' => Pages\CreateManufacturer::route('/create'),
            'edit' => Pages\EditManufacturer::route('/{record}/edit'),
        ];
    }
}
