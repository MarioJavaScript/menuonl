<?php

namespace Tests\Concerns;

use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * Helpers shared by the panel tests: restaurants, users with a role, and the Filament panel context.
 */
trait ArmaEscenarioDePanel
{
    protected function setUpEscenarioDePanel(): void
    {
        RestauranteScope::setRestauranteId(null);
    }

    /**
     * Laravel runs `tearDown<TraitName>` automatically. The scope context is static, so it must not leak into other tests.
     */
    protected function tearDownArmaEscenarioDePanel(): void
    {
        RestauranteScope::setRestauranteId(null);
    }

    protected function crearRestaurante(string $slug): Restaurante
    {
        $restaurante = Restaurante::factory()->create(['slug' => $slug, 'idioma_default' => 'es']);
        RestauranteIdioma::factory()->create(['restaurante_id' => $restaurante->id, 'idioma' => 'es']);

        return $restaurante;
    }

    protected function crearUsuario(string $rol, ?Restaurante $restaurante = null): User
    {
        if ($rol === 'superadmin') {
            return User::factory()->superadmin()->create();
        }

        $usuario = User::factory()->create();
        $usuario->restaurantes()->attach($restaurante->id, ['rol' => $rol]);

        return $usuario;
    }

    protected function crearCategoria(Restaurante $restaurante, string $nombre = 'Entradas'): Categoria
    {
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => $nombre]);

        return $categoria;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    protected function crearPlatillo(Restaurante $restaurante, Categoria $categoria, string $nombre, array $atributos = []): Producto
    {
        $producto = Producto::factory()->create($atributos + ['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => $nombre]);

        return $producto;
    }

    /**
     * Enter the admin panel as the user, with the restaurant as the active tenant (Livewire-level tests).
     */
    protected function entrarAlPanel(User $usuario, Restaurante $restaurante): void
    {
        $this->actingAs($usuario);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($restaurante);
        Filament::bootCurrentPanel();
        // Real panel requests set this in DispatchServingFilamentEvent; Livewire::test skips the middleware.
        Filament::setServingStatus();
    }
}
