<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RestaurantResource;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuApiController extends Controller
{
    /**
     * Display the public REST API menu for a restaurant.
     */
    public function show(Request $request, string $slug): RestaurantResource|JsonResponse
    {
        $restaurante = Restaurante::where('slug', $slug)->first();

        if (
            $restaurante === null
            || ! $restaurante->isActivo()
            || $restaurante->trashed()
        ) {
            return response()->json([
                'message' => 'Restaurante no encontrado o no disponible.',
            ], 404);
        }

        try {
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

            // Context attributes for resources
            $request->attributes->set('idioma', $idioma);
            $request->attributes->set('idiomas_disponibles', $idiomasDisponibles);
            $request->attributes->set('moneda', $restaurante->moneda);
            $request->attributes->set('mesa', $mesa);

            // Eager-load relations without N+1
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

            $restaurante->setRelation('categorias', $categorias);
            $restaurante->setRelation('contactos', $contactos);
            $restaurante->setRelation('horarios', $horarios);
            $request->attributes->set('destacados', $destacados);

            return new RestaurantResource($restaurante);
        } finally {
            RestauranteScope::setRestauranteId(null);
        }
    }
}
