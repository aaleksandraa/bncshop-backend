<?php

namespace App\Filament\Resources\ApiImportJobResource\Pages;

use App\Filament\Resources\ApiImportJobResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;

class ListApiImportJobs extends ListRecords
{
    protected static string $resource = ApiImportJobResource::class;
}
