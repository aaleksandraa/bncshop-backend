<?php

namespace App\Providers\Filament;

use App\Filament\B2b\Auth\Login;
use App\Http\Controllers\Admin\B2bOrderInvoiceController;
use App\Http\Middleware\Filament\AuthenticateB2bPanel;
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
use Illuminate\Support\Facades\Route;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class B2bPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('b2b-admin')
            ->path('b2b-admin')
            ->login(Login::class)
            ->brandName('BNC B2B Admin')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->navigationGroups([
                NavigationGroup::make('Katalog'),
                NavigationGroup::make('Kupci'),
                NavigationGroup::make('Narudžbe'),
                NavigationGroup::make('Postavke'),
            ])
            ->discoverResources(in: app_path('Filament/B2b/Resources'), for: 'App\\Filament\\B2b\\Resources')
            ->discoverPages(in: app_path('Filament/B2b/Pages'), for: 'App\\Filament\\B2b\\Pages')
            ->pages([
                Pages\Dashboard::class,
                \App\Filament\B2b\Pages\B2bSettingsPage::class,
            ])
            ->widgets([
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
                AuthenticateB2bPanel::class,
            ])
            ->routes(function (): void {
                Route::get('/b2b-orders/{order}/invoice', B2bOrderInvoiceController::class)
                    ->name('b2b-orders.invoice');
            });
    }
}
