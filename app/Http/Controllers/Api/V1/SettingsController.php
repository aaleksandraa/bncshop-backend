<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Models\SystemSetting;
use App\Services\Catalog\ProductReadCache;
use App\Services\Integrations\TrackingSettings;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}

    public function publicSettings(TrackingSettings $trackingSettings): JsonResponse
    {
        $settings = $this->productReadCache->rememberPublicSettings(600, function () use ($trackingSettings): array {
            $settings = SystemSetting::query()
                ->whereIn('group', ['shop', 'checkout', 'seo'])
                ->get()
                ->mapWithKeys(fn (SystemSetting $setting): array => [$setting->key => $setting->value]);

            $settings['tracking'] = $trackingSettings->publicConfig();

            return $settings->all();
        });

        return $this->success($settings);
    }
}
