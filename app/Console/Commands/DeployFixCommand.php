<?php

namespace App\Console\Commands;

use App\Services\Catalog\ProductReadCache;
use App\Support\PublicStorageUrl;
use App\Support\StorefrontConfig;
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

        $assetUrl = rtrim((string) config('bnc.legacy_storage_url'), '/');
        $this->line('LEGACY_STORAGE_URL / ASSET_URL: '.($assetUrl !== '' ? $assetUrl : '(not set — uses production fallback)'));

        if (
            $env === 'production'
            && str_ends_with(parse_url($appUrl, PHP_URL_HOST) ?: '', 'api.bnc.ba')
            && ($assetUrl === '' || ! str_contains($assetUrl, 'bncshop'))
        ) {
            $this->error('APP_URL is api.bnc.ba but legacy storage is served from api.bncshop.ba.');
            $this->line('Set LEGACY_STORAGE_URL=https://api.bncshop.ba (or ASSET_URL=...) in .env, then run: php artisan bnc:deploy-fix --apply');
            $issues++;
        } elseif ($assetUrl !== '') {
            $this->info('Legacy storage URL looks OK.');
        }

        if ($env === 'production' && str_contains($appUrl, 'api.bncshop.ba')) {
            $this->error('APP_URL is api.bncshop.ba but production uses api.bnc.ba — Filament admin tables will fail CORS.');
            $this->line('Set APP_URL=https://api.bnc.ba in .env, unset ASSET_URL (use LEGACY_STORAGE_URL for old /storage only), then run: php artisan bnc:deploy-fix --apply');
            $issues++;
        }

        $sample = PublicStorageUrl::absoluteFromResolved(
            'https://api.bnc.ba/storage/products/deploy-check.jpg',
        );
        $this->line('Legacy storage URL rewrite: '.$sample);

        $sellerSample = PublicStorageUrl::absoluteFromResolved(
            '/storage/products/demo/seller-00000000-0000-4000-8000-000000000000.jpg',
        );
        $this->line('Seller storage URL rewrite: '.$sellerSample);

        if (
            $env === 'production'
            && str_contains($sample, 'api.bnc.ba/storage')
        ) {
            $this->error('Legacy storage URL rewrite still points to api.bnc.ba — run git pull, then config:cache.');
            $issues++;
        }

        if (
            $env === 'production'
            && ! str_contains($sellerSample, 'api.bnc.ba/storage')
        ) {
            $this->error('Seller storage URL rewrite must point to APP_URL (api.bnc.ba).');
            $issues++;
        }

        $frontendUrl = StorefrontConfig::frontendUrl();
        $this->line('FRONTEND_URL: '.($frontendUrl ?? '(missing)'));

        if ($env === 'production' && $frontendUrl === null) {
            $this->error('FRONTEND_URL missing — cart CORS/Sanctum fallbacks need https://bncshop.ba');
            $issues++;
        }

        $sessionDomain = config('session.domain');
        $this->line('SESSION domain: '.($sessionDomain ?: '(host-only — cart CSRF may fail)'));

        if ($env === 'production' && ! $sessionDomain) {
            $this->error('SESSION domain is host-only. Set SESSION_DOMAIN=.bncshop.ba or APP_URL=https://api.bncshop.ba');
            $issues++;
        } else {
            $this->info('SESSION domain looks OK.');
        }

        $corsOrigins = config('cors.allowed_origins');
        $this->line('CORS origins: '.implode(', ', $corsOrigins));

        if ($env === 'production' && StorefrontConfig::containsOnlyLocalhostOrigins($corsOrigins)) {
            $this->error('CORS still allows only localhost — set FRONTEND_URL=https://bncshop.ba or CORS_ALLOWED_ORIGINS');
            $issues++;
        } else {
            $this->info('CORS origins look OK.');
        }

        $recommended = StorefrontConfig::productionEnvRecommendations();

        if ($recommended !== []) {
            $this->newLine();
            $this->warn('Recommended .env changes:');
            foreach ($recommended as $line) {
                $this->line('  '.$line);
            }
        }

        $statefulDomains = config('sanctum.stateful');
        $this->line('Sanctum stateful: '.implode(', ', $statefulDomains));

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
        Artisan::call('view:clear');
        $this->line('  config:clear + cache:clear + view:clear');

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
