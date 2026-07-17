<?php

namespace App\Filament\B2b\Resources\B2bCustomerResource\Pages;

use App\Filament\B2b\Resources\B2bCustomerResource;
use App\Services\B2b\B2bCustomerProvisioner;
use Filament\Resources\Pages\CreateRecord;

class CreateB2bCustomer extends CreateRecord
{
    protected static string $resource = B2bCustomerResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $formData = $this->form->getState();

        return app(B2bCustomerProvisioner::class)->createCustomer([
            'name' => $formData['name'],
            'email' => $formData['email'],
            'phone' => $formData['phone'],
            'company_name' => $formData['company_name'],
            'company_address' => $formData['company_address'],
            'jib' => $formData['jib'],
            'pdv_number' => $formData['pdv_number'] ?? null,
            'discount_percent' => $formData['discount_percent'] ?? null,
        ], auth()->user());
    }
}
