<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Restaurante;
use App\Services\WebpImageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MenuController extends Controller
{
    /**
     * Categories with their dishes (including hidden and sold-out ones) in the restaurant's default language.
     */
    public function index(Restaurante $restaurante): JsonResponse
    {
        Gate::authorize('viewAny', Producto::class);

        $categorias = Categoria::ordenado()
            ->with(['traducciones', 'productos' => fn ($q) => $q->ordenado()->with('traducciones')])
            ->get();

        return response()->json(['data' => $categorias->map(fn (Categoria $c): array => [
            'id' => $c->id,
            'nombre' => $this->nombre($c->traducciones, $restaurante),
            'productos' => $c->productos->map(fn (Producto $p): array => $this->producto($p, $restaurante))->values(),
        ])->values()]);
    }

    public function store(Request $request, Restaurante $restaurante, Categoria $categoria): JsonResponse
    {
        Gate::authorize('create', Producto::class);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'precio' => ['required', 'numeric', 'between:0,100000'],
        ]);

        $producto = DB::transaction(function () use ($datos, $categoria, $restaurante): Producto {
            $producto = Producto::create([
                'categoria_id' => $categoria->id,
                'precio' => $datos['precio'],
                'orden' => (int) $categoria->productos()->max('orden') + 1,
            ]);
            $producto->traducciones()->create([
                'idioma' => $restaurante->idioma_default,
                'nombre' => trim($datos['nombre']),
            ]);

            return $producto;
        });

        return response()->json(['data' => $this->producto($producto->load('traducciones'), $restaurante)], 201);
    }

    public function update(Request $request, Restaurante $restaurante, Producto $producto): JsonResponse
    {
        Gate::authorize('update', $producto);

        $datos = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:150'],
            'precio' => ['sometimes', 'required', 'numeric', 'between:0,100000'],
            'disponible' => ['sometimes', 'required', 'boolean'],
            'visible' => ['sometimes', 'required', 'boolean'],
        ]);

        DB::transaction(function () use ($datos, $producto, $restaurante): void {
            $producto->update(collect($datos)->only(['precio', 'disponible', 'visible'])->all());

            if (isset($datos['nombre'])) {
                $producto->traducciones()->updateOrCreate(
                    ['idioma' => $restaurante->idioma_default],
                    ['nombre' => trim($datos['nombre'])],
                );
            }
        });

        return response()->json(['data' => $this->producto($producto->refresh()->load('traducciones'), $restaurante)]);
    }

    /**
     * Copy a dish (without image or variants) right after the original.
     */
    public function duplicar(Restaurante $restaurante, Producto $producto): JsonResponse
    {
        Gate::authorize('create', Producto::class);

        $copia = DB::transaction(function () use ($producto): Producto {
            $copia = $producto->replicate(['imagen', 'destacado'])->fill(['orden' => $producto->orden + 1]);
            $copia->save();

            foreach ($producto->traducciones as $traduccion) {
                $copia->traducciones()->create([
                    'idioma' => $traduccion->idioma,
                    'nombre' => mb_substr($traduccion->nombre, 0, 140).' (copia)',
                    'descripcion' => $traduccion->descripcion,
                ]);
            }

            return $copia;
        });

        return response()->json(['data' => $this->producto($copia->load('traducciones'), $restaurante)], 201);
    }

    public function destroy(Restaurante $restaurante, Producto $producto): JsonResponse
    {
        Gate::authorize('delete', $producto);

        $producto->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function producto(Producto $producto, Restaurante $restaurante): array
    {
        return [
            'id' => $producto->id,
            'nombre' => $this->nombre($producto->traducciones, $restaurante),
            'precio' => (float) $producto->precio,
            'disponible' => $producto->disponible,
            'visible' => $producto->visible,
            'imagen' => app(WebpImageService::class)->url($producto->imagen, 480),
        ];
    }

    /**
     * Name in the default language, or the first translation available.
     *
     * @param  Collection<int, Model>  $traducciones
     */
    private function nombre($traducciones, Restaurante $restaurante): string
    {
        return (string) ($traducciones->firstWhere('idioma', $restaurante->idioma_default)?->nombre
            ?? $traducciones->first()?->nombre
            ?? '');
    }
}
