<?php

namespace App\Filament\Resources\MenuResource\RelationManagers;

use App\Models\Category;
use App\Models\CmsPage;
use App\Models\MenuItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MenuItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Stavke menija';

    protected static ?string $modelLabel = 'Stavka';

    protected static ?string $pluralModelLabel = 'Stavke';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Tip stavke')
                    ->options([
                        MenuItem::TYPE_CATEGORY => 'Kategorija',
                        MenuItem::TYPE_PAGE => 'Stranica',
                        MenuItem::TYPE_CUSTOM_LINK => 'Prilagođeni link',
                    ])
                    ->required()
                    ->live()
                    ->default(MenuItem::TYPE_CATEGORY),
                Forms\Components\TextInput::make('label')
                    ->label('Naziv u meniju')
                    ->maxLength(255)
                    ->helperText('Opcionalno — koristi se naziv kategorije/stranice ako je prazno'),
                Forms\Components\Select::make('parent_id')
                    ->label('Roditelj (podstavka)')
                    ->options(fn (): array => $this->parentOptions())
                    ->searchable()
                    ->nullable()
                    ->helperText('Ostavite prazno za stavku najvišeg nivoa'),
                Forms\Components\Select::make('category_id')
                    ->label('Kategorija')
                    ->options(fn (): array => Category::query()->active()->orderBy('path')->pluck('name', 'id')->all())
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('type') === MenuItem::TYPE_CATEGORY)
                    ->required(fn (Get $get): bool => $get('type') === MenuItem::TYPE_CATEGORY),
                Forms\Components\Select::make('cms_page_id')
                    ->label('Stranica')
                    ->options(fn (): array => CmsPage::query()->orderBy('title')->pluck('title', 'id')->all())
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('type') === MenuItem::TYPE_PAGE)
                    ->required(fn (Get $get): bool => $get('type') === MenuItem::TYPE_PAGE),
                Forms\Components\TextInput::make('url')
                    ->label('URL')
                    ->maxLength(2048)
                    ->visible(fn (Get $get): bool => $get('type') === MenuItem::TYPE_CUSTOM_LINK)
                    ->required(fn (Get $get): bool => $get('type') === MenuItem::TYPE_CUSTOM_LINK)
                    ->helperText('Relativna (/pretraga) ili puna adresa (https://…)'),
                Forms\Components\Toggle::make('open_in_new_tab')
                    ->label('Otvori u novom tabu'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aktivna')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Naziv')
                    ->formatStateUsing(fn (MenuItem $record): string => $record->resolvedLabel())
                    ->description(fn (MenuItem $record): ?string => $this->itemDescription($record)),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tip')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        MenuItem::TYPE_CATEGORY => 'Kategorija',
                        MenuItem::TYPE_PAGE => 'Stranica',
                        MenuItem::TYPE_CUSTOM_LINK => 'Link',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('parent_id')
                    ->label('Roditelj')
                    ->formatStateUsing(fn ($state, MenuItem $record): string => $record->parent?->resolvedLabel() ?? '—'),
                Tables\Columns\TextColumn::make('resolvedUrl')
                    ->label('URL')
                    ->state(fn (MenuItem $record): ?string => $record->resolvedUrl()),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktivna')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Dodaj stavku')
                    ->mutateFormDataUsing(function (array $data): array {
                        $max = MenuItem::query()
                            ->where('menu_id', $this->getOwnerRecord()->id)
                            ->max('sort_order');

                        $data['sort_order'] = ((int) $max) + 1;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('addSubcategories')
                    ->label('Dodaj podkategorije')
                    ->icon('heroicon-o-folder-plus')
                    ->visible(fn (MenuItem $record): bool => $record->type === MenuItem::TYPE_CATEGORY && $record->category_id !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Dodaj podkategorije u meni')
                    ->modalDescription('Direktne podkategorije odabrane kategorije bit će dodane kao podstavke.')
                    ->action(function (MenuItem $record): void {
                        $this->importSubcategories($record);
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<int|string, string>
     */
    private function parentOptions(?int $excludeId = null): array
    {
        return MenuItem::query()
            ->where('menu_id', $this->getOwnerRecord()->id)
            ->when($excludeId, fn (Builder $query) => $query->where('id', '!=', $excludeId))
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(function (MenuItem $item): array {
                $depth = $this->depth($item);
                $prefix = str_repeat('— ', $depth);

                return [$item->id => $prefix.$item->resolvedLabel()];
            })
            ->all();
    }

    private function depth(MenuItem $item): int
    {
        $depth = 0;
        $current = $item;

        while ($current->parent_id) {
            $depth++;
            $current = MenuItem::query()->find($current->parent_id);
            if (! $current) {
                break;
            }
        }

        return $depth;
    }

    private function itemDescription(MenuItem $record): ?string
    {
        return match ($record->type) {
            MenuItem::TYPE_CATEGORY => $record->category?->full_slug,
            MenuItem::TYPE_PAGE => $record->cmsPage?->slug,
            default => null,
        };
    }

    private function importSubcategories(MenuItem $record): void
    {
        $children = Category::query()
            ->active()
            ->where('parent_id', $record->category_id)
            ->orderBy('name')
            ->get();

        $sort = (int) MenuItem::query()
            ->where('menu_id', $record->menu_id)
            ->where('parent_id', $record->id)
            ->max('sort_order');

        foreach ($children as $child) {
            $exists = MenuItem::query()
                ->where('menu_id', $record->menu_id)
                ->where('parent_id', $record->id)
                ->where('category_id', $child->id)
                ->exists();

            if ($exists) {
                continue;
            }

            MenuItem::query()->create([
                'menu_id' => $record->menu_id,
                'parent_id' => $record->id,
                'type' => MenuItem::TYPE_CATEGORY,
                'category_id' => $child->id,
                'sort_order' => ++$sort,
                'is_active' => true,
            ]);
        }
    }
}
