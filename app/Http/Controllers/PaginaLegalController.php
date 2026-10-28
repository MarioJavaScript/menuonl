<?php

namespace App\Http\Controllers;

use App\Services\ContactoSoporte;
use App\Services\PoliticaDeContenido;
use Illuminate\Http\Response;

class PaginaLegalController extends Controller
{
    public function privacidad(): Response
    {
        return $this->pagina('landing.privacidad');
    }

    public function terminos(): Response
    {
        return $this->pagina('landing.terminos');
    }

    /**
     * Páginas legales: mismo trato que la portada (sin sesión, CSP propia y caché pública).
     */
    private function pagina(string $vista): Response
    {
        return response()->view($vista, ['soporte' => app(ContactoSoporte::class)])->withHeaders([
            'Content-Security-Policy' => PoliticaDeContenido::LANDING,
            'Cache-Control' => 'public, max-age=300, stale-while-revalidate=600',
        ]);
    }
}
