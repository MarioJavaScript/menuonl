<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Restaurante;
use Illuminate\Support\Collection;

/**
 * Qué platillos recomendados muestra hoy el sitio de un restaurante y cuál sale como principal, con las mismas reglas
 * que SitioPublicoDatos y las plantillas (solo lectura: no cambia nada del sitio público).
 */
class RecomendadosDelSitio
{
    /**
     * Plantillas que destacan un platillo principal en su portada.
     *
     * @var list<string>
     */
    private const array PLANTILLAS_CON_PRINCIPAL = ['e', 'f', 'g'];

    /**
     * @return array{marcados: int, maximo: int, en_sitio: Collection<int, Producto>, principal: ?Producto, plantilla_con_principal: bool, sin_marcar_usa_los_primeros: bool}
     */
    public function para(Restaurante $restaurante): array
    {
        $marcados = $restaurante->productos()->destacado()->count();
        $sinMarcar = $marcados === 0;

        $enSitio = $restaurante->productos()
            ->visible()
            ->whereHas('categoria', fn ($q) => $q->where('visible', true))
            ->when(! $sinMarcar, fn ($q) => $q->destacado())
            ->when(! $restaurante->mostrar_agotados, fn ($q) => $q->disponible())
            ->with(['traducciones', 'etiquetas'])
            ->orderBy('orden')
            ->orderBy('id')
            ->limit(SitioPublicoDatos::MAX_DESTACADOS)
            ->get();

        $conPrincipal = in_array($restaurante->sitio_plantilla, self::PLANTILLAS_CON_PRINCIPAL, true);

        return [
            'marcados' => $marcados,
            'maximo' => SitioPublicoDatos::MAX_DESTACADOS,
            'en_sitio' => $enSitio,
            'principal' => $conPrincipal ? $this->principal($enSitio) : null,
            'plantilla_con_principal' => $conPrincipal,
            'sin_marcar_usa_los_primeros' => $sinMarcar,
        ];
    }

    /**
     * La portada de las plantillas e, f y g toma el platillo con la etiqueta "Nuevo"; si no hay, el primero con foto
     * y, si tampoco, el primero. Los agotados no se destacan.
     *
     * @param  Collection<int, Producto>  $enSitio
     */
    private function principal(Collection $enSitio): ?Producto
    {
        $disponibles = $enSitio->filter(fn (Producto $producto): bool => $producto->disponible)->values();

        return $disponibles->first(fn (Producto $producto): bool => $producto->etiquetas->contains('codigo', 'nuevo'))
            ?? $disponibles->first(fn (Producto $producto): bool => filled($producto->imagen))
            ?? $disponibles->first();
    }
}
