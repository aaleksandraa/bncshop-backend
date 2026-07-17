<?php

namespace App\Console\Commands;

use App\Models\ApiSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ValidateImportDataCommand extends Command
{
    protected $signature = 'bnc:validate-import';

    protected $description = 'Run SQL validation checklist after A1 import';

    public function handle(): int
    {
        $checks = [
            'categories' => 'SELECT COUNT(*) FROM categories',
            'attribute_definitions' => 'SELECT COUNT(*) FROM attribute_definitions',
            'products' => 'SELECT COUNT(*) FROM products',
            'products_without_category' => 'SELECT COUNT(*) FROM products WHERE category_id IS NULL',
            'products_without_images' => 'SELECT COUNT(*) FROM products p LEFT JOIN product_images pi ON pi.product_id = p.id WHERE pi.id IS NULL',
            'duplicate_external_ids' => 'SELECT COUNT(*) FROM (SELECT external_product_id FROM products GROUP BY external_product_id HAVING COUNT(*) > 1)',
            'orphan_attribute_values' => 'SELECT COUNT(*) FROM product_attribute_values pav LEFT JOIN attribute_definitions ad ON ad.id = pav.attribute_definition_id WHERE ad.id IS NULL',
            'public_without_price' => 'SELECT COUNT(*) FROM products WHERE is_public = 1 AND display_price IS NULL',
        ];

        $rows = [];
        foreach ($checks as $label => $sql) {
            $rows[] = [$label, DB::scalar($sql)];
        }

        $this->table(['Check', 'Count'], $rows);

        $syncStatus = DB::select('SELECT sync_status, COUNT(*) as cnt FROM products GROUP BY sync_status');
        $this->info('Sync status distribution:');
        foreach ($syncStatus as $row) {
            $this->line("  {$row->sync_status}: {$row->cnt}");
        }

        return self::SUCCESS;
    }
}
