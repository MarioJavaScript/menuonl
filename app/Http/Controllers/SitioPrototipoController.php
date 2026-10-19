<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\SitioConfiguracion;
use App\Models\SitioGaleria;
use Illuminate\Http\Response;

/**
 * Prototipo de "sitio del restaurante" completo (/sitio/{slug}), alimentado por los datos reales del restaurante.
 * Es un experimento de diseño: la ruta solo se registra en local y testing (ver routes/web.php).
 * El contenido propio del sitio (portada, historia, datos, galería) sale de sitio_configuraciones y sitio_galeria,
 * editables desde el panel; si falta algo, la vista oculta o simplifica esa sección.
 * Sigue los patrones de MenuPublicoController: scope de tenant liberado en finally, 404 con la vista de
 * "menú no disponible" y las mismas cabeceras de seguridad. No usa la caché del menú.
 */
class SitioPrototipoController extends Controller
{
    private const SECURITY_HEADERS = [
        'Content-Security-Policy' => "default-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:;",
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
    ];

    public function mostrar(string $slug): Response
    {
        try {
            $restaurante = Restaurante::where('slug', $slug)->first();

            if (
                $restaurante === null
                || ! $restaurante->isActivo()
                || $restaurante->trashed()
            ) {
                return response()
                    ->view('menu.no-disponible', [], 404)
                    ->withHeaders(self::SECURITY_HEADERS);
            }

            RestauranteScope::setRestauranteId($restaurante->id);

            $idioma = $restaurante->idioma_default ?: 'es';
            app()->setLocale($idioma);

            $categorias = Categoria::where('restaurante_id', $restaurante->id)
                ->visible()
                ->ordenado()
                ->with([
                    'traducciones',
                    'productos' => function ($query) use ($restaurante): void {
                        $query->visible()
                            ->ordenado()
                            ->with([
                                'traducciones',
                                'variantes' => fn ($vq) => $vq->ordenado()->with('traducciones'),
                                'etiquetas',
                            ]);

                        if (! $restaurante->mostrar_agotados) {
                            $query->disponible();
                        }
                    },
                ])
                ->get();

            $sitio = SitioConfiguracion::where('restaurante_id', $restaurante->id)->first();
            $galeria = SitioGaleria::where('restaurante_id', $restaurante->id)->visible()->ordenado()->get();

            $contactos = $restaurante->contactos()->where('visible', true)->orderBy('orden')->get();
            $horarios = $restaurante->horarios()->orderBy('dia_semana')->orderBy('abre')->get();

            $direccion = $contactos->firstWhere('tipo', 'direccion')?->valor;
            $telefono = $contactos->firstWhere('tipo', 'telefono')?->valor;
            $whatsapp = preg_replace('/[^0-9]/', '', (string) $restaurante->whatsapp_pedidos);

            $diaHoy = (int) now($restaurante->zona_horaria)->dayOfWeek;

            return response()
                ->view('sitio.prototipo', [
                    'restaurante' => $restaurante,
                    'idioma' => $idioma,
                    'categorias' => $categorias,
                    'contactos' => $contactos,
                    'horarios' => $horarios,
                    'estaAbierto' => $restaurante->estaAbiertoAhora(),
                    'turnosHoy' => $horarios->where('dia_semana', $diaHoy)->where('cerrado', false),
                    'direccion' => $direccion,
                    'mapaUrl' => filled($direccion) ? 'https://www.openstreetmap.org/search?query='.rawurlencode($direccion) : null,
                    'telefono' => $telefono,
                    'telefonoHref' => filled($telefono) ? 'tel:'.preg_replace('/[^0-9+]/', '', $telefono) : null,
                    'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
                    'turnosReserva' => $horarios->where('cerrado', false)
                        ->map(fn ($h): array => ['dia' => (int) $h->dia_semana, 'abre' => substr((string) $h->abre, 0, 5), 'cierra' => substr((string) $h->cierra, 0, 5)])
                        ->values()
                        ->all(),
                    'sitio' => $sitio,
                    'galeria' => $galeria,
                    'hojasListas' => $this->hojasDeViteDisponibles(),
                ], 200)
                ->withHeaders(self::SECURITY_HEADERS);
        } finally {
            RestauranteScope::setRestauranteId(null);
        }
    }

    /**
     * Vite solo se invoca si hay servidor de desarrollo o un build que ya incluya las hojas del sitio;
     * así un manifest anterior (sin estas entradas) no provoca un error 500.
     */
    private function hojasDeViteDisponibles(): bool
    {
        if (file_exists(public_path('hot'))) {
            return true;
        }

        $manifiesto = public_path('build/manifest.json');
        if (! is_file($manifiesto)) {
            return false;
        }

        $entradas = json_decode((string) file_get_contents($manifiesto), true);

        return is_array($entradas)
            && array_key_exists('resources/css/sitio.css', $entradas)
            && array_key_exists('resources/js/sitio.js', $entradas);
    }
}
