<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Services\Catalogos;
use App\Services\MenuCacheService;
use App\Services\PoliticaDeContenido;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MenuPublicoController extends Controller
{
    private const SECURITY_HEADERS = [
        'Content-Security-Policy' => PoliticaDeContenido::MENU,
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
    ];

    public function __construct(
        protected MenuCacheService $menuCacheService
    ) {}

    /**
     * Display the public mobile-optimized SSR menu for a restaurant.
     */
    public function mostrar(Request $request, string $slug): Response
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

            // Multi-tenant isolation: set scope context
            RestauranteScope::setRestauranteId($restaurante->id);

            // Eager-load child relation safely under the established tenant scope
            $restaurante->load('idiomas');

            // Language resolution: query ?lang= > Accept-Language > idioma_default
            $idiomasConfigurados = $restaurante->idiomas->pluck('idioma')->all();
            $idiomasDisponibles = array_values(array_unique(array_filter([
                $restaurante->idioma_default,
                ...$idiomasConfigurados,
            ])));

            $langQuery = $request->query('lang');
            if (is_string($langQuery) && in_array($langQuery, $idiomasDisponibles, true)) {
                $idioma = $langQuery;
            } else {
                $preferred = $request->getPreferredLanguage($idiomasDisponibles);
                if (is_string($preferred) && in_array($preferred, $idiomasDisponibles, true)) {
                    $idioma = $preferred;
                } else {
                    $idioma = $restaurante->idioma_default ?: 'es';
                }
            }

            app()->setLocale($idioma);

            // Mesa sanitization: alphanumeric, spaces, hyphens, underscores up to 20 chars
            $mesaRaw = $request->query('mesa');
            $mesa = (is_string($mesaRaw) && preg_match('/\A[A-Za-z0-9 _-]{1,20}\z/', trim($mesaRaw)))
                ? trim($mesaRaw)
                : null;

            // Dynamic hours calculation in real time (NOT cached)
            $estaAbierto = $restaurante->estaAbiertoAhora();

            // L2 cached catalog tree per restaurant and language
            $menuData = $this->menuCacheService->rememberMenu($restaurante, $idioma, function () use ($restaurante): array {
                $categorias = Categoria::where('restaurante_id', $restaurante->id)
                    ->visible()
                    ->ordenado()
                    ->with([
                        'restaurante',
                        'traducciones',
                        'productos' => function ($query) use ($restaurante): void {
                            $query->visible()
                                ->ordenado()
                                ->with([
                                    'restaurante',
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

                $destacadosQuery = Producto::where('restaurante_id', $restaurante->id)
                    ->destacado()
                    ->visible()
                    ->ordenado()
                    ->with([
                        'restaurante',
                        'traducciones',
                        'variantes' => fn ($vq) => $vq->ordenado()->with('traducciones'),
                        'etiquetas',
                    ]);

                if (! $restaurante->mostrar_agotados) {
                    $destacadosQuery->disponible();
                }

                $destacados = $destacadosQuery->get();

                $contactos = $restaurante->contactos()
                    ->where('visible', true)
                    ->orderBy('orden')
                    ->get();

                $horarios = $restaurante->horarios()
                    ->orderBy('dia_semana')
                    ->orderBy('abre')
                    ->get();

                return [
                    'categorias' => $categorias,
                    'destacados' => $destacados,
                    'contactos' => $contactos,
                    'horarios' => $horarios,
                ];
            });

            $plantilla = array_key_exists((string) $restaurante->sitio_plantilla, Catalogos::plantillasSitio()) ? (string) $restaurante->sitio_plantilla : 'a';
            $vista = view()->exists('menu.plantillas.'.$plantilla) ? 'menu.plantillas.'.$plantilla : 'menu.publico';

            return response()
                ->view($vista, [
                    'restaurante' => $restaurante,
                    'idioma' => $idioma,
                    'idiomasDisponibles' => $idiomasDisponibles,
                    'mesa' => $mesa,
                    'estaAbierto' => $estaAbierto,
                    'categorias' => $menuData['categorias'],
                    'destacados' => $menuData['destacados'],
                    'contactos' => $menuData['contactos'],
                    'horarios' => $menuData['horarios'],
                ], 200)
                ->withHeaders([
                    ...self::SECURITY_HEADERS,
                    'Cache-Control' => 'public, max-age=30, stale-while-revalidate=86400',
                ]);
        } finally {
            RestauranteScope::setRestauranteId(null);
        }
    }
}
