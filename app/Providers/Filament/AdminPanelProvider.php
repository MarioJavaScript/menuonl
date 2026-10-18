<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Tenancy\EditRestauranteProfile;
use App\Http\Middleware\ExigeDosFactores;
use App\Models\Restaurante;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->passwordReset()
            ->profile()
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()])
            ->tenant(Restaurante::class, slugAttribute: 'slug')
            ->tenantProfile(EditRestauranteProfile::class)
            ->tenantMenuItems([
                MenuItem::make()
                    ->label('Ver menú público')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (): ?string => Filament::getTenant() ? route('menu.publico', ['slug' => Filament::getTenant()->slug]) : null)
                    ->openUrlInNewTab(),
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                function (): HtmlString|string {
                    $tenant = Filament::getTenant();

                    if (! $tenant || ! $tenant->slug) {
                        return '';
                    }

                    return new HtmlString(view('filament.hooks.ver-menu-publico', [
                        'url' => route('menu.publico', ['slug' => $tenant->slug]),
                    ])->render());
                }
            )
            ->brandName('MENUONL')
            ->brandLogo(fn () => view('filament.logo'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.svg'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // Persistent so it also runs on Livewire update requests (see ExigeDosFactores).
            ->authMiddleware([
                ExigeDosFactores::class,
            ], isPersistent: true);
    }
}
