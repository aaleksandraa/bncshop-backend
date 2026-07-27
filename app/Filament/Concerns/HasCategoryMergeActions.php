<?php

namespace App\Filament\Concerns;

use App\Filament\Forms\CategoryMappingSelect;
use App\Models\Category;
use App\Services\Catalog\CategoryMergeService;
use App\Support\Catalog\CategoryScopeResolver;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

trait HasCategoryMergeActions
{
    /**
     * @return array<int, Tables\Actions\Action|Tables\Actions\BulkAction>
     */
    protected static function categoryMergeTableActions(): array
    {
        return [
            static::makeCategoryMergeIntoAction(),
        ];
    }

    /**
     * @return array<int, Tables\Actions\BulkAction>
     */
    protected static function categoryMergeBulkActions(): array
    {
        return [
            static::makeCategoryMergeIntoBulkAction(),
        ];
    }

    protected static function makeCategoryMergeIntoAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('mergeInto')
            ->label('Spoji u...')
            ->icon('heroicon-o-arrows-pointing-in')
            ->form(static::categoryMergeForm())
            ->requiresConfirmation()
            ->modalHeading('Spoji kategoriju')
            ->modalDescription('Proizvodi i mapiranja iz ove kategorije prelaze u ciljnu kategoriju. Izvorna kategorija se deaktivira.')
            ->action(function (Category $record, array $data): void {
                static::runCategoryMerge(
                    Category::query()->findOrFail((int) $data['target_id']),
                    collect([$record]),
                    $data,
                );
            });
    }

    protected static function makeCategoryMergeIntoBulkAction(): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make('mergeInto')
            ->label('Spoji u kategoriju')
            ->icon('heroicon-o-arrows-pointing-in')
            ->form(static::categoryMergeForm())
            ->requiresConfirmation()
            ->modalHeading('Spoji kategorije')
            ->modalDescription('Proizvodi i mapiranja iz odabranih kategorija prelaze u ciljnu kategoriju. Izvorne kategorije se deaktiviraju.')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                static::runCategoryMerge(
                    Category::query()->findOrFail((int) $data['target_id']),
                    $records,
                    $data,
                );
            });
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected static function categoryMergeForm(): array
    {
        return [
            CategoryMappingSelect::make('target_id')
                ->label('Ciljna kategorija (zadržava se)')
                ->helperText('Proizvodi iz izvornih kategorija prelaze ovdje. Ne možete odabrati kategoriju unutar izvorne grane.')
                ->required(),
            Forms\Components\Toggle::make('reparent_children')
                ->label('Premjesti podkategorije u ciljnu')
                ->helperText('Direktne podkategorije izvorne kategorije postaju djeca ciljne kategorije.')
                ->default(true),
            Forms\Components\Toggle::make('create_redirect')
                ->label('Kreiraj 301 redirect')
                ->helperText('Stari URL kategorije preusmjerava na novi slug.')
                ->default(true),
            Forms\Components\Toggle::make('deactivate_source')
                ->label('Deaktiviraj izvorne kategorije')
                ->helperText('Izvorne kategorije ostaju u bazi ali se ne prikazuju na shopu.')
                ->default(true),
        ];
    }

    /**
     * @param  Collection<int, Category>  $sources
     * @param  array<string, mixed>  $options
     */
    protected static function runCategoryMerge(Category $target, Collection $sources, array $options): void
    {
        $service = app(CategoryMergeService::class);
        $merged = 0;
        $products = 0;
        $errors = [];

        foreach ($sources as $source) {
            if ($source->id === $target->id) {
                continue;
            }

            $excludedIds = CategoryScopeResolver::descendantIds($source->id, includeSelf: true);

            if (in_array($target->id, $excludedIds, true)) {
                $errors[] = $source->publicName().': ciljna kategorija je unutar izvorne grane.';

                continue;
            }

            try {
                $result = $service->merge($target, $source, [
                    'reparent_children' => (bool) ($options['reparent_children'] ?? true),
                    'deactivate_source' => (bool) ($options['deactivate_source'] ?? true),
                    'create_redirect' => (bool) ($options['create_redirect'] ?? true),
                ]);

                $merged++;
                $products += $result['products'];
            } catch (InvalidArgumentException $exception) {
                $errors[] = $source->publicName().': '.$exception->getMessage();
            }
        }

        if ($merged === 0) {
            Notification::make()
                ->title('Nema kategorija za spajanje')
                ->body($errors !== [] ? implode("\n", $errors) : 'Provjerite odabir.')
                ->warning()
                ->send();

            return;
        }

        $body = "Spojeno {$merged} kategorija. Premješteno {$products} proizvoda u «{$target->publicName()}».";

        if ($errors !== []) {
            $body .= "\n\nPreskočeno:\n".implode("\n", $errors);
        }

        Notification::make()
            ->title('Kategorije spojene')
            ->body($body)
            ->success()
            ->send();
    }
}
