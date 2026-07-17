<?php

namespace App\Filament\Concerns;

use App\Models\AttributeDefinition;
use App\Services\Catalog\AttributeMergeService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Illuminate\Database\Eloquent\Collection;

trait HasAttributeMergeActions
{
    /**
     * @return array<int, Tables\Actions\Action|Tables\Actions\BulkAction>
     */
    protected static function attributeMergeTableActions(): array
    {
        return [
            static::makeMergeIntoAction(),
        ];
    }

    /**
     * @return array<int, Tables\Actions\BulkAction>
     */
    protected static function attributeMergeBulkActions(): array
    {
        return [
            static::makeMergeIntoBulkAction(),
        ];
    }

    protected static function makeMergeIntoAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('mergeInto')
            ->label('Spoji u...')
            ->icon('heroicon-o-arrows-pointing-in')
            ->visible(fn (AttributeDefinition $record): bool => ! $record->isAlias())
            ->form(static::mergeTargetForm())
            ->action(function (AttributeDefinition $record, array $data): void {
                static::runAttributeMerge(
                    AttributeDefinition::query()->findOrFail((int) $data['target_id']),
                    collect([$record]),
                );
            });
    }

    protected static function makeMergeIntoBulkAction(): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('mergeInto')
            ->label('Spoji u atribut')
            ->icon('heroicon-o-arrows-pointing-in')
            ->form(static::mergeTargetForm())
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                static::runAttributeMerge(
                    AttributeDefinition::query()->findOrFail((int) $data['target_id']),
                    $records,
                );
            });
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function mergeTargetForm(): array
    {
        return [
            Forms\Components\Select::make('target_id')
                ->label('Glavni atribut (zadržava se na shopu)')
                ->helperText('Odabrani atributi postaju aliasi i njihove vrijednosti se prebacuju na glavni atribut.')
                ->searchable()
                ->getSearchResultsUsing(function (string $search): array {
                    return AttributeDefinition::query()
                        ->canonical()
                        ->where(function ($query) use ($search): void {
                            $query
                                ->where('name', 'ilike', "%{$search}%")
                                ->orWhere('display_name', 'ilike', "%{$search}%");
                        })
                        ->orderBy('name')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (AttributeDefinition $record): array => [
                            $record->id => static::attributeSelectLabel($record),
                        ])
                        ->all();
                })
                ->getOptionLabelUsing(fn ($value): ?string => AttributeDefinition::query()
                    ->find($value)
                    ?->publicLabel())
                ->required(),
        ];
    }

    protected static function attributeSelectLabel(AttributeDefinition $record): string
    {
        $label = $record->publicLabel();

        if (filled($record->display_name) && $record->display_name !== $record->name) {
            return "{$label} ({$record->name})";
        }

        return $label;
    }

    /**
     * @param  Collection<int, AttributeDefinition>  $sources
     */
    protected static function runAttributeMerge(AttributeDefinition $canonical, Collection $sources): void
    {
        $service = app(AttributeMergeService::class);
        $merged = 0;
        $products = 0;

        foreach ($sources as $source) {
            if ($source->id === $canonical->id || $source->isAlias()) {
                continue;
            }

            $result = $service->merge($canonical, $source);
            $merged++;
            $products += $result['products'];
        }

        if ($merged === 0) {
            Notification::make()
                ->title('Nema atributa za spajanje')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Atributi spojeni')
            ->body("Spojeno {$merged} atribut(a). Ažurirano {$products} proizvoda.")
            ->success()
            ->send();
    }
}
