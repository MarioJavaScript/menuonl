<?php

namespace App\Services;

use App\Filament\Resources\Restaurantes\RestauranteResource;
use App\Models\Restaurante;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

/**
 * Banner "Estás viendo como superadmin" dentro del panel de un restaurante (docs/integracion-dashboard.md, PR 4).
 *
 * Solo lo ve un superadmin que está dentro de un restaurante que no es suyo. Para cualquier otra persona la vista ni se
 * renderiza (no es CSS que lo esconde). Las pantallas de plataforma, que Filament también cuelga de /admin/{slug}, se
 * reconocen por su grupo de navegación: una pantalla nueva del grupo "Plataforma" lo oculta sin tocar este código.
 */
class BannerDeSuperadmin
{
    public const string GRUPO_PLATAFORMA = 'Plataforma';

    public function render(): string
    {
        $usuario = auth()->user();
        $restaurante = Filament::getTenant();

        if (! $usuario instanceof User || ! $restaurante instanceof Restaurante) {
            return '';
        }

        if (! self::debeMostrarse($usuario, $restaurante, self::claseDeLaPaginaActual())) {
            return '';
        }

        return view('filament.hooks.banner-superadmin', [
            'restaurante' => $restaurante,
            'urlDeVuelta' => RestauranteResource::getUrl('index'),
        ])->render();
    }

    /**
     * Superadmin, dentro de un restaurante del que no es miembro activo y fuera de las pantallas de plataforma.
     *
     * @param  class-string|null  $clasePagina  Página de Filament que se está mostrando, si se sabe.
     */
    public static function debeMostrarse(User $usuario, Restaurante $restaurante, ?string $clasePagina): bool
    {
        return $usuario->isSuperadmin()
            && $usuario->rolEn($restaurante) === null
            && ! self::esDePlataforma($clasePagina);
    }

    /**
     * Una página (o la página de un recurso) es de plataforma cuando su grupo de navegación es "Plataforma".
     */
    public static function esDePlataforma(?string $clasePagina): bool
    {
        if ($clasePagina === null || ! class_exists($clasePagina)) {
            return false;
        }

        $destino = method_exists($clasePagina, 'getResource') ? $clasePagina::getResource() : $clasePagina;

        if (! method_exists($destino, 'getNavigationGroup')) {
            return false;
        }

        $grupo = $destino::getNavigationGroup();

        if ($grupo instanceof \BackedEnum) {
            $grupo = $grupo->value;
        } elseif ($grupo instanceof \UnitEnum) {
            $grupo = $grupo->name;
        }

        return $grupo === self::GRUPO_PLATAFORMA;
    }

    /**
     * Clase de la página de Filament de la petición actual (las páginas de Filament son su propio controlador).
     *
     * @return class-string|null
     */
    public static function claseDeLaPaginaActual(): ?string
    {
        $clase = explode('@', (string) Route::current()?->getActionName())[0];

        return $clase !== '' && class_exists($clase) ? $clase : null;
    }
}
