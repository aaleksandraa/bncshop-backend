<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\MarketingContact;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var MarketingContact $marketingContact */
        $marketingContact = $this->record;

        if (! $marketingContact->isRegistered()) {
            $this->redirect(CustomerResource::getUrl('view', ['record' => $marketingContact]));
        }
    }
}
