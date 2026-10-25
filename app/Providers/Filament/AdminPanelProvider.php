<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Inicio;
use App\Filament\Pages\Tenancy\EditRestauranteProfile;
use App\Filament\Widgets\ResumenRestauranteWidget;
use App\Http\Middleware\AutenticaPanelAdmin;
use App\Http\Middleware\ExigeDosFactores;
use App\Models\Restaurante;
use App\Services\PermisosPanel;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
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
                        'sitioUrl' => $tenant->tieneSitio() ? route('sitio.publico', ['slug' => $tenant->slug]) : null,
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
                'primary' => Color::Amber,
                'gray' => Color::Zinc,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Inicio::class,
            ])
            ->navigationGroups([
                NavigationGroup::make(fn (): string => __('panel.grupos.menu')),
                NavigationGroup::make(fn (): string => __('panel.grupos.apariencia')),
                NavigationGroup::make(fn (): string => __('panel.grupos.informacion')),
                NavigationGroup::make(fn (): string => __('panel.grupos.pedidos')),
                NavigationGroup::make(fn (): string => __('panel.grupos.qr')),
                NavigationGroup::make('Administración'),
                NavigationGroup::make('Plataforma'),
            ])
            ->navigationItems([
                NavigationItem::make(fn (): string => __('panel.enlaces.servicio'))
                    ->group(fn (): string => __('panel.grupos.pedidos'))
                    ->icon(Heroicon::OutlinedBolt)
                    ->sort(20)
                    ->url(fn (): string => route('panel'))
                    ->visible(fn (): bool => self::puedeUsarElPanelDeServicio()),
                NavigationItem::make(fn (): string => __('panel.datos.nav'))
                    ->group(fn (): string => __('panel.grupos.informacion'))
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->sort(10)
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
                AutenticaPanelAdmin::class,
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
     * "Panel de servicio" solo se ofrece a quien tiene algún módulo en el restaurante activo (el plan es un techo).
     */
    private static function puedeUsarElPanelDeServicio(): bool
    {
        $tenant = Filament::getTenant();
        $usuario = auth()->user();

        return $tenant instanceof Restaurante
            && $usuario !== null
            && PermisosPanel::modulosDe($usuario, (int) $tenant->getKey()) !== [];
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
