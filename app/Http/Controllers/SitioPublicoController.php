<?php

namespace App\Http\Controllers;

use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Services\SitioPublicoDatos;
use Illuminate\Http\Response;

/**
 * Public marketing site of a restaurant (/s/{slug}), rendered with the template chosen in Ajustes.
 * The full menu keeps living at /m/{slug}.
 */
class SitioPublicoController extends Controller
{
    private const SECURITY_HEADERS = [
        'Content-Security-Policy' => "default-src 'self' 'unsafe-inline'; img-src 'self' data: https:; form-action 'self'; frame-ancestors 'self'",
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    public function __construct(private SitioPublicoDatos $datos) {}

    public function mostrar(string $slug): Response
    {
        $restaurante = Restaurante::where('slug', $slug)->first();

        if ($restaurante === null || ! $restaurante->isActivo() || $restaurante->trashed()) {
            return response()->view('menu.no-disponible', [], 404)->withHeaders(self::SECURITY_HEADERS);
        }

        try {
            RestauranteScope::setRestauranteId($restaurante->id);
            app()->setLocale($restaurante->idioma_default ?: 'es');

            $datos = $this->datos->para($restaurante);

            return response()
                ->view('sitio.'.$datos['plantilla'], $datos)
                ->withHeaders([...self::SECURITY_HEADERS, 'Cache-Control' => 'public, max-age=30, stale-while-revalidate=300']);
        } finally {
            RestauranteScope::setRestauranteId(null);
        }
    }
}
