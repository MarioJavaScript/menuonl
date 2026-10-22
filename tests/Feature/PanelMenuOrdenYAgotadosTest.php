<?php

namespace Tests\Feature;

use App\Filament\Resources\Categorias\Pages\CreateCategoria;
use App\Filament\Resources\Categorias\Pages\ListCategorias;
use App\Filament\Resources\Productos\Pages\CreateProducto;
use App\Filament\Resources\Productos\Pages\EditProducto;
use App\Filament\Resources\Productos\Pages\ListProductos;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Mi menú: orden arrastrando (sin campo numérico) y "Mostrar platillos agotados" junto a la lista de platillos.
 */
class PanelMenuOrdenYAgotadosTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    public function test_los_formularios_ya_no_piden_un_numero_de_orden(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $producto = $this->crearPlatillo($restaurante, $this->crearCategoria($restaurante), 'Sopa');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(CreateCategoria::class)->assertDontSee('Orden de aparición');
        Livewire::test(CreateProducto::class)->assertDontSee('Orden de aparición');
        Livewire::test(EditProducto::class, ['record' => $producto->getRouteKey()])->assertDontSee('Orden de aparición');
    }

    public function test_una_categoria_nueva_queda_al_final_del_menu(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $primera = $this->crearCategoria($restaurante, 'Entradas');
        $primera->update(['orden' => 4]);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(CreateCategoria::class)
            ->fillForm(['traducciones' => [['idioma' => 'es', 'nombre' => 'Postres']]])
            ->call('create')
            ->assertHasNoFormErrors();

        $nueva = Categoria::query()->where('id', '!=', $primera->id)->firstOrFail();
        $this->assertSame(5, $nueva->orden);
    }

    public function test_un_platillo_nuevo_queda_al_final_de_su_categoria(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $categoria = $this->crearCategoria($restaurante);
        $otra = $this->crearCategoria($restaurante, 'Bebidas');
        $this->crearPlatillo($restaurante, $categoria, 'Sopa', ['orden' => 7]);
        $this->crearPlatillo($restaurante, $otra, 'Agua', ['orden' => 40]);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(CreateProducto::class)
            ->fillForm([
                'categoria_id' => $categoria->id,
                'precio' => 80,
                'traducciones' => [['idioma' => 'es', 'nombre' => 'Pozole']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $nuevo = Producto::query()->whereHas('traducciones', fn ($q) => $q->where('nombre', 'Pozole'))->firstOrFail();
        $this->assertSame(8, $nuevo->orden);
    }

    public function test_las_tablas_se_ordenan_arrastrando_y_explican_como(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $uno = $this->crearCategoria($restaurante, 'Uno');
        $dos = $this->crearCategoria($restaurante, 'Dos');
        $uno->update(['orden' => 1]);
        $dos->update(['orden' => 2]);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $categorias = Livewire::test(ListCategorias::class)
            ->assertSee('Ordenar arrastrando')
            ->assertSee('mueve las categorías')
            ->assertDontSee('Orden de aparición');

        $categorias->call('reorderTable', [(string) $dos->id, (string) $uno->id]);

        $this->assertSame(2, $uno->fresh()->orden);
        $this->assertSame(1, $dos->fresh()->orden);

        Livewire::test(ListProductos::class)->assertSee('Ordenar arrastrando')->assertSee('mueve los platillos');
    }

    public function test_el_propietario_cambia_mostrar_platillos_agotados_desde_mi_menu(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->assertTrue($restaurante->mostrar_agotados);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(ListProductos::class)
            ->assertActionVisible('platillos_agotados')
            ->assertSee('Platillos agotados')
            ->callAction('platillos_agotados', ['mostrar_agotados' => false])
            ->assertHasNoActionErrors()
            ->assertNotified('Listo, ya cambió cómo se muestran los platillos agotados.');

        $this->assertFalse($restaurante->fresh()->mostrar_agotados);

        Livewire::test(ListProductos::class)
            ->mountAction('platillos_agotados')
            ->assertActionDataSet(['mostrar_agotados' => false]);
    }

    public function test_el_cambio_se_nota_en_el_menu_publico(): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->crearPlatillo($restaurante, $this->crearCategoria($restaurante), 'Pozole agotado', ['disponible' => false]);
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        $this->get(route('menu.publico', ['slug' => 'la-esquina']))->assertSee('Pozole agotado');

        Livewire::test(ListProductos::class)->callAction('platillos_agotados', ['mostrar_agotados' => false]);

        $this->get(route('menu.publico', ['slug' => 'la-esquina']))->assertDontSee('Pozole agotado');
    }

    #[DataProvider('rolesSinPermisoDelRestaurante')]
    public function test_quien_no_puede_cambiar_los_datos_del_restaurante_no_ve_la_opcion(string $rol): void
    {
        $restaurante = $this->crearRestaurante('la-esquina');
        $this->entrarAlPanel($this->crearUsuario($rol, $restaurante), $restaurante);

        Livewire::test(ListProductos::class)->assertActionHidden('platillos_agotados');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesSinPermisoDelRestaurante(): array
    {
        return ['editor' => ['editor'], 'gerente' => ['gerente']];
    }

    public function test_la_opcion_no_toca_otro_restaurante(): void
    {
        $a = $this->crearRestaurante('restaurante-a');
        $b = $this->crearRestaurante('restaurante-b');
        $this->entrarAlPanel($this->crearUsuario('propietario', $a), $a);

        Livewire::test(ListProductos::class)->callAction('platillos_agotados', ['mostrar_agotados' => false]);

        $this->assertFalse($a->fresh()->mostrar_agotados);
        $this->assertTrue($b->fresh()->mostrar_agotados);
    }
}
