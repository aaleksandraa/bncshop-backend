<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Catalog\ProductImageManagerService;
use App\Support\PublicStorageUrl;
use App\Filament\Support\OptimizedMediaUpload;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Slike';

    public function form(Form $form): Form
    {
        if ($this->supportsFileUpload()) {
            $product = $this->getOwnerRecord();

            return $form
                ->schema([
                    OptimizedMediaUpload::configure(
                        Forms\Components\FileUpload::make('upload')
                            ->label('Slika')
                            ->helperText('PNG, JPG ili WebP. Možete kasnije promijeniti ili obrisati sliku.')
                            ->image()
                            ->maxSize(5120)
                            ->imagePreviewHeight('160')
                            ->required(fn (string $operation): bool => $operation === 'create'),
                        $this->uploadDirectory($product),
                    )->columnSpanFull(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Redoslijed')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('is_primary')
                        ->label('Glavna slika'),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'active' => 'Aktivna',
                            'pending' => 'Na čekanju',
                            'failed' => 'Greška',
                        ])
                        ->default('active'),
                ]);
        }

        return $form
            ->schema([
                Forms\Components\TextInput::make('image_url')
                    ->label('URL slike')
                    ->url()
                    ->required()
                    ->maxLength(2048),
                Forms\Components\TextInput::make('public_url')
                    ->label('Javni URL')
                    ->url()
                    ->maxLength(2048),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Redoslijed')
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_primary')
                    ->label('Glavna slika'),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktivna',
                        'pending' => 'Na čekanju',
                        'failed' => 'Greška',
                    ])
                    ->default('active'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('image_url')
            ->columns([
                Tables\Columns\ImageColumn::make('preview')
                    ->label('Slika')
                    ->getStateUsing(fn (ProductImage $record): ?string => PublicStorageUrl::absoluteFromResolved($record->resolvedUrl())),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Redoslijed')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Glavna')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data): ProductImage {
                        if ($this->supportsFileUpload()) {
                            return app(ProductImageManagerService::class)->createImageFromStoredKey(
                                $this->getOwnerRecord(),
                                (string) $data['upload'],
                                app(\App\Services\Media\MediaStorage::class)->usesR2() ? 'r2' : 'public',
                                [],
                                [
                                    'is_primary' => (bool) ($data['is_primary'] ?? false),
                                    'sort_order' => (int) ($data['sort_order'] ?? 0),
                                    'status' => (string) ($data['status'] ?? 'active'),
                                ],
                            );
                        }

                        return $this->getRelationship()->create($data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(function (ProductImage $record, array $data): ProductImage {
                        if ($this->supportsFileUpload()) {
                            $product = $this->getOwnerRecord();
                            $service = app(ProductImageManagerService::class);

                            if (! empty($data['upload']) && $data['upload'] !== $record->local_path) {
                                $record = $service->replaceFromStoredKey(
                                    $product,
                                    $record,
                                    (string) $data['upload'],
                                );
                            }

                            $record->update([
                                'sort_order' => (int) ($data['sort_order'] ?? $record->sort_order),
                                'status' => (string) ($data['status'] ?? $record->status),
                            ]);

                            if (! empty($data['is_primary'])) {
                                $service->setPrimary($product, $record);
                            }

                            return $record->fresh();
                        }

                        $record->update($data);

                        if (! empty($data['is_primary'])) {
                            app(ProductImageManagerService::class)->setPrimary($this->getOwnerRecord(), $record);
                        }

                        return $record->fresh();
                    })
                    ->mutateRecordDataUsing(function (array $data, ProductImage $record): array {
                        if ($this->supportsFileUpload() && filled($record->local_path)) {
                            $data['upload'] = $record->local_path;
                        }

                        return $data;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->using(function (ProductImage $record): void {
                        app(ProductImageManagerService::class)->delete($this->getOwnerRecord(), $record);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->using(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $service = app(ProductImageManagerService::class);
                            $product = $this->getOwnerRecord();

                            foreach ($records as $record) {
                                if ($record instanceof ProductImage) {
                                    $service->delete($product, $record);
                                }
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('Nema slika')
            ->emptyStateDescription($this->supportsFileUpload()
                ? 'Dodajte sliku seta/proizvoda. Bez slike na shopu će pisati „Nema slike“.'
                : 'Dodajte URL slike ili sačekajte sync iz izvora.');
    }

    private function supportsFileUpload(): bool
    {
        $product = $this->getOwnerRecord();

        return $product instanceof Product
            && ($product->is_set || $product->import_source === 'manual');
    }

    private function uploadDirectory(Product $product): string
    {
        return 'products/'.Str::slug((string) $product->external_product_id, '_');
    }
}
