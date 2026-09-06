<?php

namespace App\Services\Integrations;

use App\Models\Product;
use App\Support\PublicStorageUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MetaCatalogFeedService
{
    public function __construct(
        private readonly TrackingSettings $trackingSettings,
        private readonly MetaCatalogFeedPolicy $feedPolicy,
    ) {}

    public function isAuthorized(?string $token): bool
    {
        $expected = trim((string) ($this->trackingSettings->all()['fb_catalog_feed_token'] ?? ''));

        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }

    public function feedToken(): string
    {
        $settings = $this->trackingSettings->all();
        $token = trim((string) ($settings['fb_catalog_feed_token'] ?? ''));

        if ($token !== '') {
            return $token;
        }

        $token = bin2hex(random_bytes(16));
        $this->trackingSettings->save(['fb_catalog_feed_token' => $token]);

        return $token;
    }

    public function feedUrl(): string
    {
        return $this->frontendUrl().'/backend-api/v1/feeds/meta-catalog.csv?token='.$this->feedToken();
    }

    public function toStreamedResponse(): StreamedResponse
    {
        return response()->stream(function (): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'id',
                'title',
                'description',
                'availability',
                'condition',
                'price',
                'link',
                'image_link',
                'brand',
                'google_product_category',
            ]);

            foreach ($this->productRows() as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'public, max-age=900',
        ]);
    }

    public function resolvePublicImageUrl(Product $product): ?string
    {
        $image = $product->defaultImage;
        if ($image !== null) {
            $url = $image->resolvedUrl();

            return $this->absoluteHttpsImageUrl($url);
        }

        $apiUrl = trim((string) ($product->api_default_image_url ?? ''));

        return $this->absoluteHttpsImageUrl($apiUrl !== '' ? $apiUrl : null);
    }

    /**
     * @return LazyCollection<int, list<string|null>>
     */
    private function productRows(): LazyCollection
    {
        $frontendUrl = $this->frontendUrl();

        $query = Product::query()
            ->public()
            ->active()
            ->where('display_price', '>', 0)
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNotNull('default_image_id')
                    ->orWhereNotNull('api_default_image_url');
            })
            ->with(['defaultImage', 'manufacturer', 'category'])
            ->orderBy('id');

        $this->feedPolicy->applyToQuery($query);

        return $query
            ->cursor()
            ->map(function (Product $product) use ($frontendUrl): ?array {
                $imageUrl = $this->resolvePublicImageUrl($product);
                if ($imageUrl === null) {
                    return null;
                }

                $description = trim(strip_tags((string) ($product->short_description ?: $product->description ?: $product->name)));
                $description = preg_replace('/\s+/', ' ', $description) ?? $description;
                $description = mb_substr($description, 0, 4999);

                return [
                    (string) $product->id,
                    mb_substr((string) $product->name, 0, 200),
                    $description,
                    $product->available_stock > 0 ? 'in stock' : 'out of stock',
                    $this->feedPolicy->resolveCondition($product),
                    number_format((float) $product->display_price, 2, '.', '').' BAM',
                    $frontendUrl.'/proizvod/'.$product->slug,
                    $imageUrl,
                    $product->manufacturer?->name,
                    $product->category?->name,
                ];
            })
            ->filter(static fn (?array $row): bool => $row !== null);
    }

    private function absoluteHttpsImageUrl(?string $url): ?string
    {
        $absolute = PublicStorageUrl::absoluteFromResolved($url);

        if (! is_string($absolute) || $absolute === '') {
            return null;
        }

        if (! str_starts_with($absolute, 'https://')) {
            return null;
        }

        return $absolute;
    }

    private function frontendUrl(): string
    {
        return rtrim((string) config('bnc.frontend_url', 'https://bnc.ba'), '/');
    }
}
