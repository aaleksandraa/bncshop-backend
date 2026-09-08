<?php

namespace App\Services\Ananas;

use App\Models\AnanasProductMapping;
use App\Models\Product;

class AnanasProductMappingService
{
    public function __construct(
        private readonly AnanasProductMapper $productMapper,
    ) {}

    public function findOrCreate(Product $product): AnanasProductMapping
    {
        return AnanasProductMapping::query()->firstOrCreate(
            ['product_id' => $product->id],
            [
                'export_enabled' => false,
                'local_status' => AnanasProductMapping::LOCAL_DISABLED,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordSubmission(Product $product, array $payload, ?string $progressId): AnanasProductMapping
    {
        $mapping = $this->findOrCreate($product);

        $mapping->fill([
            'export_enabled' => true,
            'ean' => (string) ($payload['ean'] ?? $product->barcode),
            'sku' => (string) ($payload['sku'] ?? ''),
            'external_id' => (string) ($payload['externalId'] ?? $product->id),
            'local_status' => AnanasProductMapping::LOCAL_SUBMITTED,
            'last_progress_id' => $progressId,
            'payload_hash' => $this->productMapper->fingerprintPayload($payload),
            'stock_hash' => hash('sha256', (string) ($payload['stockLevel'] ?? 0)),
            'price_hash' => hash('sha256', (string) ($payload['basePrice'] ?? 0)),
            'last_submitted_at' => now(),
            'last_error' => null,
            'last_error_at' => null,
        ])->save();

        return $mapping->fresh() ?? $mapping;
    }

    /**
     * @param  array<string, mixed>  $remoteProduct
     */
    public function applyRemoteProduct(AnanasProductMapping $mapping, array $remoteProduct): AnanasProductMapping
    {
        $remoteStatus = isset($remoteProduct['status']) ? (string) $remoteProduct['status'] : null;
        $remoteId = isset($remoteProduct['id']) ? (string) $remoteProduct['id'] : null;

        $mapping->fill([
            'ananas_product_id' => $remoteId,
            'merchant_inventory_id' => $remoteId,
            'ananas_code' => isset($remoteProduct['ananasCode']) ? (string) $remoteProduct['ananasCode'] : $mapping->ananas_code,
            'group_id' => isset($remoteProduct['groupId']) ? (string) $remoteProduct['groupId'] : $mapping->group_id,
            'remote_status' => $remoteStatus,
            'local_status' => $remoteId !== null
                ? AnanasProductMapping::LOCAL_LINKED
                : AnanasProductMapping::LOCAL_PENDING_ONBOARDING,
            'last_success_at' => now(),
            'last_error' => null,
            'last_error_at' => null,
        ])->save();

        return $mapping->fresh() ?? $mapping;
    }

    public function markFailed(AnanasProductMapping $mapping, string $error): AnanasProductMapping
    {
        $mapping->fill([
            'local_status' => AnanasProductMapping::LOCAL_FAILED,
            'last_error' => $error,
            'last_error_at' => now(),
        ])->save();

        return $mapping->fresh() ?? $mapping;
    }

    public function markNotEligible(Product $product, string $reasonCode): AnanasProductMapping
    {
        $mapping = $this->findOrCreate($product);

        $mapping->fill([
            'local_status' => AnanasProductMapping::LOCAL_NOT_ELIGIBLE,
            'last_error' => $reasonCode,
            'last_error_at' => now(),
        ])->save();

        return $mapping->fresh() ?? $mapping;
    }
}
