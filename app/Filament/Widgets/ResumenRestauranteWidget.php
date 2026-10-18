<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Tenancy\EditRestauranteProfile;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\CodigosQr\CodigoQrResource;
use App\Filament\Resources\Horarios\HorarioResource;
use App\Filament\Resources\Productos\ProductoResource;
use App\Models\Restaurante;
use App\Services\Catalogos;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

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

        $temas = Catalogos::temas();
        $temaKey = $restaurante->temaVigente();
        $temaNombre = $temas[$temaKey]['nombre'] ?? $temaKey;
        $puedeEditarRestaurante = auth()->user()?->can('update', $restaurante) ?? false;

        /** @var list<array{mensaje: string, accion: string, url: ?string}> $avisos */
        $avisos = [];

        if (! $restaurante->categorias()->exists()) {
            $avisos[] = [
                'mensaje' => 'Aún no tienes categorías. Crea la primera para organizar tus productos.',
                'accion' => 'Crear categoría',
                'url' => CategoriaResource::getUrl('create'),
            ];
        } elseif (! $restaurante->productos()->exists()) {
            $avisos[] = [
                'mensaje' => 'Tu menú aún no tiene productos registrados.',
                'accion' => 'Agregar producto',
                'url' => ProductoResource::getUrl('create'),
            ];
        }

        if (! $restaurante->horarios()->where('cerrado', false)->exists()) {
            $avisos[] = [
                'mensaje' => 'No has configurado horarios de apertura.',
                'accion' => 'Configurar horarios',
                'url' => HorarioResource::getUrl('index'),
            ];
        }

        if ($restaurante->pedidos_activos && blank($restaurante->whatsapp_pedidos)) {
            $avisos[] = [
                'mensaje' => 'Los pedidos están activos pero falta el número de WhatsApp.',
                'accion' => 'Configurar WhatsApp',
                'url' => $puedeEditarRestaurante ? EditRestauranteProfile::getUrl() : null,
            ];
        }

        return [
            'restaurante' => $restaurante,
            'estaPublicado' => $restaurante->isActivo(),
            'estaAbierto' => $restaurante->estaAbiertoAhora(),
            'temaNombre' => $temaNombre,
            'puedeEditarRestaurante' => $puedeEditarRestaurante,
            'crearProductoUrl' => ProductoResource::getUrl('create'),
            'productosUrl' => ProductoResource::getUrl('index'),
            'categoriasUrl' => CategoriaResource::getUrl('index'),
            'perfilRestauranteUrl' => $puedeEditarRestaurante ? EditRestauranteProfile::getUrl() : null,
            'codigosQrUrl' => CodigoQrResource::getUrl('index'),
            'menuPublicoUrl' => route('menu.publico', ['slug' => $restaurante->slug]),
            'avisos' => $avisos,
        ];
    }
}
