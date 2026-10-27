<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Reservas;
use App\Models\Restaurante;
use App\Services\ReservasDelDia;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * "Hoy en tu restaurante" (docs/integracion-dashboard.md, sección 20): reservas de hoy, comensales esperados, por
 * confirmar y la próxima reserva. Solo lee, con ReservasDelDia (dos consultas), y lleva a la página Reservas.
 *
 * Lo ve quien puede abrir Reservas (módulo reservas por rol y plan, más viewAny). Sin reservas que mostrar, el bloque
 * solo aparece si el restaurante tiene las reservas disponibles; en ese caso se vuelve a pintar cada 60 segundos con la
 * pestaña visible. Ese sondeo comparte con Reservas el aviso de red y de sesión caducada (partials/sondeo-con-sesion).
 */
class OperacionDelDiaWidget extends Widget
{
    protected static ?int $sort = -20;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.operacion-del-dia-widget';

    public static function canView(): bool
    {
        return Reservas::canAccess();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $restaurante = Filament::getTenant();

        if (! static::canView() || ! $restaurante instanceof Restaurante) {
            return ['mostrar' => false];
        }

        $dia = app(ReservasDelDia::class)->operacionDelDia($restaurante);
        $hayReservas = $dia['reservas_hoy'] > 0 || $dia['por_confirmar'] > 0;

        return [
            'mostrar' => $hayReservas || $restaurante->reservasDisponibles(),
            'dia' => $dia,
            'reservasUrl' => Reservas::getUrl(),
        ];
    }
}
