<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Services\Commerce\InstallmentSettings;
use Illuminate\Http\JsonResponse;

class InstallmentSettingsController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly InstallmentSettings $settings,
    ) {}

    public function show(): JsonResponse
    {
        return $this->success($this->settings->publicPayload());
    }
}
