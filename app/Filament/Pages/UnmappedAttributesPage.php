<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Concerns\HasAttributeMergeActions;
use App\Models\AttributeDefinition;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UnmappedAttributesPage extends Page implements HasTable
{
    use AuthorizesWithPermissions;
    use HasAttributeMergeActions;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-language';

    protected static ?string $navigationGroup = 'Katalog';

    protected static ?string $navigationLabel = 'Nemapirani atributi';

    protected static ?string $title = 'Nemapirani atributi i vrijednosti';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.unmapped-attributes';

    protected static function permissionPrefix(): string
    {
        return 'attributes';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AttributeDefinition::query()
                    ->where('is_mapped', false)
                    ->canonical()
                    ->withCount('productValues')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('API naziv')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Frontend naziv')
                    ->placeholder('— nije postavljen —'),
                Tables\Columns\TextColumn::make('internal_type')
                    ->label('Tip')
                    ->badge(),
                Tables\Columns\TextColumn::make('sample_values')
                    ->label('Primjer vrijednosti')
                    ->state(function (AttributeDefinition $record): string {
                        return DB::table('product_attribute_values')
                            ->where('attribute_definition_id', $record->id)
                            ->select('raw_value')
                            ->distinct()
                            ->limit(5)
                            ->pluck('raw_value')
                            ->filter()
                            ->implode(', ');
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('product_values_count')
                    ->label('Proizvoda')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_public')
                    ->label('Javno')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('internal_type')
                    ->label('Tip')
                    ->options([
                        'text' => 'Tekst',
                        'number' => 'Broj',
                        'boolean' => 'Da/Ne',
                        'select' => 'Select',
                        'multi_select' => 'Multi select',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('map')
                    ->label('Mapiraj')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (AttributeDefinition $record): string => route('filament.admin.resources.attribute-definitions.edit', $record)),
                ...static::attributeMergeTableActions(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    ...static::attributeMergeBulkActions(),
                ]),
            ])
            ->defaultSort('product_values_count', 'desc');
    }
}
