<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Models\CmsPage;
use App\Services\Catalog\ProductReadCache;
use Illuminate\Http\JsonResponse;

class PageController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}

    public function show(string $slug): JsonResponse
    {
        $payload = $this->productReadCache->rememberPage($slug, 600, function () use ($slug): array {
            $page = CmsPage::query()
                ->active()
                ->where('slug', $slug)
                ->firstOrFail();

            return [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'body' => $page->body,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
            ];
        });

        return $this->success($payload);
    }
}
