<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\PedidosYReservas;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\CodigosQr\CodigoQrResource;
use App\Filament\Resources\Horarios\HorarioResource;
use App\Filament\Resources\Productos\ProductoResource;
use App\Models\Restaurante;
use App\Services\FormatoFecha;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Panel home (DISEÑO.md): the state of the menu and what needs attention, not a copy of the navigation.
 * It only reads; every pending item links to the screen where it is solved.
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

        $puedeEditarRestaurante = auth()->user()?->can('update', $restaurante) ?? false;
        $diaHoy = (int) now($restaurante->zona_horaria)->dayOfWeek;
        $turnosHoy = $restaurante->horarios
            ->where('dia_semana', $diaHoy)
            ->where('cerrado', false)
            ->sortBy('abre')
            ->map(fn ($turno): string => FormatoFecha::rango($turno->abre, $turno->cierra))
            ->values()
            ->all();

        return [
            'restaurante' => $restaurante,
            'estaPublicado' => $restaurante->isActivo(),
            'estaAbierto' => $restaurante->estaAbiertoAhora(),
            'turnosHoy' => $turnosHoy,
            'agregarPlatilloUrl' => ProductoResource::canCreate() ? ProductoResource::getUrl('create') : null,
            'pendientes' => $this->pendientes($restaurante, $puedeEditarRestaurante),
        ];
    }

    /**
     * @return list<array{mensaje: string, accion: string, url: ?string, importante: bool}>
     */
    private function pendientes(Restaurante $restaurante, bool $puedeEditarRestaurante): array
    {
        $pendientes = [];

        if (! $restaurante->categorias()->exists()) {
            $pendientes[] = [
                'mensaje' => 'Crea tu primera categoría, por ejemplo "Tacos" o "Bebidas".',
                'accion' => 'Crear categoría',
                'url' => CategoriaResource::getUrl('create'),
                'importante' => true,
            ];
        } elseif (! $restaurante->productos()->exists()) {
            $pendientes[] = [
                'mensaje' => 'Tu menú todavía no tiene platillos.',
                'accion' => 'Agregar platillo',
                'url' => ProductoResource::getUrl('create'),
                'importante' => true,
            ];
        }

        if ($restaurante->pedidos_activos && blank($restaurante->whatsapp_pedidos)) {
            $pendientes[] = [
                'mensaje' => __('panel.inicio.whatsapp_faltante'),
                'accion' => 'Agregar WhatsApp',
                'url' => $puedeEditarRestaurante ? PedidosYReservas::getUrl() : null,
                'importante' => true,
            ];
        }

        if (! $restaurante->horarios()->where('cerrado', false)->exists()) {
            $pendientes[] = [
                'mensaje' => 'Agrega tu horario para que tus clientes sepan cuándo abres.',
                'accion' => 'Agregar horario',
                'url' => HorarioResource::getUrl('index'),
                'importante' => false,
            ];
        }

        $agotados = $restaurante->productos()->where('disponible', false)->count();

        if ($agotados > 0) {
            $pendientes[] = [
                'mensaje' => $agotados === 1 ? 'Tienes 1 platillo agotado.' : "Tienes {$agotados} platillos agotados.",
                'accion' => 'Ver agotados',
                'url' => ProductoResource::getUrl('index', ['filters' => ['agotados' => ['isActive' => true]]]),
                'importante' => false,
            ];
        }

        $sinFoto = $restaurante->productos()->whereNull('imagen')->count();

        if ($sinFoto > 0) {
            $pendientes[] = [
                'mensaje' => ($sinFoto === 1 ? '1 platillo no tiene foto.' : "{$sinFoto} platillos no tienen foto.").' Con foto se piden más.',
                'accion' => 'Ver platillos',
                'url' => ProductoResource::getUrl('index'),
                'importante' => false,
            ];
        }

        if ($restaurante->productos()->exists() && ! $restaurante->codigosQr()->exists()) {
            $pendientes[] = [
                'mensaje' => 'Aún no creas los códigos QR de tus mesas.',
                'accion' => 'Crear QR de mesas',
                'url' => CodigoQrResource::getUrl('index'),
                'importante' => false,
            ];
        }

        return $pendientes;
    }
}
