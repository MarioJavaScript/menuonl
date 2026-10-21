<?php

namespace App\Http\Controllers;

use App\Models\Restaurante;
use App\Services\Catalogos;
use Illuminate\Contracts\View\View;
use Throwable;

class PortadaController extends Controller
{
    /**
     * Colores representativos de cada plantilla del sitio (fondo, texto y acento), tomados de resources/css/sitio/*.css.
     * Son los de cada plantilla, no de la marca, por eso se pintan en línea en las muestras.
     *
     * @var array<string, array{fondo: string, tinta: string, acento: string}>
     */
    private const array PALETAS = [
        'a' => ['fondo' => '#ffffff', 'tinta' => '#111111', 'acento' => '#7a5a30'],
        'b' => ['fondo' => '#0b0b0a', 'tinta' => '#f4efe6', 'acento' => '#c9b08a'],
        'c' => ['fondo' => '#f6f0e2', 'tinta' => '#1e1a14', 'acento' => '#7f3417'],
        'd' => ['fondo' => '#0c0b0a', 'tinta' => '#f3ede2', 'acento' => '#c9a24a'],
        'e' => ['fondo' => '#ffd0d4', 'tinta' => '#5a1d0c', 'acento' => '#9a5538'],
        'f' => ['fondo' => '#0b0f14', 'tinta' => '#f2efe9', 'acento' => '#ff2e93'],
        'g' => ['fondo' => '#f6ecdc', 'tinta' => '#4a2a24', 'acento' => '#a98080'],
    ];

    /**
     * Portada pública. El enlace al menú de muestra es opcional: si la base de datos
     * aún no está lista, la portada se muestra igual, sin ese enlace.
     */
    public function __invoke(): View
    {
        return view('welcome', [
            'demoUrl' => $this->urlMenuDeMuestra(),
            'plantillas' => $this->plantillas(),
        ]);
    }

    /**
     * @return list<array{clave: string, nombre: string, descripcion: string, fondo: string, tinta: string, acento: string}>
     */
    private function plantillas(): array
    {
        $plantillas = [];

        foreach (Catalogos::plantillasSitio() as $clave => $plantilla) {
            $plantillas[] = [
                'clave' => strtoupper($clave),
                ...$plantilla,
                ...(self::PALETAS[$clave] ?? self::PALETAS['a']),
            ];
        }

        return $plantillas;
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
