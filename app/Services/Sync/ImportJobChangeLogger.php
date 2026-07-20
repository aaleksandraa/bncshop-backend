<?php

namespace App\Services\Sync;

use App\Models\ApiImportJob;
use App\Models\ApiImportJobChange;

class ImportJobChangeLogger
{
    private const BATCH_SIZE = 100;

    /** @var list<array<string, mixed>> */
    private array $buffer = [];

    public function log(ProductUpsertResult $result, ApiImportJob $job): void
    {
        $product = $result->product;

        $this->buffer[] = [
            'api_import_job_id' => $job->id,
            'product_id' => $product->id,
            'external_product_id' => (string) $product->external_product_id,
            'product_name' => $product->name,
            'action' => $result->action,
            'changed_fields' => $result->changedFields !== [] ? json_encode($result->changedFields) : null,
            'error_message' => null,
            'created_at' => now(),
        ];

        if (count($this->buffer) >= self::BATCH_SIZE) {
            $this->flush();
        }
    }

    public function logError(string $externalId, string $message, ApiImportJob $job): void
    {
        $this->buffer[] = [
            'api_import_job_id' => $job->id,
            'product_id' => null,
            'external_product_id' => $externalId,
            'product_name' => null,
            'action' => 'error',
            'changed_fields' => null,
            'error_message' => $message,
            'created_at' => now(),
        ];

        if (count($this->buffer) >= self::BATCH_SIZE) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        ApiImportJobChange::query()->insert($this->buffer);
        $this->buffer = [];
    }
}
