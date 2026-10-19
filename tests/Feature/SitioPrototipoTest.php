<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\Contacto;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Models\VarianteTraduccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prototipo del sitio del restaurante (/sitio/{slug}): solo existe en local y testing.
 */
class SitioPrototipoTest extends TestCase
{
    use RefreshDatabase;

    private function restauranteConMenu(array $atributos = []): Restaurante
    {
        $restaurante = Restaurante::factory()->create([
            'estado' => 'activo',
            'moneda' => 'MXN',
            'idioma_default' => 'es',
            'zona_horaria' => 'America/Mexico_City',
            'whatsapp_pedidos' => '525512345678',
            ...$atributos,
        ]);

        Horario::factory()->create(['restaurante_id' => $restaurante->id, 'dia_semana' => 2, 'abre' => '13:00:00', 'cierra' => '23:00:00', 'cerrado' => false]);
        Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'tipo' => 'telefono', 'valor' => '+52 55 1234 5678', 'visible' => true]);
        Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'tipo' => 'direccion', 'valor' => 'Av. Reforma 10, CDMX', 'visible' => true]);

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id, 'visible' => true, 'orden' => 1]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Tacos de la casa']);

        $oferta = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id, 'precio' => 120, 'precio_oferta' => 99, 'disponible' => true, 'visible' => true, 'orden' => 1]);
        ProductoTraduccion::factory()->create(['producto_id' => $oferta->id, 'idioma' => 'es', 'nombre' => 'Taco de oferta']);

        $agotado = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id, 'precio' => 80, 'disponible' => false, 'visible' => true, 'orden' => 2]);
        ProductoTraduccion::factory()->create(['producto_id' => $agotado->id, 'idioma' => 'es', 'nombre' => 'Taco agotado']);

        $conVariantes = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id, 'precio' => 60, 'disponible' => true, 'visible' => true, 'orden' => 3]);
        ProductoTraduccion::factory()->create(['producto_id' => $conVariantes->id, 'idioma' => 'es', 'nombre' => 'Taco con variantes']);
        $variante = ProductoVariante::factory()->create(['producto_id' => $conVariantes->id, 'precio' => 95, 'disponible' => true]);
        VarianteTraduccion::factory()->create(['variante_id' => $variante->id, 'idioma' => 'es', 'nombre' => 'Orden de cinco']);

        return $restaurante;
    }

    private function sitio(Restaurante $restaurante): TestResponse
    {
        // Livewire inyecta sus assets si otro test del mismo proceso renderizó un componente; en una petición real no ocurre.
        Livewire::flushState();

        return $this->get(route('sitio.prototipo', ['slug' => $restaurante->slug]));
    }

    public function test_el_sitio_responde_200_con_datos_reales_del_restaurante(): void
    {
        $restaurante = $this->restauranteConMenu();

        $respuesta = $this->sitio($restaurante)->assertOk();

        $respuesta->assertSee($restaurante->nombre);
        $respuesta->assertSee('Tacos de la casa');
        $respuesta->assertSee('Taco de oferta');
        $respuesta->assertSee('99.00');
        $respuesta->assertSee('Taco agotado');
        $respuesta->assertSee('Orden de cinco');
        $respuesta->assertSee('Av. Reforma 10, CDMX');
        $respuesta->assertSee('Reservar');
        $respuesta->assertSee('data-whatsapp="525512345678"', false);
    }

    public function test_incluye_las_cabeceras_de_seguridad_del_menu(): void
    {
        $respuesta = $this->sitio($this->restauranteConMenu());

        $respuesta->assertHeader('X-Content-Type-Options', 'nosniff');
        $respuesta->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $respuesta->assertHeader('Content-Security-Policy');
    }

    public function test_responde_404_si_el_restaurante_no_existe_esta_inactivo_o_fue_eliminado(): void
    {
        $this->get('/sitio/no-existe')->assertNotFound()->assertSee('Menú digital en pausa');

        $inactivo = Restaurante::factory()->create(['estado' => 'suspendido']);
        $this->sitio($inactivo)->assertNotFound();

        $eliminado = $this->restauranteConMenu();
        $eliminado->delete();
        $this->sitio($eliminado)->assertNotFound();
    }

    public function test_el_nombre_con_html_se_escapa(): void
    {
        $restaurante = $this->restauranteConMenu(['nombre' => '<b>Taqueria</b> "x" & </script>']);

        $contenido = $this->sitio($restaurante)->assertOk()->getContent();

        $this->assertStringNotContainsString('<b>Taqueria</b>', $contenido);
        $this->assertStringContainsString(e('<b>Taqueria</b> "x" & </script>'), $contenido);
    }

    public function test_no_hay_scripts_en_linea_ni_atributos_de_evento_ni_estilos_en_linea(): void
    {
        $contenido = $this->sitio($this->restauranteConMenu())->assertOk()->getContent();

        $this->assertSame(0, preg_match('/<script(?![^>]*\ssrc=)/i', $contenido), 'Ningun <script> en linea.');
        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $contenido), 'Ningun atributo de evento en linea.');
        $this->assertSame(0, preg_match('/\sstyle\s*=/i', $contenido), 'Ningun style en linea.');
        $this->assertStringNotContainsString('<style', $contenido);
    }

    public function test_no_usa_recursos_externos(): void
    {
        $contenido = $this->sitio($this->restauranteConMenu())->assertOk()->getContent();
        $sinHost = str_replace(url('/'), '/', $contenido);

        $this->assertSame(0, preg_match('/<(script|link|img|iframe)[^>]+(src|href)="https?:\/\//i', $sinHost));
        $this->assertStringNotContainsString('<iframe', $contenido);
        $this->assertStringNotContainsString('fonts.googleapis', $contenido);
    }

    public function test_no_hay_emojis_en_la_pagina(): void
    {
        $contenido = $this->sitio($this->restauranteConMenu())->assertOk()->getContent();

        $this->assertSame(0, preg_match('/[\x{1F000}-\x{1FFFF}\x{2190}-\x{2BFF}\x{2705}\x{2713}\x{2714}\x{2728}\x{FE0F}]/u', $contenido));
    }

    public function test_sin_whatsapp_muestra_el_telefono_en_lugar_del_envio(): void
    {
        $restaurante = $this->restauranteConMenu(['whatsapp_pedidos' => null]);

        $respuesta = $this->sitio($restaurante)->assertOk();

        $respuesta->assertDontSee('data-whatsapp', false);
        $respuesta->assertSee('Para reservar, llámanos');
        $respuesta->assertSee('tel:+525512345678', false);
    }

    public function test_el_formulario_de_reservacion_tiene_labels_y_lee_datos_desde_atributos_data(): void
    {
        $respuesta = $this->sitio($this->restauranteConMenu())->assertOk();

        foreach (['nombre', 'telefono', 'fecha', 'hora', 'personas', 'notas'] as $campo) {
            $respuesta->assertSee('for="reserva-'.$campo.'"', false);
            $respuesta->assertSee('id="reserva-'.$campo.'"', false);
            $respuesta->assertSee('id="error-'.$campo.'"', false);
        }
        $respuesta->assertSee('data-restaurante=', false);
        $respuesta->assertSee('data-horarios=', false);
    }

    public function test_la_ruta_solo_se_registra_en_local_y_testing(): void
    {
        $this->assertTrue(app()->environment(['local', 'testing']));
        $this->assertNotNull(app('router')->getRoutes()->getByName('sitio.prototipo'));

        $archivo = (string) file_get_contents(base_path('routes/web.php'));
        $this->assertMatchesRegularExpression("/environment\(\['local', 'testing'\]\)[\s\S]*sitio\.prototipo/", $archivo);
    }
}
