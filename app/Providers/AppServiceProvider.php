<?php

namespace App\Providers;

use App\Models\B2bCampaign;
use App\Models\B2bCustomer;
use App\Models\B2bProduct;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Discount;
use App\Models\MenuItem;
use App\Models\Product;
use App\Observers\B2bCampaignObserver;
use App\Observers\B2bCustomerObserver;
use App\Observers\B2bProductObserver;
use App\Observers\BlogPostObserver;
use App\Observers\CategoryObserver;
use App\Observers\CmsPageObserver;
use App\Observers\DiscountObserver;
use App\Observers\MenuItemObserver;
use App\Observers\ProductObserver;
use App\Listeners\LogFailedMailJob;
use App\Listeners\LogSentMail;
use App\Services\Pricing\PricingCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $livewireTmp = storage_path('app/private/livewire-tmp');

        if (! is_dir($livewireTmp)) {
            mkdir($livewireTmp, 0755, true);
        }

        B2bProduct::observe(B2bProductObserver::class);
        B2bCampaign::observe(B2bCampaignObserver::class);
        B2bCustomer::observe(B2bCustomerObserver::class);
        Product::observe(ProductObserver::class);
        Category::observe(CategoryObserver::class);
        Discount::observe(DiscountObserver::class);
        MenuItem::observe(MenuItemObserver::class);
        CmsPage::observe(CmsPageObserver::class);
        BlogPost::observe(BlogPostObserver::class);

        Event::listen(MessageSent::class, LogSentMail::class);
        Event::listen(JobFailed::class, LogFailedMailJob::class);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api-public', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('api-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('api-checkout', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('api-b2b-checkout', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-forms', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('api-analytics', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('api-register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('api-b2b', function (Request $request) {
            $userId = $request->user()?->id;

            return Limit::perMinute(300)->by(
                $userId ? 'b2b-user:'.$userId : $request->ip()
            );
        });

        RateLimiter::for('api-b2b-cart', function (Request $request) {
            $userId = $request->user()?->id;

            return Limit::perMinute(180)->by(
                $userId ? 'b2b-cart:'.$userId : $request->ip()
            );
        });

        RateLimiter::for('api-admin', function (Request $request) {
            return Limit::perMinute(300)->by($request->user()?->id ?: $request->ip());
        });
    }
}
