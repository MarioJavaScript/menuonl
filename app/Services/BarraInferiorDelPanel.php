<?php

namespace App\Services;

use App\Filament\Pages\Apariencia;
use App\Filament\Pages\Inicio;
use App\Filament\Pages\Reservas;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\Contactos\ContactoResource;
use App\Filament\Resources\Horarios\HorarioResource;
use App\Filament\Resources\Productos\ProductoResource;
use Closure;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;

/**
 * Accesos de la barra inferior del panel en el celular (docs/integracion-dashboard.md, sección 12).
 *
 * Recorre una lista fija por prioridad y se queda con los primeros que la persona puede abrir. Cada acceso se valida con
 * el mismo permiso que su pantalla (canAccess / canViewAny), así que el rol y el plan salen de las políticas y no de
 * comparaciones de plan en la vista. "Más" no está aquí: abre el menú lateral de Filament, que ya es el menú completo.
 */
class BarraInferiorDelPanel
{
    public const int MAXIMO = 4;

    /**
     * @return list<array{clave: string, etiqueta: string, nombre: string, url: string, icono: Heroicon, activa: bool, insignia: int}>
     */
    public function accesos(): array
    {
        $accesos = [];

        foreach ($this->candidatos() as $clave => $candidato) {
            if (count($accesos) === self::MAXIMO) {
                break;
            }

            if (! $candidato['visible']()) {
                continue;
            }

            $etiqueta = __($candidato['etiqueta']);
            $insignia = isset($candidato['insignia']) ? max(0, $candidato['insignia']()) : 0;
            $nombre = $insignia > 0 && isset($candidato['nombre_con_insignia'])
                ? __($candidato['nombre_con_insignia'], ['n' => $insignia])
                : __($candidato['nombre']);

            $accesos[] = [
                'clave' => $clave,
                'etiqueta' => $etiqueta,
                'nombre' => $nombre,
                'url' => $candidato['url'](),
                'icono' => $candidato['icono'],
                'activa' => $candidato['activa'](),
                'insignia' => $insignia,
            ];
        }

        return $accesos;
    }

    /**
     * En orden de prioridad.
     *
     * "insignia" (opcional) es la cuenta que se pinta sobre el ícono; con ella, el nombre accesible pasa a "nombre_con_insignia".
     *
     * @return array<string, array{etiqueta: string, nombre: string, icono: Heroicon, visible: Closure(): bool, url: Closure(): string, activa: Closure(): bool, insignia?: Closure(): int, nombre_con_insignia?: string}>
     */
    private function candidatos(): array
    {
        $ruta = fn (string $nombre): Closure => fn (): bool => request()->routeIs($nombre);

        return [
            'inicio' => [
                'etiqueta' => 'panel.barra.inicio', 'nombre' => 'panel.barra.inicio', 'icono' => Heroicon::OutlinedHome,
                'visible' => fn (): bool => true,
                'url' => fn (): string => Inicio::getUrl(),
                'activa' => $ruta(Inicio::getRouteName()),
            ],
            'platillos' => [
                'etiqueta' => 'panel.barra.platillos', 'nombre' => 'panel.barra.platillos', 'icono' => Heroicon::OutlinedCake,
                'visible' => fn (): bool => ProductoResource::canViewAny(),
                'url' => fn (): string => ProductoResource::getUrl('index'),
                'activa' => $ruta(ProductoResource::getRouteBaseName().'.*'),
            ],
            'reservas' => [
                'etiqueta' => 'panel.barra.reservas', 'nombre' => 'panel.barra.reservas', 'icono' => Heroicon::OutlinedCalendarDays,
                'visible' => fn (): bool => Reservas::canAccess(),
                'url' => fn (): string => Reservas::getUrl(),
                'activa' => $ruta(Reservas::getRouteName()),
                'insignia' => fn (): int => (int) Reservas::getNavigationBadge(),
                'nombre_con_insignia' => 'panel.barra.reservas_por_confirmar',
            ],
            'apariencia' => [
                'etiqueta' => 'panel.barra.apariencia', 'nombre' => 'panel.barra.apariencia', 'icono' => Heroicon::OutlinedPaintBrush,
                'visible' => fn (): bool => Apariencia::canAccess(),
                'url' => fn (): string => Apariencia::getUrl(),
                'activa' => $ruta(Apariencia::getRouteName()),
            ],
            'categorias' => [
                'etiqueta' => 'panel.barra.categorias', 'nombre' => 'panel.barra.categorias', 'icono' => Heroicon::OutlinedRectangleStack,
                'visible' => fn (): bool => CategoriaResource::canViewAny(),
                'url' => fn (): string => CategoriaResource::getUrl('index'),
                'activa' => $ruta(CategoriaResource::getRouteBaseName().'.*'),
            ],
            'horario' => [
                'etiqueta' => 'panel.barra.horario', 'nombre' => 'panel.barra.horario', 'icono' => Heroicon::OutlinedClock,
                'visible' => fn (): bool => HorarioResource::canViewAny(),
                'url' => fn (): string => HorarioResource::getUrl('index'),
                'activa' => $ruta(HorarioResource::getRouteBaseName().'.*'),
            ],
            'contactos' => [
                'etiqueta' => 'panel.barra.contactos', 'nombre' => 'panel.barra.contactos', 'icono' => Heroicon::OutlinedPhone,
                'visible' => fn (): bool => ContactoResource::canViewAny(),
                'url' => fn (): string => ContactoResource::getUrl('index'),
                'activa' => $ruta(ContactoResource::getRouteBaseName().'.*'),
            ],
        ];
    }
}
