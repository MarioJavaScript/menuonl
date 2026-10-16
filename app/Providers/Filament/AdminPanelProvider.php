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

                    $url = e(route('menu.publico', ['slug' => $tenant->slug]));

                    return new HtmlString(<<<HTML
<div class="flex items-center me-3">
    <a
        href="{$url}"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-amber-950 bg-amber-500/20 hover:bg-amber-500/30 dark:text-amber-200 dark:bg-amber-500/10 dark:hover:bg-amber-500/20 border border-amber-500/30 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500"
        title="Abrir el menú digital público en una nueva pestaña"
    >
        <svg class="w-4 h-4 text-amber-700 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
        </svg>
        <span class="hidden sm:inline">Ver menú público</span>
        <span class="sm:hidden">Menú</span>
    </a>
</div>
HTML);
                }
            )
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
