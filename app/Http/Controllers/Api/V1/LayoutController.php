<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\SystemSetting;
use App\Services\Catalog\CategoryNavBuilder;
use App\Services\Catalog\ProductReadCache;
use App\Services\Commerce\InstallmentSettings;
use App\Services\Integrations\TrackingSettings;
use App\Services\Menu\MenuTreeBuilder;
use Illuminate\Http\JsonResponse;

class LayoutController extends Controller
{
    use RespondsWithJson;

    public function __construct(
        private readonly ProductReadCache $productReadCache,
        private readonly CategoryNavBuilder $categoryNavBuilder,
    ) {}

    public function shell(
        TrackingSettings $trackingSettings,
        InstallmentSettings $installmentSettings,
        MenuTreeBuilder $treeBuilder,
    ): JsonResponse {
        $payload = $this->productReadCache->rememberLayoutShell(300, function () use (
            $trackingSettings,
            $installmentSettings,
            $treeBuilder,
        ): array {
            return [
                'settings' => $this->buildPublicSettings($trackingSettings),
                'header_menu' => $this->buildMenuPayload('header', $treeBuilder),
                'footer_menu' => $this->buildMenuPayload('footer', $treeBuilder),
                'category_nav' => $this->categoryNavBuilder->buildPayload(),
                'installment_settings' => $installmentSettings->publicPayload(),
            ];
        });

        return $this->success($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPublicSettings(TrackingSettings $trackingSettings): array
    {
        $settings = SystemSetting::query()
            ->publicFacing()
            ->get()
            ->mapWithKeys(fn (SystemSetting $setting): array => [$setting->key => $setting->value]);

        $settings['tracking'] = $trackingSettings->publicConfig();

        return $settings->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMenuPayload(string $slug, MenuTreeBuilder $treeBuilder): array
    {
        return $this->productReadCache->rememberMenu($slug, 600, function () use ($slug, $treeBuilder): array {
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
    }
}
