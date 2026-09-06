<?php

namespace App\Services\Integrations;

use App\Models\InstallmentInquiry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\MetaUserDataHasher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaConversionsApi
{
    private const API_VERSION = 'v26.0';

    public function __construct(
        private readonly TrackingSettings $trackingSettings,
    ) {}

    public function sendPurchase(Order $order): void
    {
        if (! $this->isConfigured()) {
            Log::warning('Meta CAPI purchase skipped: dataset ID or access token missing.');

            return;
        }

        $order->loadMissing('items');

        $contents = $order->items
            ->map(fn (OrderItem $item): array => [
                'id' => (string) ($item->product_id ?: $item->sku ?: $item->id),
                'quantity' => (int) $item->quantity,
            ])
            ->values()
            ->all();

        $contentIds = array_values(array_unique(array_map(
            static fn (array $row): string => (string) $row['id'],
            $contents,
        )));

        $this->sendEvents([[
            'event_name' => 'Purchase',
            'event_time' => $order->created_at?->timestamp ?? time(),
            'event_id' => (string) $order->order_number,
            'action_source' => 'website',
            'user_data' => $this->buildUserDataFromOrder($order),
            'custom_data' => [
                'currency' => 'BAM',
                'value' => (float) $order->total,
                'content_type' => 'product',
                'content_ids' => $contentIds,
                'contents' => $contents,
                'num_items' => (int) $order->items_count,
                'order_id' => (string) $order->order_number,
            ],
        ]]);
    }

    public function sendLead(InstallmentInquiry $inquiry): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $inquiry->loadMissing('product');

        $contentId = $this->resolveProductContentId($inquiry->product, $inquiry->product_id);

        $this->sendEvents([[
            'event_name' => 'Lead',
            'event_time' => $inquiry->created_at?->timestamp ?? time(),
            'event_id' => 'lead-'.$inquiry->id,
            'action_source' => 'system_generated',
            'user_data' => $this->buildUserDataFromInquiry($inquiry),
            'custom_data' => array_filter([
                'event_source' => 'crm',
                'lead_event_source' => $this->crmName(),
                'currency' => 'BAM',
                'value' => (float) $inquiry->base_price,
                'content_type' => 'product',
                'content_ids' => $contentId !== null ? [$contentId] : null,
                'contents' => $contentId !== null
                    ? [['id' => $contentId, 'quantity' => (int) $inquiry->quantity]]
                    : null,
                'product_name' => $inquiry->product_name,
                'installment_type' => $inquiry->installment_type,
                'months' => (int) $inquiry->months,
            ], static fn ($value): bool => $value !== null && $value !== []),
        ]]);
    }

    public function sendProductView(Product $product, string $eventSourceUrl, ?string $clientIp, ?string $userAgent): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $contentId = $this->resolveProductContentId($product, $product->id);
        $eventId = 'view-'.$contentId.'-'.now()->format('YmdHi');

        $this->sendEvents([
            [
                'event_name' => 'PageView',
                'event_time' => time(),
                'event_id' => 'pv-'.$eventId,
                'action_source' => 'website',
                'event_source_url' => $eventSourceUrl,
                'user_data' => $this->buildUserDataFromRequest($clientIp, $userAgent),
            ],
            [
                'event_name' => 'ViewContent',
                'event_time' => time(),
                'event_id' => $eventId,
                'action_source' => 'website',
                'event_source_url' => $eventSourceUrl,
                'user_data' => $this->buildUserDataFromRequest($clientIp, $userAgent),
                'custom_data' => [
                    'currency' => 'BAM',
                    'value' => (float) $product->display_price,
                    'content_type' => 'product',
                    'content_ids' => [$contentId],
                    'contents' => [['id' => $contentId, 'quantity' => 1]],
                    'content_name' => (string) $product->name,
                ],
            ],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    private function sendEvents(array $events): void
    {
        $payload = ['data' => $events];

        $testEventCode = $this->testEventCode();
        if ($testEventCode !== '') {
            $payload['test_event_code'] = $testEventCode;
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->asJson()
                ->post($this->eventsUrl(), $payload);

            $response->throw();

            $body = $response->json();
            if (is_array($body) && ! empty($body['messages'])) {
                Log::warning('Meta CAPI returned diagnostic messages.', [
                    'events' => array_column($events, 'event_name'),
                    'messages' => $body['messages'],
                    'fbtrace_id' => $body['fbtrace_id'] ?? null,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Meta CAPI request failed.', [
                'events' => array_column($events, 'event_name'),
                'message' => $exception->getMessage(),
            ]);
            report($exception);
        }
    }

    private function isConfigured(): bool
    {
        return $this->datasetId() !== '' && $this->accessToken() !== '';
    }

    private function eventsUrl(): string
    {
        return 'https://graph.facebook.com/'.self::API_VERSION.'/'
            .$this->datasetId()
            .'/events?'.http_build_query(['access_token' => $this->accessToken()]);
    }

    private function datasetId(): string
    {
        $settings = $this->trackingSettings->all();
        $datasetId = trim((string) ($settings['fb_dataset_id'] ?? ''));

        if ($datasetId !== '') {
            return $datasetId;
        }

        return trim((string) ($settings['fb_pixel_id'] ?? ''));
    }

    private function accessToken(): string
    {
        return trim((string) ($this->trackingSettings->all()['fb_access_token'] ?? ''));
    }

    private function testEventCode(): string
    {
        return trim((string) ($this->trackingSettings->all()['fb_test_event_code'] ?? ''));
    }

    private function crmName(): string
    {
        $name = trim((string) ($this->trackingSettings->all()['fb_crm_name'] ?? ''));

        return $name !== '' ? $name : 'BNC Shop';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUserDataFromRequest(?string $clientIp, ?string $userAgent): array
    {
        return array_filter([
            'client_ip_address' => filled($clientIp) ? $clientIp : null,
            'client_user_agent' => filled($userAgent) ? $userAgent : null,
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUserDataFromOrder(Order $order): array
    {
        return array_filter([
            'em' => $this->hashedList(MetaUserDataHasher::hashEmail($order->email)),
            'ph' => $this->hashedList(MetaUserDataHasher::hashPhone($order->phone)),
            'fn' => $this->hashedList(MetaUserDataHasher::hashName($order->first_name)),
            'ln' => $this->hashedList(MetaUserDataHasher::hashName($order->last_name)),
            'ct' => $this->hashedList(MetaUserDataHasher::hashCity($order->city)),
            'zp' => $this->hashedList(MetaUserDataHasher::hashZip($order->postal_code)),
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUserDataFromInquiry(InstallmentInquiry $inquiry): array
    {
        return array_filter([
            'em' => $this->hashedList(MetaUserDataHasher::hashEmail($inquiry->email)),
            'ph' => $this->hashedList(MetaUserDataHasher::hashPhone($inquiry->phone)),
            'fn' => $this->hashedList(MetaUserDataHasher::hashName($inquiry->first_name)),
            'ln' => $this->hashedList(MetaUserDataHasher::hashName($inquiry->last_name)),
            'client_ip_address' => filled($inquiry->ip_address) ? (string) $inquiry->ip_address : null,
            'client_user_agent' => filled($inquiry->user_agent) ? (string) $inquiry->user_agent : null,
        ], static fn ($value): bool => $value !== null);
    }

    /**
     * @return array<int, string>|null
     */
    private function hashedList(?string $hash): ?array
    {
        return $hash !== null ? [$hash] : null;
    }

    private function resolveProductContentId(?Product $product, ?int $productId): ?string
    {
        if ($product !== null) {
            return (string) $product->id;
        }

        return $productId !== null ? (string) $productId : null;
    }
}
