<?php

namespace Tests\Feature;

use App\Filament\Resources\Productos\Pages\EditProducto;
use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Models\Scopes\RestauranteScope;
use App\Models\VarianteTraduccion;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * El nombre, la descripción y las etiquetas de un platillo viven en relaciones (traducciones, producto_etiqueta) que el panel
 * guarda sin tocar al platillo. Cambiarlas debe vaciar la caché del menú público igual que cambiar el precio.
 */
class MenuPublicoCacheRelacionesTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private Restaurante $restaurante;

    private Categoria $categoria;

    private Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();

        $this->restaurante = $this->crearRestaurante('cache-relaciones');
        $this->categoria = $this->crearCategoria($this->restaurante, 'Tacos');
        $this->producto = $this->crearPlatillo($this->restaurante, $this->categoria, 'Nombre Viejo');
    }

    private function urlDelMenu(): string
    {
        return route('menu.publico', ['slug' => $this->restaurante->slug, 'lang' => 'es']);
    }

    private function claveDeCache(): string
    {
        return "menu:{$this->restaurante->id}:es";
    }

    private function calentarCache(): void
    {
        $this->get($this->urlDelMenu())->assertOk();
        $this->assertTrue(Cache::has($this->claveDeCache()));
    }

    /**
     * Sale del contexto del panel para pedir el menú como un visitante.
     */
    private function salirDelPanel(): void
    {
        Filament::setTenant(null);
        Filament::setServingStatus(false);
        RestauranteScope::setRestauranteId(null);
    }

    public function test_renombrar_un_platillo_desde_el_panel_se_ve_en_el_menu_publico(): void
    {
        $this->get($this->urlDelMenu())->assertOk()->assertSee('Nombre Viejo');

        $this->entrarAlPanel($this->crearUsuario('propietario', $this->restaurante), $this->restaurante);

        $componente = Livewire::test(EditProducto::class, ['record' => $this->producto->getRouteKey()]);
        $clave = array_key_first($componente->get('data.traducciones'));
        $componente->set("data.traducciones.{$clave}.nombre", 'Nombre Nuevo')
            ->call('save')
            ->assertHasNoErrors();

        $this->salirDelPanel();

        $this->get($this->urlDelMenu())->assertOk()->assertSee('Nombre Nuevo')->assertDontSee('Nombre Viejo');
    }

    public function test_cambiar_las_etiquetas_desde_el_panel_vacia_la_cache(): void
    {
        $etiqueta = Etiqueta::factory()->create();
        $this->calentarCache();

        $this->entrarAlPanel($this->crearUsuario('propietario', $this->restaurante), $this->restaurante);

        Livewire::test(EditProducto::class, ['record' => $this->producto->getRouteKey()])
            ->set('data.etiquetas', [$etiqueta->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$etiqueta->id], $this->producto->etiquetas()->pluck('etiquetas.id')->all());
        $this->assertFalse(Cache::has($this->claveDeCache()));
    }

    public function test_quitar_una_etiqueta_vacia_la_cache(): void
    {
        $etiqueta = Etiqueta::factory()->create();
        $this->producto->etiquetas()->attach($etiqueta->id);
        $this->calentarCache();

        $this->producto->etiquetas()->detach($etiqueta->id);

        $this->assertFalse(Cache::has($this->claveDeCache()));
    }

    public function test_cambiar_la_traduccion_de_una_categoria_vacia_la_cache(): void
    {
        $this->calentarCache();

        $this->categoria->traducciones()->first()->update(['nombre' => 'Tacos de la casa']);

        $this->assertFalse(Cache::has($this->claveDeCache()));
        $this->get($this->urlDelMenu())->assertOk()->assertSee('Tacos de la casa');
    }

    public function test_cambiar_la_traduccion_de_una_variante_vacia_la_cache(): void
    {
        $variante = ProductoVariante::factory()->create(['producto_id' => $this->producto->id]);
        $traduccion = VarianteTraduccion::factory()->create(['variante_id' => $variante->id, 'idioma' => 'es', 'nombre' => 'Chica']);
        $this->calentarCache();

        $traduccion->update(['nombre' => 'Grande']);

        $this->assertFalse(Cache::has($this->claveDeCache()));
    }

    public function test_agregar_o_quitar_un_idioma_vacia_la_cache(): void
    {
        $this->calentarCache();
        $idioma = RestauranteIdioma::factory()->create(['restaurante_id' => $this->restaurante->id, 'idioma' => 'en']);
        $this->assertFalse(Cache::has($this->claveDeCache()));

        $this->calentarCache();
        $idioma->delete();
        $this->assertFalse(Cache::has($this->claveDeCache()));
    }

    public function test_guardar_una_traduccion_sin_cambios_no_vacia_la_cache(): void
    {
        $this->calentarCache();

        $this->producto->traducciones()->first()->save();

        $this->assertTrue(Cache::has($this->claveDeCache()));
    }

    public function test_cambiar_otro_restaurante_no_vacia_esta_cache(): void
    {
        $otro = $this->crearRestaurante('otro-restaurante');
        $platilloDelOtro = $this->crearPlatillo($otro, $this->crearCategoria($otro), 'Ajeno');
        $this->calentarCache();

        $platilloDelOtro->traducciones()->first()->update(['nombre' => 'Ajeno cambiado']);

        $this->assertTrue(Cache::has($this->claveDeCache()));
    }
}
