<?php

namespace App\Services;

use App\Enums\Capacidad;
use App\Filament\Pages\AjustesDeReservasYPedidos;
use App\Filament\Pages\Apariencia;
use App\Filament\Pages\Tenancy\EditRestauranteProfile;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\CodigosQr\CodigoQrResource;
use App\Filament\Resources\Contactos\ContactoResource;
use App\Filament\Resources\Horarios\HorarioResource;
use App\Filament\Resources\Productos\ProductoResource;
use App\Models\Categoria;
use App\Models\CodigoQr;
use App\Models\Contacto;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\Restaurante;
use App\Models\User;

/**
 * Lo que ve el dueño en Inicio: qué falta configurar (con porcentaje), avisos y cosas que atender (la operación del día es del widget OperacionDelDiaWidget).
 *
 * Solo lee. Cada elemento aparece únicamente si la persona puede abrir la pantalla a la que lleva, con las políticas que
 * ya existen (no se cambia ningún permiso): por eso un gerente o un editor ven menos pasos que el propietario.
 */
class ProgresoDeConfiguracion
{
    /**
     * @return array{
     *     pasos: list<array{clave: string, hecho: bool, importante: bool, url: string}>,
     *     hechos: int,
     *     total: int,
     *     porcentaje: ?int,
     *     avisos: list<array{clave: string, url: string}>,
     *     atencion: list<array{clave: string, cantidad: int, url: string}>,
     *     codigo_qr_url: ?string
     * }
     */
    public function para(Restaurante $restaurante, User $usuario): array
    {
        $tieneHorario = null;
        $consultarHorario = function () use ($restaurante, &$tieneHorario): bool {
            return $tieneHorario ??= $this->tieneHorario($restaurante);
        };

        $pasos = $this->pasos($restaurante, $usuario, $consultarHorario);
        $hechos = count(array_filter($pasos, fn (array $paso): bool => $paso['hecho']));
        $total = count($pasos);

        return [
            'pasos' => $pasos,
            'hechos' => $hechos,
            'total' => $total,
            'porcentaje' => $total > 0 ? (int) round($hechos / $total * 100) : null,
            'avisos' => $this->avisos($restaurante, $usuario, $consultarHorario),
            'atencion' => $this->atencion($restaurante, $usuario),
            'codigo_qr_url' => $this->urlDeDescargaDelQr($usuario),
        ];
    }

    /**
     * @param  \Closure(): bool  $tieneHorario
     * @return list<array{clave: string, hecho: bool, importante: bool, url: string}>
     */
    private function pasos(Restaurante $restaurante, User $usuario, \Closure $tieneHorario): array
    {
        $puedeEditar = $usuario->can('update', $restaurante);
        $puedeEditarDatos = $puedeEditar && $restaurante->permiteA($usuario, Capacidad::EditarDatos);
        $puedeEditarDiseno = $puedeEditar && $restaurante->permiteA($usuario, Capacidad::EditarDiseno);
        $puedeEditarTelefonos = $puedeEditar && $restaurante->permiteA($usuario, Capacidad::EditarTelefonos);
        $candidatos = [
            ['categoria', $usuario->can('viewAny', Categoria::class), fn (): bool => $restaurante->categorias()->exists(), true, fn (): string => CategoriaResource::getUrl('create')],
            ['platillo', $usuario->can('viewAny', Producto::class), fn (): bool => $restaurante->productos()->exists(), true, fn (): string => ProductoResource::getUrl('create')],
            ['horario', $usuario->can('viewAny', Horario::class), $tieneHorario, false, fn (): string => HorarioResource::getUrl('index')],
            ['contacto', $usuario->can('viewAny', Contacto::class), fn (): bool => $restaurante->contactos()->where('visible', true)->exists(), false, fn (): string => ContactoResource::getUrl('index')],
            ['descripcion', $puedeEditarDatos, fn (): bool => filled(trim((string) $restaurante->descripcion)), false, fn (): string => EditRestauranteProfile::getUrl()],
            ['mapa', $puedeEditarDatos, fn (): bool => $restaurante->latitud !== null && $restaurante->longitud !== null, false, fn (): string => EditRestauranteProfile::getUrl()],
            ['logotipo', $puedeEditarDiseno, fn (): bool => filled($restaurante->logo), false, fn (): string => Apariencia::getUrl()],
            ['portada', $puedeEditarDiseno, fn (): bool => filled($restaurante->portada), false, fn (): string => Apariencia::getUrl()],
            ['whatsapp', $puedeEditarTelefonos && $restaurante->pedidos_activos, fn (): bool => filled($restaurante->whatsapp_pedidos), true, fn (): string => AjustesDeReservasYPedidos::getUrl()],
        ];

        $pasos = [];

        foreach ($candidatos as [$clave, $visible, $hecho, $importante, $url]) {
            if ($visible) {
                $pasos[] = ['clave' => $clave, 'hecho' => (bool) $hecho(), 'importante' => $importante, 'url' => $url()];
            }
        }

        return $pasos;
    }

    /**
     * Avisos que no cuentan para el porcentaje: reservas abiertas al público (si el plan las incluye) sin un horario que las respalde.
     *
     * @param  \Closure(): bool  $tieneHorario
     * @return list<array{clave: string, url: string}>
     */
    private function avisos(Restaurante $restaurante, User $usuario, \Closure $tieneHorario): array
    {
        if ($restaurante->reservasDisponibles() && $usuario->can('viewAny', Horario::class) && ! $tieneHorario()) {
            return [['clave' => 'reservas_sin_horario', 'url' => HorarioResource::getUrl('index')]];
        }

        return [];
    }

    /**
     * Cosas del día a día que conviene revisar; no cuentan para el porcentaje.
     *
     * @return list<array{clave: string, cantidad: int, url: string}>
     */
    private function atencion(Restaurante $restaurante, User $usuario): array
    {
        $atencion = [];

        if ($usuario->can('viewAny', Producto::class)) {
            $agotados = $restaurante->productos()->where('disponible', false)->count();
            $sinFoto = $restaurante->productos()->whereNull('imagen')->count();

            if ($agotados > 0) {
                $atencion[] = ['clave' => 'agotados', 'cantidad' => $agotados, 'url' => ProductoResource::getUrl('index', ['filters' => ['agotados' => ['isActive' => true]]])];
            }

            if ($sinFoto > 0) {
                $atencion[] = ['clave' => 'sin_foto', 'cantidad' => $sinFoto, 'url' => ProductoResource::getUrl('index')];
            }
        }

        if ($usuario->can('viewAny', CodigoQr::class) && $restaurante->productos()->exists() && ! $restaurante->codigosQr()->exists()) {
            $atencion[] = ['clave' => 'sin_qr', 'cantidad' => 1, 'url' => CodigoQrResource::getUrl('index')];
        }

        return $atencion;
    }

    /**
     * Descarga del QR principal (el general, o el primero activo) para quien puede ver los QR.
     */
    private function urlDeDescargaDelQr(User $usuario): ?string
    {
        if (! $usuario->can('viewAny', CodigoQr::class)) {
            return null;
        }

        $codigo = CodigoQr::query()->where('activo', true)->orderByRaw('mesa is null desc')->orderBy('id')->first();

        return $codigo === null ? null : route('codigos-qr.descargar', ['codigoQr' => $codigo, 'formato' => 'png']);
    }

    private function tieneHorario(Restaurante $restaurante): bool
    {
        return $restaurante->horarios()->where('cerrado', false)->exists();
    }
}
