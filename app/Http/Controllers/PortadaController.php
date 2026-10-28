<?php

namespace App\Http\Controllers;

use App\Models\Restaurante;
use App\Services\ContactoSoporte;
use App\Services\PoliticaDeContenido;
use Illuminate\Http\Response;
use Throwable;

class PortadaController extends Controller
{
    public function __construct(private ContactoSoporte $soporte) {}

    /**
     * Portada pública: sin sesión ni cookies y cacheable (docs/despliegue.md, "Landing"). El enlace al menú de muestra es
     * opcional: si la base de datos aún no está lista, la portada se muestra igual, sin ese enlace.
     */
    public function __invoke(): Response
    {
        return response()->view('landing.inicio', [
            'soporte' => $this->soporte,
            'demoUrl' => $this->urlMenuDeMuestra(),
        ])->withHeaders([
            'Content-Security-Policy' => PoliticaDeContenido::LANDING,
            'Cache-Control' => 'public, max-age=300, stale-while-revalidate=600',
        ]);
    }

    private function urlMenuDeMuestra(): ?string
    {
        $configurada = $this->urlConfigurada((string) config('menuonl.demo_url'));

        if ($configurada !== null) {
            return $configurada;
        }

        try {
            $demo = Restaurante::where('estado', 'activo')->where('slug', 'la-esquina')->first()
                ?? Restaurante::where('estado', 'activo')->first();
        } catch (Throwable) {
            return null;
        }

        return $demo ? route('menu.publico', ['slug' => $demo->slug]) : null;
    }

    /**
     * MENU_DEMO_URL solo vale si es una ruta relativa o una URL http(s) del propio dominio, sin usuario ni caracteres raros.
     */
    private function urlConfigurada(string $valor): ?string
    {
        $valor = trim($valor);

        if ($valor === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $valor)) {
            return null;
        }

        if (str_starts_with($valor, '/')) {
            return str_starts_with($valor, '//') ? null : $valor;
        }

        $url = parse_url($valor);
        $propia = parse_url((string) config('app.url'));

        if (! is_array($url) || ! is_array($propia) || isset($url['user']) || isset($url['pass'])) {
            return null;
        }

        $esquemaValido = in_array($url['scheme'] ?? '', ['http', 'https'], true);
        $mismoDominio = isset($url['host'], $propia['host']) && strcasecmp($url['host'], $propia['host']) === 0;

        return $esquemaValido && $mismoDominio ? $valor : null;
    }
}
