<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\B2bProductImage;
use App\Models\Manufacturer;
use App\Models\ProductImage;
use App\Services\Media\MediaMigrationService;
use Illuminate\Console\Command;

class MigrateMediaToR2Command extends Command
{
    protected $signature = 'bnc:migrate-media-to-r2
                            {--type=all : products|logos|blog|b2b|all}
                            {--limit=100 : Max records per type}
                            {--force : Re-upload even when already on R2}
                            {--dry-run : List candidates without uploading}
                            {--all-records : Include already migrated records instead of pending only}';

    protected $description = 'Migrate legacy media files to optimized R2 storage';

    public function handle(MediaMigrationService $migration): int
    {
        $type = (string) $this->option('type');
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $pendingOnly = ! $force && ! (bool) $this->option('all-records');

        $types = $type === 'all'
            ? ['products', 'logos', 'blog', 'b2b']
            : [$type];

        $totals = ['migrated' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($types as $selectedType) {
            match ($selectedType) {
                'products' => $this->migrateProducts($migration, $limit, $force, $dryRun, $pendingOnly, $totals),
                'logos' => $this->migrateManufacturers($migration, $limit, $force, $dryRun, $pendingOnly, $totals),
                'blog' => $this->migrateBlogPosts($migration, $limit, $force, $dryRun, $pendingOnly, $totals),
                'b2b' => $this->migrateB2bImages($migration, $limit, $force, $dryRun, $pendingOnly, $totals),
                default => $this->error("Unknown type [{$selectedType}]"),
            };
        }

        $this->info("Done. migrated={$totals['migrated']} skipped={$totals['skipped']} failed={$totals['failed']}");

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array{migrated:int,skipped:int,failed:int}  $totals
     */
    private function migrateProducts(MediaMigrationService $migration, int $limit, bool $force, bool $dryRun, bool $pendingOnly, array &$totals): void
    {
        $query = ProductImage::query()
            ->where('status', 'active')
            ->with('product:id,external_product_id')
            ->orderBy('id');

        if ($pendingOnly) {
            $targetDisk = (string) config('bnc.media_disk', 'r2');
            $query->where(function ($builder) use ($targetDisk): void {
                $builder
                    ->whereNull('optimized_at')
                    ->orWhereNull('storage_disk')
                    ->orWhere('storage_disk', '!=', $targetDisk);
            });
        }

        $images = $query->limit($limit)->get();

        foreach ($images as $image) {
            if ($dryRun) {
                $this->line("[dry-run] product_image #{$image->id} {$image->local_path}");
                $totals['skipped']++;

                continue;
            }

            $result = $migration->migrateProductImage($image, $force);

            if ($result['success']) {
                $totals[$result['message'] === 'already migrated' ? 'skipped' : 'migrated']++;
                $this->line("product_image #{$image->id}: {$result['message']}");
            } else {
                $totals['failed']++;
                $this->warn("product_image #{$image->id}: {$result['message']}");
            }
        }
    }

    /**
     * @param  array{migrated:int,skipped:int,failed:int}  $totals
     */
    private function migrateManufacturers(MediaMigrationService $migration, int $limit, bool $force, bool $dryRun, bool $pendingOnly, array &$totals): void
    {
        $query = Manufacturer::query()
            ->where(function ($builder): void {
                $builder->whereNotNull('logo_path')->orWhereNotNull('logo_url');
            })
            ->orderBy('id');

        if ($pendingOnly) {
            $targetDisk = (string) config('bnc.media_disk', 'r2');
            $query->where(function ($builder) use ($targetDisk): void {
                $builder
                    ->whereNull('optimized_at')
                    ->orWhereNull('storage_disk')
                    ->orWhere('storage_disk', '!=', $targetDisk);
            });
        }

        $items = $query->limit($limit)->get();

        foreach ($items as $manufacturer) {
            if ($dryRun) {
                $this->line("[dry-run] manufacturer #{$manufacturer->id} {$manufacturer->logo_path}");
                $totals['skipped']++;

                continue;
            }

            $result = $migration->migrateManufacturer($manufacturer, $force);

            if ($result['success']) {
                $totals[$result['message'] === 'already migrated' ? 'skipped' : 'migrated']++;
                $this->line("manufacturer #{$manufacturer->id}: {$result['message']}");
            } else {
                $totals['failed']++;
                $this->warn("manufacturer #{$manufacturer->id}: {$result['message']}");
            }
        }
    }

    /**
     * @param  array{migrated:int,skipped:int,failed:int}  $totals
     */
    private function migrateBlogPosts(MediaMigrationService $migration, int $limit, bool $force, bool $dryRun, bool $pendingOnly, array &$totals): void
    {
        $query = BlogPost::query()
            ->where(function ($builder): void {
                $builder->whereNotNull('featured_image_path')->orWhereNotNull('featured_image_url');
            })
            ->orderBy('id');

        if ($pendingOnly) {
            $targetDisk = (string) config('bnc.media_disk', 'r2');
            $query->where(function ($builder) use ($targetDisk): void {
                $builder
                    ->whereNull('optimized_at')
                    ->orWhereNull('storage_disk')
                    ->orWhere('storage_disk', '!=', $targetDisk);
            });
        }

        $items = $query->limit($limit)->get();

        foreach ($items as $post) {
            if ($dryRun) {
                $this->line("[dry-run] blog_post #{$post->id} {$post->featured_image_path}");
                $totals['skipped']++;

                continue;
            }

            $result = $migration->migrateBlogPost($post, $force);

            if ($result['success']) {
                $totals[$result['message'] === 'already migrated' ? 'skipped' : 'migrated']++;
                $this->line("blog_post #{$post->id}: {$result['message']}");
            } else {
                $totals['failed']++;
                $this->warn("blog_post #{$post->id}: {$result['message']}");
            }
        }
    }

    /**
     * @param  array{migrated:int,skipped:int,failed:int}  $totals
     */
    private function migrateB2bImages(MediaMigrationService $migration, int $limit, bool $force, bool $dryRun, bool $pendingOnly, array &$totals): void
    {
        $query = B2bProductImage::query()->orderBy('id');

        if ($pendingOnly) {
            $targetDisk = (string) config('bnc.media_disk', 'r2');
            $query->where(function ($builder) use ($targetDisk): void {
                $builder
                    ->whereNull('optimized_at')
                    ->orWhereNull('storage_disk')
                    ->orWhere('storage_disk', '!=', $targetDisk);
            });
        }

        $items = $query->limit($limit)->get();

        foreach ($items as $image) {
            if ($dryRun) {
                $this->line("[dry-run] b2b_product_image #{$image->id} {$image->path}");
                $totals['skipped']++;

                continue;
            }

            $result = $migration->migrateB2bProductImage($image, $force);

            if ($result['success']) {
                $totals[$result['message'] === 'already migrated' ? 'skipped' : 'migrated']++;
                $this->line("b2b_product_image #{$image->id}: {$result['message']}");
            } else {
                $totals['failed']++;
                $this->warn("b2b_product_image #{$image->id}: {$result['message']}");
            }
        }
    }
}
