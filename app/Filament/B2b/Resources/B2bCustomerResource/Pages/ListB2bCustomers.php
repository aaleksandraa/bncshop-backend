<?php

namespace App\Filament\B2b\Resources\B2bCustomerResource\Pages;

use App\Filament\B2b\Resources\B2bCustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListB2bCustomers extends ListRecords
{
    protected static string $resource = B2bCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
