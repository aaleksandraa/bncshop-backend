<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\B2bProductImage;
use App\Models\Manufacturer;
use App\Models\ProductImage;
use App\Services\Media\MediaStorage;
use Illuminate\Console\Command;

class MediaAuditCommand extends Command
{
    protected $signature = 'bnc:media-audit {--sample=5 : Random R2 existence checks per type}';

    protected $description = 'Report media migration progress and verify R2 object presence';

    public function handle(MediaStorage $mediaStorage): int
    {
        $targetDisk = $mediaStorage->diskName();

        $this->info('Media migration audit');
        $this->line('Target disk: '.$targetDisk);
        $this->line('R2 configured: '.($mediaStorage->usesR2() ? 'yes' : 'no'));
        $this->newLine();

        $this->auditProductImages($targetDisk, $mediaStorage);
        $this->auditManufacturers($targetDisk, $mediaStorage);
        $this->auditBlogPosts($targetDisk, $mediaStorage);
        $this->auditB2bImages($targetDisk, $mediaStorage);

        return self::SUCCESS;
    }

    private function auditProductImages(string $targetDisk, MediaStorage $mediaStorage): void
    {
        $total = ProductImage::query()->where('status', 'active')->count();
        $migrated = ProductImage::query()
            ->where('status', 'active')
            ->where('storage_disk', $targetDisk)
            ->whereNotNull('optimized_at')
            ->count();

        $this->section('Product images', $total, $migrated);
        $this->verifySample(
            ProductImage::query()
                ->where('storage_disk', $targetDisk)
                ->whereNotNull('local_path')
                ->inRandomOrder()
                ->limit((int) $this->option('sample'))
                ->get()
                ->map(fn (ProductImage $image) => (string) $image->local_path)
                ->all(),
            $mediaStorage,
        );
    }

    private function auditManufacturers(string $targetDisk, MediaStorage $mediaStorage): void
    {
        $total = Manufacturer::query()->whereNotNull('logo_path')->count();
        $migrated = Manufacturer::query()
            ->where('storage_disk', $targetDisk)
            ->whereNotNull('optimized_at')
            ->count();

        $this->section('Manufacturer logos', $total, $migrated);
        $this->verifySample(
            Manufacturer::query()
                ->where('storage_disk', $targetDisk)
                ->whereNotNull('logo_path')
                ->inRandomOrder()
                ->limit((int) $this->option('sample'))
                ->get()
                ->map(fn (Manufacturer $item) => (string) $item->logo_path)
                ->all(),
            $mediaStorage,
        );
    }

    private function auditBlogPosts(string $targetDisk, MediaStorage $mediaStorage): void
    {
        $total = BlogPost::query()->whereNotNull('featured_image_path')->count();
        $migrated = BlogPost::query()
            ->where('storage_disk', $targetDisk)
            ->whereNotNull('optimized_at')
            ->count();

        $this->section('Blog featured images', $total, $migrated);
        $this->verifySample(
            BlogPost::query()
                ->where('storage_disk', $targetDisk)
                ->whereNotNull('featured_image_path')
                ->inRandomOrder()
                ->limit((int) $this->option('sample'))
                ->get()
                ->map(fn (BlogPost $item) => (string) $item->featured_image_path)
                ->all(),
            $mediaStorage,
        );
    }

    private function auditB2bImages(string $targetDisk, MediaStorage $mediaStorage): void
    {
        $total = B2bProductImage::query()->count();
        $migrated = B2bProductImage::query()
            ->where('storage_disk', $targetDisk)
            ->whereNotNull('optimized_at')
            ->count();

        $this->section('B2B product images', $total, $migrated);
        $this->verifySample(
            B2bProductImage::query()
                ->where('storage_disk', $targetDisk)
                ->whereNotNull('path')
                ->inRandomOrder()
                ->limit((int) $this->option('sample'))
                ->get()
                ->map(fn (B2bProductImage $item) => (string) $item->path)
                ->all(),
            $mediaStorage,
        );
    }

    private function section(string $label, int $total, int $migrated): void
    {
        $percent = $total > 0 ? round(($migrated / $total) * 100, 1) : 100.0;
        $this->line("{$label}: {$migrated}/{$total} ({$percent}%)");
    }

    /**
     * @param  list<string>  $keys
     */
    private function verifySample(array $keys, MediaStorage $mediaStorage): void
    {
        foreach ($keys as $key) {
            $exists = $mediaStorage->exists($key);
            $this->line('  '.($exists ? '[ok]' : '[missing]').' '.$key);
        }

        $this->newLine();
    }
}
