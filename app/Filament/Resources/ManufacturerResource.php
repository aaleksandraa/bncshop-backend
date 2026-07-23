<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Concerns\HasSeoFormFields;
use App\Filament\Resources\ManufacturerResource\Pages;
use App\Models\Manufacturer;
use App\Support\PublicStorageUrl;
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
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Redoslijed')
                            ->helperText('Manji broj = više na listi. Brendovi sa istim brojem sortiraju se po broju proizvoda.')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),
                        Forms\Components\Toggle::make('featured')
                            ->label('Istaknut'),
                        Forms\Components\Toggle::make('system')
                            ->label('Sistemski')
                            ->disabled(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Logotip')
                    ->description('Upload ima prioritet nad eksternim URL-om. Sync iz izvora ažurira samo eksterni URL.')
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Upload logotipa')
                            ->helperText('PNG, JPG, WebP ili SVG. Maks. 2 MB. Prikazuje se na /brendovi i stranici brenda.')
                            ->image()
                            ->disk('public')
                            ->directory('manufacturers/logos')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                            ->imagePreviewHeight('120')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('logo_url')
                            ->label('Eksterni URL logotipa')
                            ->helperText('URL iz synca ili ručno unesen. Koristi se ako nema uploadanog fajla.')
                            ->url()
                            ->maxLength(2048)
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('logo_preview')
                            ->label('Trenutni prikaz')
                            ->content(function (?Manufacturer $record) {
                                if (! $record) {
                                    return '—';
                                }

                                $url = PublicStorageUrl::absoluteFromResolved($record->logoUrl());

                                if (! $url) {
                                    return 'Nema logotipa';
                                }

                                return new \Illuminate\Support\HtmlString(
                                    '<img src="'.e($url).'" alt="'.e($record->name).'" style="max-height:80px;max-width:220px;object-fit:contain;background:#fff;padding:8px;border-radius:8px;border:1px solid #e5e5e5;" />'
                                );
                            }),
                    ]),
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
                Tables\Columns\ImageColumn::make('logo_preview')
                    ->label('Logo')
                    ->getStateUsing(fn (Manufacturer $record): ?string => PublicStorageUrl::absoluteFromResolved($record->logoUrl()))
                    ->height(40)
                    ->square(false),
                Tables\Columns\TextColumn::make('name')
                    ->label('Naziv')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('featured')
                    ->label('Istaknut')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Redoslijed')
                    ->sortable(),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Proizvodi')
                    ->counts('products')
                    ->sortable(),
                Tables\Columns\IconColumn::make('has_local_logo')
                    ->label('Upload')
                    ->getStateUsing(fn (Manufacturer $record): bool => filled($record->logo_path))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus-circle'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Izmjena')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('featured')
                    ->label('Istaknut'),
                Tables\Filters\TernaryFilter::make('has_logo')
                    ->label('Ima logo')
                    ->queries(
                        true: fn ($query) => $query->where(function ($builder): void {
                            $builder
                                ->whereNotNull('logo_path')
                                ->where('logo_path', '!=', '')
                                ->orWhere(function ($inner): void {
                                    $inner
                                        ->whereNotNull('logo_url')
                                        ->where('logo_url', '!=', '');
                                });
                        }),
                        false: fn ($query) => $query
                            ->where(fn ($builder) => $builder->whereNull('logo_path')->orWhere('logo_path', ''))
                            ->where(fn ($builder) => $builder->whereNull('logo_url')->orWhere('logo_url', '')),
                    ),
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
