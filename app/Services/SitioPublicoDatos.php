<?php

namespace App\Services;

use App\Models\Contacto;
use App\Models\GaleriaImagen;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\Restaurante;

/**
 * Everything the public site templates (A, B and C) need for one restaurant.
 *
 * Must run with the RestauranteScope context already set to that restaurant. Hidden dishes never appear;
 * sold-out ones appear flagged when the restaurant shows them, and disappear otherwise.
 */
class SitioPublicoDatos
{
    /**
     * Weekday order shown to visitors (Monday first); keys are Horario::dia_semana (0 = domingo).
     *
     * @var list<int>
     */
    private const array ORDEN_DIAS = [1, 2, 3, 4, 5, 6, 0];

    public const int MAX_DESTACADOS = 6;

    public function __construct(private WebpImageService $imagenes) {}

    /**
     * @return array<string, mixed>
     */
    public function para(Restaurante $restaurante): array
    {
        $idioma = $restaurante->idioma_default ?: 'es';
        $contactos = Contacto::where('visible', true)->orderBy('orden')->orderBy('id')->get()->groupBy('tipo');
        $valor = fn (string $tipo): ?string => $contactos->get($tipo)?->first()?->valor;
        $whatsapp = $restaurante->whatsapp_pedidos ?: $valor('whatsapp');

        return [
            'r' => $restaurante,
            'plantilla' => array_key_exists($restaurante->sitio_plantilla, Catalogos::plantillasSitio()) ? $restaurante->sitio_plantilla : 'a',
            'idioma' => $idioma,
            'hero' => $restaurante->portada,
            'destacados' => $this->destacados($restaurante, $idioma),
            'galeria' => GaleriaImagen::orderBy('orden')->orderBy('id')->get()->pluck('imagen')->all(),
            'horarios' => $this->horarios(),
            'direccion' => $valor('direccion'),
            'telefono' => $valor('telefono'),
            'telefono_enlace' => $this->telefonoEnlace($valor('telefono')),
            'whatsapp' => $whatsapp,
            'whatsapp_digitos' => $this->soloDigitos($whatsapp),
            'instagram_url' => $this->enlaceRed($valor('instagram'), 'https://instagram.com/'),
            'instagram' => $valor('instagram'),
            'facebook_url' => $this->enlaceRed($valor('facebook'), 'https://facebook.com/'),
            'mapa_url' => $this->enlaceMapa($restaurante, $valor('direccion')),
            'tiene_mapa' => $restaurante->latitud !== null && $restaurante->longitud !== null,
            'reservas_activas' => (bool) $restaurante->reservas_activas,
            'esta_abierto' => $restaurante->estaAbiertoAhora(),
            'menu_url' => route('menu.publico', ['slug' => $restaurante->slug]),
        ];
    }

    /**
     * Featured dishes (or the first visible ones) with their photo and price.
     *
     * @return list<array{nombre: string, descripcion: ?string, precio: string, precio_anterior: ?string, etiquetas: list<string>, categoria: string, imagen: ?string, agotado: bool}>
     */
    private function destacados(Restaurante $restaurante, string $idioma): array
    {
        $consulta = fn (bool $soloDestacados) => Producto::visible()
            ->whereHas('categoria', fn ($q) => $q->where('visible', true))
            ->when($soloDestacados, fn ($q) => $q->destacado())
            ->when(! $restaurante->mostrar_agotados, fn ($q) => $q->disponible())
            ->with(['traducciones', 'categoria.traducciones', 'etiquetas'])
            ->orderBy('orden')
            ->orderBy('id')
            ->limit(self::MAX_DESTACADOS)
            ->get();

        $productos = $consulta(true);

        if ($productos->isEmpty()) {
            $productos = $consulta(false);
        }

        return $productos->map(function (Producto $p) use ($idioma, $restaurante): array {
            $traduccion = $p->traducciones->firstWhere('idioma', $idioma) ?? $p->traducciones->first();
            $categoria = $p->categoria?->traducciones->firstWhere('idioma', $idioma) ?? $p->categoria?->traducciones->first();

            return [
                'nombre' => (string) ($traduccion?->nombre ?? ''),
                'descripcion' => $traduccion?->descripcion,
                'precio' => FormatoMoneda::formatear($p->precioVigente(), $restaurante->moneda, $idioma),
                'precio_anterior' => $p->precioVigente() !== (string) $p->precio ? FormatoMoneda::formatear((string) $p->precio, $restaurante->moneda, $idioma) : null,
                'etiquetas' => $p->etiquetas->map(fn ($etiqueta): string => Catalogos::nombreEtiqueta((string) $etiqueta->codigo))->values()->all(),
                'categoria' => (string) ($categoria?->nombre ?? ''),
                'imagen' => $p->imagen,
                'agotado' => ! $p->disponible,
            ];
        })->filter(fn (array $p): bool => $p['nombre'] !== '')->values()->all();
    }

    /**
     * One line per weekday, Monday first.
     *
     * @return list<array{dia: string, texto: string, cerrado: bool}>
     */
    private function horarios(): array
    {
        $porDia = Horario::orderBy('abre')->get()->groupBy('dia_semana');
        $dias = Catalogos::diasSemana();

        return array_map(function (int $dia) use ($porDia, $dias): array {
            $turnos = $porDia->get($dia, collect());
            $abiertos = $turnos->filter(fn (Horario $h): bool => ! $h->cerrado && $h->abre !== null && $h->cierra !== null);
            $cerrado = $turnos->contains('cerrado', true) || $abiertos->isEmpty();

            return [
                'dia' => $dias[$dia],
                'cerrado' => $cerrado,
                'texto' => $cerrado ? 'Cerrado' : $abiertos->map(fn (Horario $h): string => substr((string) $h->abre, 0, 5).' – '.substr((string) $h->cierra, 0, 5))->implode(' · '),
            ];
        }, self::ORDEN_DIAS);
    }

    private function enlaceMapa(Restaurante $restaurante, ?string $direccion): ?string
    {
        if ($restaurante->latitud !== null && $restaurante->longitud !== null) {
            return 'https://www.google.com/maps/search/?api=1&query='.$restaurante->latitud.','.$restaurante->longitud;
        }

        return $direccion ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($direccion) : null;
    }

    /**
     * Social profile as a safe link: a full http(s) URL is kept, a handle becomes a profile URL, anything else is dropped.
     */
    private function enlaceRed(?string $valor, string $base): ?string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        if (preg_match('#\Ahttps?://#i', $valor) === 1) {
            return filter_var($valor, FILTER_VALIDATE_URL) !== false ? $valor : null;
        }

        $usuario = ltrim($valor, '@');

        return preg_match('/\A[A-Za-z0-9._-]{1,60}\z/', $usuario) === 1 ? $base.$usuario : null;
    }

    private function telefonoEnlace(?string $telefono): ?string
    {
        $limpio = preg_replace('/[^0-9+]/', '', (string) $telefono);

        return $limpio === '' || $limpio === null ? null : 'tel:'.$limpio;
    }

    private function soloDigitos(?string $numero): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $numero);

        return $digitos === '' || $digitos === null ? null : $digitos;
    }
}
