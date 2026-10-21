<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Inicio;
use App\Filament\Pages\Tenancy\EditRestauranteProfile;
use App\Filament\Widgets\ResumenRestauranteWidget;
use App\Http\Middleware\ExigeDosFactores;
use App\Models\Restaurante;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    private const TEMA = 'resources/css/filament/admin/theme.css';

    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->passwordReset()
            ->profile()
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()])
            ->tenant(Restaurante::class, slugAttribute: 'slug')
            ->tenantProfile(EditRestauranteProfile::class)
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
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Filament::getTenant() ? view('filament.hooks.barra-inferior')->render() : '',
            )
            ->brandName('MENUONL')
            ->brandLogo(fn () => view('filament.logo'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.svg'))
            ->colors([
                'primary' => Color::hex('#a8541c'),
                'gray' => Color::Zinc,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Inicio::class,
            ])
            ->navigationItems([
                NavigationItem::make('Panel de servicio')
                    ->group('Restaurante')
                    ->icon(Heroicon::OutlinedBolt)
                    ->sort(5)
                    ->url(fn (): string => route('panel')),
                NavigationItem::make('Mi restaurante')
                    ->group('Restaurante')
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->sort(60)
                    ->url(fn (): string => EditRestauranteProfile::getUrl())
                    ->isActiveWhen(fn (): bool => request()->routeIs(EditRestauranteProfile::getRouteName()))
                    ->visible(fn (): bool => Filament::getTenant() !== null && EditRestauranteProfile::canView(Filament::getTenant())),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                ResumenRestauranteWidget::class,
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

        if (self::temaCompilado()) {
            $panel->viteTheme(self::TEMA);
        }

        return $panel;
    }

    /**
     * The theme is only registered when Vite can serve it (dev server or a build that includes it), so the
     * panel still renders in CI and tests, where assets are not compiled.
     */
    private static function temaCompilado(): bool
    {
        if (is_file(public_path('hot'))) {
            return true;
        }

        $manifiesto = public_path('build/manifest.json');

        return is_file($manifiesto) && str_contains((string) file_get_contents($manifiesto), self::TEMA);
    }
}
