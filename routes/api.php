<?php

use App\Http\Controllers\Api\V1\AdminSyncController;
use App\Http\Controllers\Api\V1\AnalyticsEventController;
use App\Http\Controllers\Api\V1\B2b\B2bAccessRequestController;
use App\Http\Controllers\Api\V1\B2b\B2bAuthController;
use App\Http\Controllers\Api\V1\B2b\B2bCartController;
use App\Http\Controllers\Api\V1\B2b\B2bCatalogController;
use App\Http\Controllers\Api\V1\B2b\B2bOrderController;
use App\Http\Controllers\Api\V1\B2b\B2bProfileController;
use App\Http\Controllers\Api\V1\BlogPostController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\CustomerAuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HomepageController;
use App\Http\Controllers\Api\V1\InstallmentInquiryController;
use App\Http\Controllers\Api\V1\InstallmentSettingsController;
use App\Http\Controllers\Api\V1\LayoutController;
use App\Http\Controllers\Api\V1\LoyaltyController;
use App\Http\Controllers\Api\V1\ManufacturerController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\PartnerProductExportController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\RedirectController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SellerAuthController;
use App\Http\Controllers\Api\V1\SellerOrderController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SitemapController;
use App\Http\Middleware\ResolveCartSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('throttle:api-public')->group(function (): void {
        Route::get('/health', HealthController::class);

        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/nav', [CategoryController::class, 'nav']);
        Route::get('/categories/{slug}', [CategoryController::class, 'show'])
            ->where('slug', '.+');

        Route::get('/layout/shell', [LayoutController::class, 'shell']);

        Route::get('/products/category-options', [ProductController::class, 'categoryOptions']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{slug}', [ProductController::class, 'show']);

        Route::get('/manufacturers', [ManufacturerController::class, 'index']);
        Route::get('/manufacturers/{slug}', [ManufacturerController::class, 'show']);

        Route::get('/search', [SearchController::class, 'search']);
        Route::get('/filters/{categorySlug}', [SearchController::class, 'filters'])
            ->where('categorySlug', '.+');

        Route::get('/sitemap', SitemapController::class);
        Route::get('/redirects', RedirectController::class);
        Route::get('/menus/{slug}', [MenuController::class, 'show']);
        Route::get('/pages/{slug}', [PageController::class, 'show']);
        Route::get('/blog/posts', [BlogPostController::class, 'index']);
        Route::get('/blog/posts/{slug}', [BlogPostController::class, 'show']);
        Route::get('/settings/public', [SettingsController::class, 'publicSettings']);
        Route::get('/homepage/weekly-offer', [HomepageController::class, 'weeklyOffer']);
        Route::get('/loyalty/settings', [LoyaltyController::class, 'settings']);

        Route::post('/analytics/events', [AnalyticsEventController::class, 'store'])
            ->middleware('throttle:api-analytics');
        Route::post('/installment-inquiries', [InstallmentInquiryController::class, 'store'])
            ->middleware('throttle:api-forms');
        Route::post('/b2b/access-request', [B2bAccessRequestController::class, 'store'])
            ->middleware('throttle:api-forms');
        Route::get('/installments/settings', [InstallmentSettingsController::class, 'show']);
    });

    Route::middleware(['auth.optional', ResolveCartSession::class, 'throttle:api-public'])->group(function (): void {
        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart/items', [CartController::class, 'store']);
        Route::patch('/cart/items/{id}', [CartController::class, 'update']);
        Route::delete('/cart/items/{id}', [CartController::class, 'destroy']);
        Route::post('/cart/coupon', [CartController::class, 'applyCoupon']);
        Route::delete('/cart/coupon', [CartController::class, 'removeCoupon']);
        Route::post('/cart/validate-prices', [CartController::class, 'validatePrices']);
        Route::post('/cart/confirm-prices', [CartController::class, 'confirmPrices']);
    });

    Route::middleware(['auth:sanctum', ResolveCartSession::class, 'throttle:api-public'])->group(function (): void {
        Route::post('/cart/loyalty-reward', [CartController::class, 'applyLoyaltyReward']);
        Route::delete('/cart/loyalty-reward', [CartController::class, 'removeLoyaltyReward']);
    });

    Route::middleware(['auth.optional', ResolveCartSession::class, 'throttle:api-checkout'])->group(function (): void {
        Route::post('/checkout/shipping-quote', [CheckoutController::class, 'shippingQuote']);
        Route::post('/checkout', [CheckoutController::class, 'store']);
    });

    Route::middleware('throttle:api-public')->group(function (): void {
        Route::get('/orders/track/{token}', [OrderController::class, 'track']);
        Route::post('/orders/track', [OrderController::class, 'trackWithVerification']);
    });

    Route::middleware('throttle:api-login')->group(function (): void {
        Route::post('/customer/login', [CustomerAuthController::class, 'login']);
        Route::post('/seller/login', [SellerAuthController::class, 'login']);
        Route::post('/b2b/auth/login', [B2bAuthController::class, 'login']);
        Route::post('/b2b/auth/set-password', [B2bAuthController::class, 'setPassword']);
        Route::post('/b2b/auth/forgot-password', [B2bAuthController::class, 'forgotPassword']);
        Route::post('/b2b/auth/reset-password', [B2bAuthController::class, 'resetPassword']);
    });

    Route::middleware('throttle:api-register')->group(function (): void {
        Route::post('/customer/register', [CustomerAuthController::class, 'register']);
    });

    Route::middleware(['auth:sanctum', 'throttle:api-public'])->group(function (): void {
        Route::post('/customer/logout', [CustomerAuthController::class, 'logout']);
        Route::get('/customer/me', [CustomerAuthController::class, 'me']);
        Route::get('/customer/orders', [CustomerAuthController::class, 'orders']);
        Route::get('/customer/loyalty', [LoyaltyController::class, 'account']);
        Route::get('/customer/pending-loyalty', [CustomerAuthController::class, 'pendingLoyaltyPoints']);
        Route::put('/customer/profile', [CustomerAuthController::class, 'updateProfile']);
    });

    Route::middleware(['auth:sanctum', 'b2b.customer'])->prefix('b2b')->group(function (): void {
        Route::middleware('throttle:api-b2b')->group(function (): void {
            Route::post('/auth/logout', [B2bAuthController::class, 'logout']);
            Route::get('/auth/me', [B2bAuthController::class, 'me']);
            Route::get('/profile', [B2bProfileController::class, 'show']);
            Route::patch('/profile', [B2bProfileController::class, 'update']);
            Route::get('/categories', [B2bCatalogController::class, 'categories']);
            Route::get('/products', [B2bCatalogController::class, 'products']);
            Route::get('/products/{slug}', [B2bCatalogController::class, 'showProduct']);
            Route::get('/cart', [B2bCartController::class, 'index']);
            Route::get('/shipping-quote', [B2bCartController::class, 'shippingQuote']);
            Route::get('/orders', [B2bOrderController::class, 'index']);
            Route::get('/orders/{id}', [B2bOrderController::class, 'show']);
            Route::get('/orders/{id}/invoice', [B2bOrderController::class, 'invoice']);
        });

        Route::middleware('throttle:api-b2b-cart')->group(function (): void {
            Route::post('/cart/items', [B2bCartController::class, 'store']);
            Route::patch('/cart/items/{id}', [B2bCartController::class, 'update']);
            Route::delete('/cart/items/{id}', [B2bCartController::class, 'destroy']);
        });
    });

    Route::post('/b2b/checkout', [B2bCartController::class, 'checkout'])
        ->middleware(['auth:sanctum', 'b2b.customer', 'throttle:api-b2b-checkout']);

    Route::middleware(['auth:sanctum', 'seller', 'throttle:api-public'])->prefix('seller')->group(function (): void {
        Route::post('/logout', [SellerAuthController::class, 'logout']);
        Route::get('/me', [SellerAuthController::class, 'me']);
        Route::get('/orders', [SellerOrderController::class, 'index']);
        Route::get('/orders/{id}', [SellerOrderController::class, 'show']);
        Route::patch('/orders/{id}/status', [SellerOrderController::class, 'updateStatus']);
    });

    Route::middleware(['auth:sanctum', 'throttle:api-admin', 'permission:manage_sync|view_sync'])->prefix('admin/sync')->group(function (): void {
        Route::get('/status', [AdminSyncController::class, 'status']);
        Route::patch('/sources/{id}', [AdminSyncController::class, 'updateSource'])->middleware('permission:manage_sync');
        Route::post('/run', [AdminSyncController::class, 'run'])->middleware('permission:manage_sync');
        Route::get('/jobs', [AdminSyncController::class, 'jobs']);
        Route::get('/jobs/{id}', [AdminSyncController::class, 'showJob']);
        Route::post('/test-connection', [AdminSyncController::class, 'testConnection'])->middleware('permission:manage_sync');
    });

    Route::middleware(['partner.export.secure', 'partner.export', 'partner.export.headers'])->prefix('partner')->group(function (): void {
        Route::get('/products', [PartnerProductExportController::class, 'index']);
    });
});
