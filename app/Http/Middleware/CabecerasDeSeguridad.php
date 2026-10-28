<?php

namespace App\Http\Middleware;

use App\Services\PoliticaDeContenido;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad de todas las respuestas (docs/despliegue.md, punto 6.1).
 *
 * Nunca pisa una cabecera que el controlador ya haya puesto (las páginas públicas llevan la suya). No toca la sesión ni
 * las cookies, así que las páginas públicas siguen siendo cacheables. La CSP solo se añade a respuestas HTML.
 *
 * HSTS solo cuando la petición llegó por https (la IP/esquema reales dependen de TRUSTED_PROXIES) y APP_ENV=production.
 */
class CabecerasDeSeguridad
{
    public const PERMISSIONS_POLICY = 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()';

    public function __construct(private PoliticaDeContenido $politicas) {}

    public function handle(Request $request, Closure $next): Response
    {
        $esAdmin = $request->is('admin', 'admin/*', 'livewire*', 'filament*', 'diseno');

        // El nonce debe existir antes de renderizar: Filament y Livewire lo leen de Vite::cspNonce().
        $nonce = $esAdmin ? Str::random(40) : null;

        if ($nonce !== null) {
            Vite::useCspNonce($nonce);
        }

        $respuesta = $next($request);

        $this->ponerSiFalta($respuesta, 'X-Content-Type-Options', 'nosniff');
        $this->ponerSiFalta($respuesta, 'X-Frame-Options', 'SAMEORIGIN');
        $this->ponerSiFalta($respuesta, 'Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->ponerSiFalta($respuesta, 'Permissions-Policy', self::PERMISSIONS_POLICY);

        if ($this->esHtml($respuesta)) {
            $politica = $respuesta->headers->get('Content-Security-Policy') ?? match (true) {
                $nonce !== null => $this->politicas->admin($nonce),
                $request->is('invitaciones', 'invitaciones/*') => $this->politicas->estricta(),
                default => $this->politicas->publica(),
            };

            $respuesta->headers->set('Content-Security-Policy', $this->politicas->paraEntorno($politica));
        }

        $segundos = (int) config('menuonl.seguridad.hsts_segundos');

        if ($segundos > 0 && $request->isSecure() && app()->environment('production')) {
            $this->ponerSiFalta($respuesta, 'Strict-Transport-Security', "max-age={$segundos}");
        }

        return $respuesta;
    }

    private function ponerSiFalta(Response $respuesta, string $cabecera, string $valor): void
    {
        if (! $respuesta->headers->has($cabecera)) {
            $respuesta->headers->set($cabecera, $valor);
        }
    }

    private function esHtml(Response $respuesta): bool
    {
        return str_contains(strtolower((string) $respuesta->headers->get('Content-Type')), 'text/html');
    }
}
