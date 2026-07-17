<?php

namespace App\Filament\Forms;

use App\Support\Catalog\CategoryScopeResolver;
use Filament\Forms;
use Filament\Forms\Get;

class MarginCategoryScopeFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function schema(): array
    {
        return [
            Forms\Components\Select::make('subcategory_scope')
                ->label('Obuhvat kategorije')
                ->options([
                    'category_only' => 'Samo ova kategorija',
                    'all_descendants' => 'Sve podkategorije',
                    'selected' => 'Odabrane podkategorije',
                ])
                ->default('category_only')
                ->required()
                ->native(false)
                ->live()
                ->helperText('Za potpunu kontrolu odaberite "Odabrane podkategorije" i označite tačno koje podkategorije ulaze u pravilo.'),
            Forms\Components\Toggle::make('include_parent_category')
                ->label('Uključi i glavnu kategoriju')
                ->default(true)
                ->visible(fn (Get $get): bool => $get('subcategory_scope') === 'selected')
                ->helperText('Ako je isključeno, pravilo vrijedi samo za označene podkategorije, ne i za proizvode direktno u glavnoj kategoriji.'),
            Forms\Components\CheckboxList::make('target_category_ids')
                ->label('Podkategorije')
                ->options(fn (Get $get): array => self::targetOptions($get))
                ->columns(2)
                ->searchable()
                ->visible(fn (Get $get): bool => $get('subcategory_scope') === 'selected' && filled($get('category_id')))
                ->required(fn (Get $get): bool => $get('subcategory_scope') === 'selected')
                ->helperText('Prikazane su sve podkategorije ispod odabrane glavne kategorije. Možete označiti samo one koje želite.'),
            Forms\Components\Placeholder::make('target_category_hint')
                ->label('')
                ->content('Prvo odaberite glavnu kategoriju da biste vidjeli listu podkategorija.')
                ->visible(fn (Get $get): bool => $get('subcategory_scope') === 'selected' && blank($get('category_id'))),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function targetOptions(Get $get): array
    {
        $categoryId = (int) $get('category_id');

        if ($categoryId <= 0) {
            return [];
        }

        return CategoryScopeResolver::descendantOptions($categoryId);
    }
}
