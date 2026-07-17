<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

class SitemapController extends Controller
{
    use RespondsWithJson;

    public function __invoke(): JsonResponse
    {
        $cached = SystemSetting::query()->where('key', 'sitemap_cache')->first();

        if (! $cached) {
            return $this->success([
                'generated_at' => null,
                'entries' => [],
            ]);
        }

        return $this->success($cached->value);
    }
}
