<?php

namespace App\Filament\Concerns;

use Filament\Forms;

trait HasSeoFormFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function seoFormFields(): array
    {
        return [
            Forms\Components\TextInput::make('meta_title')
                ->label('Meta naslov')
                ->maxLength(255),
            Forms\Components\Textarea::make('meta_description')
                ->label('Meta opis')
                ->rows(3),
            Forms\Components\TextInput::make('og_image_url')
                ->label('OG slika URL')
                ->url()
                ->maxLength(2048),
            Forms\Components\TextInput::make('canonical')
                ->label('Canonical URL')
                ->maxLength(2048),
            Forms\Components\TextInput::make('robots')
                ->label('Robots')
                ->default('index,follow')
                ->placeholder('index,follow')
                ->maxLength(255),
            Forms\Components\Toggle::make('is_locked')
                ->label('Zaključaj SEO')
                ->default(false),
        ];
    }
}
