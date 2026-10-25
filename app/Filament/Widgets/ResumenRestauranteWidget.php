<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Productos\ProductoResource;
use App\Models\Restaurante;
use App\Services\FormatoFecha;
use App\Services\ProgresoDeConfiguracion;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Panel home (DISEÑO.md): the state of the menu and what needs attention, not a copy of the navigation.
 * It only reads; every pending item links to the screen where it is solved, and only shows to people who can open it.
 */
class ResumenRestauranteWidget extends Widget
{
    protected static ?int $sort = -10;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.resumen-restaurante-widget';

    public static function canView(): bool
    {
        return Filament::getTenant() instanceof Restaurante;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $restaurante = Filament::getTenant();

        if (! $restaurante instanceof Restaurante) {
            return [
                'restaurante' => null,
            ];
        }

        $restaurante->loadMissing('horarios');

        $diaHoy = (int) now($restaurante->zona_horaria)->dayOfWeek;
        $turnosHoy = $restaurante->horarios
            ->where('dia_semana', $diaHoy)
            ->where('cerrado', false)
            ->sortBy('abre')
            ->map(fn ($turno): string => FormatoFecha::rango($turno->abre, $turno->cierra))
            ->values()
            ->all();

        $usuario = Auth::user();

        return [
            'restaurante' => $restaurante,
            'estaPublicado' => $restaurante->isActivo(),
            'estaAbierto' => $restaurante->estaAbiertoAhora(),
            'turnosHoy' => $turnosHoy,
            'agregarPlatilloUrl' => ProductoResource::canCreate() ? ProductoResource::getUrl('create') : null,
            'sitioUrl' => $restaurante->tieneSitio() ? route('sitio.publico', ['slug' => $restaurante->slug]) : null,
            'menuUrl' => route('menu.publico', ['slug' => $restaurante->slug]),
            'progreso' => $usuario ? app(ProgresoDeConfiguracion::class)->para($restaurante, $usuario) : null,
        ];
    }
}
