<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\ApiImportJobResource\Pages;
use App\Filament\Resources\ApiImportJobResource\RelationManagers;
use App\Models\ApiImportJob;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ApiImportJobResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static ?string $model = ApiImportJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Integracije';

    protected static ?string $modelLabel = 'Import job';

    protected static ?string $pluralModelLabel = 'Import jobovi';

    protected static ?int $navigationSort = 2;

    protected static function permissionPrefix(): string
    {
        return 'api_import_jobs';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Job')
                    ->schema([
                        Infolists\Components\TextEntry::make('apiSource.name')
                            ->label('API izvor'),
                        Infolists\Components\TextEntry::make('type')
                            ->label('Tip')
                            ->badge(),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'completed' => 'success',
                                'failed' => 'danger',
                                'running' => 'warning',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('started_at')
                            ->label('Početak')
                            ->dateTime('d.m.Y H:i'),
                        Infolists\Components\TextEntry::make('completed_at')
                            ->label('Kraj')
                            ->dateTime('d.m.Y H:i'),
                        Infolists\Components\TextEntry::make('error_message')
                            ->label('Greška')
                            ->columnSpanFull(),
                        Infolists\Components\KeyValueEntry::make('stats')
                            ->label('Statistika')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('apiSource.name')
                    ->label('Izvor')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tip')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'running' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Početak')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Kraj')
                    ->dateTime('d.m.Y H:i'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'running' => 'U toku',
                        'completed' => 'Završeno',
                        'failed' => 'Neuspjelo',
                    ]),
                Tables\Filters\SelectFilter::make('api_source_id')
                    ->label('Izvor')
                    ->relationship('apiSource', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApiImportJobs::route('/'),
            'view' => Pages\ViewApiImportJob::route('/{record}'),
        ];
    }
}
