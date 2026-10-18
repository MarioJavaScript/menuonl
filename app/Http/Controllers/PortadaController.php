<?php

namespace App\Http\Controllers;

use App\Models\Restaurante;
use Illuminate\Contracts\View\View;
use Throwable;

class PortadaController extends Controller
{
    /**
     * Portada pública. El enlace al menú de muestra es opcional: si la base de datos
     * aún no está lista, la portada se muestra igual, sin ese enlace.
     */
    public function __invoke(): View
    {
        return view('welcome', ['demoUrl' => $this->urlMenuDeMuestra()]);
    }

    private function urlMenuDeMuestra(): ?string
    {
        try {
            $demo = Restaurante::where('estado', 'activo')->where('slug', 'la-esquina')->first()
                ?? Restaurante::where('estado', 'activo')->first();
        } catch (Throwable) {
            return null;
        }

        return $demo ? route('menu.publico', ['slug' => $demo->slug]) : null;
    }
}
