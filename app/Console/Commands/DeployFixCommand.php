<?php

namespace App\Console\Commands;

use App\Services\Catalog\ProductReadCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class DeployFixCommand extends Command
{
    protected $signature = 'bnc:deploy-fix
                            {--apply : Clear caches and rebuild config}
                            {--flush-all : Flush all tagged product/layout/settings caches}';

    protected $description = 'Validate production env and clear stale API caches (localhost image URLs, old listings)';

    public function handle(ProductReadCache $productReadCache): int
    {
        $this->info('BNC Shop production deploy fix');
        $this->newLine();

        $issues = $this->validateEnvironment($productReadCache);

        if ($issues > 0 && ! $this->option('apply')) {
            $this->warn('Run with --apply to clear caches after fixing .env values.');
        }

        if ($this->option('apply')) {
            $this->applyFixes($productReadCache);
        } else {
            $this->line('Dry run only. Use --apply to execute cache/config fixes.');
        }

        $this->newLine();
        $this->comment('Frontend (on bncshop.ba server):');
        $this->line('  npm run deploy:production');
        $this->line('  npm run verify:live');
        $this->line('  Plesk -> Node.js -> Restart App');
        $this->newLine();
        $this->comment('Plesk document root MUST be /httpdocs, NOT /httpdocs/.next/static');

        return $issues > 0 && ! $this->option('apply') ? self::FAILURE : self::SUCCESS;
    }

    private function validateEnvironment(ProductReadCache $productReadCache): int
    {
        $issues = 0;
        $appUrl = rtrim((string) config('app.url'), '/');
        $env = app()->environment();

        $this->line("Environment: {$env}");
        $this->line("APP_URL: {$appUrl}");

        if ($env === 'production' && str_contains($appUrl, 'localhost')) {
            $this->error('APP_URL contains localhost — API will return broken image URLs.');
            $this->line('Set APP_URL=https://api.bncshop.ba in .env, then run: php artisan bnc:deploy-fix --apply');
            $issues++;
        } elseif ($appUrl === '') {
            $this->error('APP_URL is empty.');
            $issues++;
        } else {
            $this->info('APP_URL looks OK.');
        }

        if (config('cache.default') !== 'redis') {
            $this->warn('CACHE_STORE is not redis — tagged cache flush may be incomplete.');
        } else {
            $this->info('Cache store: redis');
        }

        if (! $productReadCache->supportsTags()) {
            $this->warn('Cache store does not support tags — run CACHE_STORE=redis for full flush.');
        }

        return $issues;
    }

    private function applyFixes(ProductReadCache $productReadCache): void
    {
        $this->info('Applying production fixes...');

        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        $this->line('  config:clear + cache:clear');

        Artisan::call('config:cache');
        $this->line('  config:cache');

        if ($this->option('flush-all')) {
            $productReadCache->flushProducts();
            $productReadCache->flushCategories();
            $productReadCache->flushLayout();
            $productReadCache->flushSettings();
            $productReadCache->flushMenus();
            $productReadCache->flushCms();
            $productReadCache->flushBlog();
            $productReadCache->flushManufacturers();
            $this->line('  flushed all tagged API caches');
        } else {
            $productReadCache->flushListAndFilters();
            $productReadCache->flushLayout();
            $this->line('  flushed product lists, filters, and layout cache');
        }

        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags(['products:list'])->flush();
        }

        $this->info('Backend deploy fix applied.');
        $this->line('Rebuild frontend next: npm run deploy:production');
    }
}
