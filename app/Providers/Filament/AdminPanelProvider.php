<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Auth\ResetPassword;
use App\Filament\Pages\PartnerExportSettingsPage;
use App\Filament\Widgets\SalesChartWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\Facades\Route;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            ->brandName('BNC Admin')
            ->brandLogo(asset('bnc-logo.png'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('bnc-logo.png'))
            ->colors([
                'primary' => Color::hex('#e30613'),
            ])
            ->darkMode()
            ->renderHook('panels::head.end', fn () => view('filament.admin.head'))
            ->navigationGroups([
                NavigationGroup::make('Dashboard'),
                NavigationGroup::make('Katalog'),
                NavigationGroup::make('Prodaja'),
                NavigationGroup::make('Marketing'),
                NavigationGroup::make('OLX'),
                NavigationGroup::make('Integracije'),
                NavigationGroup::make('Analitika'),
                NavigationGroup::make('Sistem'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
                PartnerExportSettingsPage::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                StatsOverviewWidget::class,
                SalesChartWidget::class,
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->routes(function (): void {
                Route::get('/loyalty-cards/{loyaltyCard}/print', \App\Http\Controllers\Admin\LoyaltyCardPrintController::class)
                    ->name('loyalty-cards.print');

                Route::get('/orders/export-excel-batch', [\App\Http\Controllers\Admin\OrderExportController::class, 'excelBatch'])
                    ->name('orders.export-excel-batch');
                Route::get('/orders/export-pdf-batch', [\App\Http\Controllers\Admin\OrderExportController::class, 'pdfBatch'])
                    ->name('orders.export-pdf-batch');
                Route::get('/orders/export-packing-pdf-batch', [\App\Http\Controllers\Admin\OrderExportController::class, 'packingPdfBatch'])
                    ->name('orders.export-packing-pdf-batch');
                Route::get('/orders/packing-print-batch', [\App\Http\Controllers\Admin\OrderPrintController::class, 'packingBatch'])
                    ->name('orders.packing-print-batch');
                Route::get('/orders/print-batch', [\App\Http\Controllers\Admin\OrderPrintController::class, 'batch'])
                    ->name('orders.print-batch');

                Route::get('/orders/{order}/export-excel', [\App\Http\Controllers\Admin\OrderExportController::class, 'excel'])
                    ->name('orders.export-excel');
                Route::get('/orders/{order}/export-pdf', [\App\Http\Controllers\Admin\OrderExportController::class, 'pdf'])
                    ->name('orders.export-pdf');
                Route::get('/orders/{order}/export-packing-pdf', [\App\Http\Controllers\Admin\OrderExportController::class, 'packingPdf'])
                    ->name('orders.export-packing-pdf');
                Route::get('/orders/{order}/packing-print', [\App\Http\Controllers\Admin\OrderPrintController::class, 'packing'])
                    ->name('orders.packing-print');
                Route::get('/orders/{order}/print', [\App\Http\Controllers\Admin\OrderPrintController::class, 'show'])
                    ->name('orders.print');
            });
    }
}
