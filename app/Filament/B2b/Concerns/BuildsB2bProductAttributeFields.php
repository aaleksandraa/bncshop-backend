<?php

namespace App\Filament\B2b\Concerns;

use App\Models\B2bAttributeDefinition;
use App\Services\B2b\B2bProductAttributeService;
use Filament\Forms;
use Filament\Forms\Get;

trait BuildsB2bProductAttributeFields
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function b2bProductAttributeFields(): array
    {
        return [
            Forms\Components\Section::make('Atributi')
                ->schema(fn (Get $get): array => static::buildAttributeComponents((int) ($get('b2b_category_id') ?? 0)))
                ->visible(fn (Get $get): bool => filled($get('b2b_category_id')))
                ->columns(2)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function buildAttributeComponents(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        /** @var B2bProductAttributeService $service */
        $service = app(B2bProductAttributeService::class);
        $definitions = $service->definitionsForCategory($categoryId);

        if ($definitions->isEmpty()) {
            return [
                Forms\Components\Placeholder::make('no_attributes')
                    ->label('')
                    ->content('Ova kategorija nema definisanih atributa.'),
            ];
        }

        return $definitions
            ->map(fn (B2bAttributeDefinition $definition): Forms\Components\Component => static::attributeComponent($definition))
            ->all();
    }

    protected static function attributeComponent(B2bAttributeDefinition $definition): Forms\Components\Component
    {
        $fieldKey = 'attr_'.$definition->slug;
        $options = $definition->options->pluck('value', 'value')->all();

        if ($definition->isText()) {
            return Forms\Components\TextInput::make($fieldKey)
                ->label($definition->name)
                ->maxLength(255);
        }

        if ($definition->isMultiselect()) {
            return Forms\Components\Select::make($fieldKey)
                ->label($definition->name)
                ->options($options)
                ->multiple()
                ->searchable()
                ->createOptionForm([
                    Forms\Components\TextInput::make('value')
                        ->label('Nova vrijednost')
                        ->required()
                        ->maxLength(255),
                ])
                ->createOptionUsing(function (array $data) use ($definition): string {
                    /** @var B2bProductAttributeService $service */
                    $service = app(B2bProductAttributeService::class);

                    return $service->ensureOption($definition, trim((string) ($data['value'] ?? '')))->value;
                });
        }

        return Forms\Components\Select::make($fieldKey)
            ->label($definition->name)
            ->options($options)
            ->searchable()
            ->createOptionForm([
                Forms\Components\TextInput::make('value')
                    ->label('Nova vrijednost')
                    ->required()
                    ->maxLength(255),
            ])
            ->createOptionUsing(function (array $data) use ($definition): string {
                /** @var B2bProductAttributeService $service */
                $service = app(B2bProductAttributeService::class);

                return $service->ensureOption($definition, trim((string) ($data['value'] ?? '')))->value;
            });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function stripAttributeFields(array $data): array
    {
        return collect($data)
            ->reject(fn ($value, string $key): bool => str_starts_with($key, 'attr_'))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function extractAttributeFields(array $data): array
    {
        return collect($data)
            ->filter(fn ($value, string $key): bool => str_starts_with($key, 'attr_'))
            ->all();
    }
}
