<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Services\Catalog\ProductReadCache;
use App\Services\Menu\MenuTreeBuilder;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly ProductReadCache $productReadCache,
    ) {}

    public function show(string $slug, MenuTreeBuilder $treeBuilder): JsonResponse
    {
        $payload = $this->productReadCache->rememberMenu($slug, 600, function () use ($slug, $treeBuilder): array {
            $menu = Menu::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->firstOrFail();

            $items = MenuItem::query()
                ->where('menu_id', $menu->id)
                ->active()
                ->with(['category', 'cmsPage'])
                ->orderBy('sort_order')
                ->get();

            return [
                'id' => $menu->id,
                'name' => $menu->name,
                'slug' => $menu->slug,
                'items' => $treeBuilder->build($items),
            ];
        });

        return $this->success($payload);
    }
}
