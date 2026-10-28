<?php

namespace App\Services;

use Illuminate\Support\Facades\Vite;

/**
 * Content-Security-Policy por grupo de rutas (docs/despliegue.md, punto 6.1).
 *
 * - SITIO y MENU (y publica()): sitio, menú y /q. Sin sesión y cacheables en Cloudflare, así que NO puede llevar nonce (el HTML sería
 *   distinto en cada petición). Por eso no hay scripts en línea: todo el JavaScript va en archivos propios y
 *   `script-src` es solo 'self'. Los estilos en línea (atributos style="") siguen permitidos.
 * - estricta(): /invitaciones (Blade + Vite propios, sin scripts en línea): igual de estricta en scripts.
 * - admin(): Filament + Livewire. Ambos leen el nonce de Vite::cspNonce() y se lo ponen a sus scripts en línea, así que
 *   `script-src` lleva 'nonce-…' y no 'unsafe-inline'. Alpine (estándar) evalúa expresiones con `new Function`, por lo
 *   que hace falta 'unsafe-eval'; es la parte que falta endurecer (ver el documento).
 */
class PoliticaDeContenido
{
    /**
     * Sitio público de un restaurante (/{slug}).
     */
    public const SITIO = "default-src 'self' 'unsafe-inline'; img-src 'self' data: https:; script-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";

    /**
     * Menú público (/{slug}/menu) y redirección de los QR (/q/*). Conserva el `https:` histórico de `default-src`.
     */
    public const MENU = "default-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; script-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'";

    /**
     * Portada (/) y páginas legales: sin nonce (el HTML es cacheable), sin scripts ni estilos en línea y solo recursos propios.
     * El JSON-LD (`application/ld+json`) es un dato, no un script, así que `script-src` no lo bloquea.
     */
    public const LANDING = "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; connect-src 'none'; frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'";

    public function publica(): string
    {
        return self::MENU;
    }

    public function estricta(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }

    public function admin(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://fonts.bunny.net",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }

    /**
     * Solo en desarrollo con `npm run dev` (nunca en producción): el servidor de Vite sirve scripts, estilos y el websocket desde otro origen.
     */
    public function paraEntorno(string $politica): string
    {
        if (app()->isProduction() || ! Vite::isRunningHot()) {
            return $politica;
        }

        $url = parse_url(trim((string) @file_get_contents(Vite::hotFile())));
        $origen = isset($url['scheme'], $url['host']) ? $url['scheme'].'://'.$url['host'].(isset($url['port']) ? ':'.$url['port'] : '') : null;

        if ($origen === null) {
            return $politica;
        }

        $websocket = str_replace(['http://', 'https://'], ['ws://', 'wss://'], $origen);

        return preg_replace_callback(
            '/(^|; )(script-src|style-src|font-src|connect-src|default-src)([^;]*)/',
            fn (array $m): string => $m[1].$m[2].$m[3].' '.$origen.($m[2] === 'connect-src' ? ' '.$websocket : ''),
            $politica,
        );
    }
}
