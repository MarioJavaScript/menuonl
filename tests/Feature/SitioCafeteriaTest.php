<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\Contacto;
use App\Models\Etiqueta;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use App\Models\VarianteTraduccion;
use App\Services\Catalogos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Plantilla E "Cafetería": sitio (/{slug}) y menú (/{slug}/menu) con su propio diseño, sin depender del estilo del
 * restaurante ni de menuonl.css. Usa withoutVite() para no depender de un build previo de los assets.
 */
class SitioCafeteriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    private function restaurante(array $atributos = []): Restaurante
    {
        return Restaurante::factory()->create([
            'nombre' => 'Café Mezquite',
            'slug' => 'cafe-mezquite',
            'sitio_plantilla' => 'e',
            'eslogan' => 'Café frío',
            'zona_horaria' => 'America/Mexico_City',
            ...$atributos,
        ]);
    }

    private function platillo(Restaurante $restaurante, string $nombre, array $atributos = [], ?Categoria $categoria = null): Producto
    {
        $categoria ??= Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id, 'visible' => true, 'disponible' => true, ...$atributos]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => $nombre, 'descripcion' => 'Descripción de '.$nombre]);

        return $producto;
    }

    private function categoria(Restaurante $restaurante, string $nombre): Categoria
    {
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id, 'visible' => true]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => $nombre]);

        return $categoria;
    }

    private function restauranteCompleto(array $atributos = []): Restaurante
    {
        $r = $this->restaurante([
            'whatsapp_pedidos' => '+52 55 1234 5678',
            'pedidos_activos' => true,
            'descripcion' => 'Una cafetería pequeña.',
            ...$atributos,
        ]);
        $r->forceFill(['portada' => "restaurantes/{$r->id}/portadas/portada.webp"])->saveQuietly();
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'direccion', 'valor' => 'Calle Real 45']);
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'telefono', 'valor' => '55 1111 2222']);
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'instagram', 'valor' => '@mezquite.cafe']);
        Horario::factory()->create(['restaurante_id' => $r->id, 'dia_semana' => 3, 'abre' => '07:00:00', 'cierra' => '21:00:00', 'cerrado' => false]);

        $helados = $this->categoria($r, 'Café helado');
        $calientes = $this->categoria($r, 'Con leche');
        $coldBrew = $this->platillo($r, 'Cold brew de piloncillo', ['precio' => 92, 'precio_oferta' => 78, 'destacado' => true], $helados);
        $coldBrew->forceFill(['imagen' => "restaurantes/{$r->id}/productos/cold.webp"])->saveQuietly();
        $coldBrew->etiquetas()->attach(Etiqueta::firstOrCreate(['codigo' => 'nuevo'])->id);
        $this->platillo($r, 'Latte', ['precio' => 55], $calientes);
        $this->platillo($r, 'Capuchino agotado', ['precio' => 54, 'disponible' => false], $calientes);
        $grande = $this->platillo($r, 'Chai', ['precio' => 50], $calientes);
        $variante = ProductoVariante::create(['producto_id' => $grande->id, 'precio' => 60, 'disponible' => true, 'orden' => 1]);
        VarianteTraduccion::create(['variante_id' => $variante->id, 'idioma' => 'es', 'nombre' => 'Grande']);

        return $r;
    }

    private function sitio(string $slug = 'cafe-mezquite'): TestResponse
    {
        return $this->get(route('sitio.publico', ['slug' => $slug]));
    }

    private function menu(string $slug = 'cafe-mezquite'): TestResponse
    {
        return $this->get(route('menu.publico', ['slug' => $slug]));
    }

    public function test_el_catalogo_registra_la_plantilla_e_cafeteria(): void
    {
        $this->assertSame(['a', 'b', 'c', 'd', 'e'], array_keys(Catalogos::plantillasSitio()));
        $this->assertSame('Cafetería', Catalogos::plantillasSitio()['e']['nombre']);
    }

    public function test_el_panel_permite_elegir_la_plantilla_e(): void
    {
        $restaurante = Restaurante::factory()->create();
        $usuario = User::factory()->create();
        $restaurante->usuarios()->attach($usuario->id, ['rol' => 'gerente']);

        $this->actingAs($usuario)
            ->patchJson('/api/v1/panel/'.$restaurante->slug.'/ajustes', ['plantilla' => 'e'])
            ->assertOk()->assertJsonPath('data.plantilla', 'e');

        $this->assertDatabaseHas('restaurantes', ['id' => $restaurante->id, 'sitio_plantilla' => 'e']);
    }

    public function test_el_sitio_se_renderiza_con_un_restaurante_completo(): void
    {
        $this->restauranteCompleto();

        $respuesta = $this->sitio()->assertOk();

        $respuesta->assertSee('data-plantilla="e"', false)->assertSee('sitio-e', false)
            ->assertSee('Café Mezquite')->assertSee('Café frío')
            ->assertSee('Calle Real 45')->assertSee('tel:5511112222', false)->assertSee('https://wa.me/525512345678', false)
            ->assertSee('https://instagram.com/mezquite.cafe', false)->assertSee('07:00 – 21:00')
            ->assertSee('alt="Portada de Café Mezquite"', false)
            ->assertSee('Sitio creado con Menuonl')
            ->assertSee(route('menu.publico', ['slug' => 'cafe-mezquite']), false)
            ->assertSee(route('sitio.publico', ['slug' => 'cafe-mezquite']), false);
        $this->assertSame(1, substr_count($respuesta->getContent(), '<h1'));
    }

    public function test_las_palabras_grandes_se_derivan_del_nombre_y_del_eslogan(): void
    {
        $this->restauranteCompleto();

        $this->sitio()->assertOk()
            ->assertSee('cal-portada__palabra cal-portada__palabra--izq" style="--cal-n: 4" aria-hidden="true">Café</p>', false)
            ->assertSee('style="--cal-n: 8" aria-hidden="true">Mezquite</p>', false)
            ->assertSee('cal-portada__acento" aria-hidden="true">Café frío</p>', false);
    }

    public function test_un_nombre_de_una_sola_palabra_y_un_eslogan_largo_no_inventan_texto(): void
    {
        $this->restaurante(['nombre' => 'Mezquite', 'eslogan' => 'Café frío para tardes de cuarenta y cinco grados']);

        $respuesta = $this->sitio()->assertOk();

        $respuesta->assertSee('>Mezquite</p>', false)->assertDontSee('cal-portada__acento', false);
    }

    public function test_el_menu_se_renderiza_con_la_vista_de_la_plantilla_e(): void
    {
        $this->restauranteCompleto();

        $respuesta = $this->menu()->assertOk()->assertViewIs('menu.plantillas.e');

        $respuesta->assertSee('Cold brew de piloncillo')->assertSee('Latte')->assertSee('Café helado')->assertSee('Con leche')
            ->assertSee('$78.00')->assertSee('<s class="cal-precio-antes">', false)->assertSee('$92.00')
            ->assertSee('Agotado')->assertSee('Grande')->assertSee('$60.00')
            ->assertSee('Nuevo')->assertSee('Pedir por WhatsApp')
            ->assertSee('data-cal-pista', false)->assertSee('Calle Real 45')
            ->assertSee(route('sitio.publico', ['slug' => 'cafe-mezquite']), false);
        $this->assertSame(1, substr_count($respuesta->getContent(), '<h1'));
        $respuesta->assertDontSee('css/menuonl.css', false)->assertDontSee('data-tema', false);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function estilos(): array
    {
        return ['cálido' => ['calido'], 'editorial' => ['editorial'], 'rústico' => ['rustico'], 'clásico' => ['clasico']];
    }

    #[DataProvider('estilos')]
    public function test_sitio_y_menu_no_cambian_con_el_estilo_del_restaurante(string $estilo): void
    {
        $restaurante = $this->restauranteCompleto(['tema' => 'calido']);
        $sitioBase = $this->cuerpo($this->sitio());
        $menuBase = $this->cuerpo($this->menu());

        $restaurante->forceFill(['tema' => $estilo])->saveQuietly();
        Cache::flush();

        $this->assertSame($sitioBase, $this->cuerpo($this->sitio()));
        $this->assertSame($menuBase, $this->cuerpo($this->menu()));
    }

    /**
     * Contenido de la página sin lo que Livewire inyecta (estilos, scripts y tokens), que cambia entre peticiones.
     */
    private function cuerpo(TestResponse $respuesta): string
    {
        $html = $respuesta->assertOk()->getContent();
        $inicio = (int) strpos($html, '<body');

        return substr($html, $inicio, (int) strrpos($html, '</footer>') - $inicio);
    }

    public function test_las_otras_plantillas_siguen_usando_las_mismas_vistas(): void
    {
        foreach (['a', 'b', 'c', 'd'] as $clave) {
            $restaurante = $this->restauranteCompleto(['sitio_plantilla' => $clave, 'slug' => 'otro-'.$clave]);

            $this->sitio('otro-'.$clave)->assertOk()->assertViewIs('sitio.'.$clave)->assertSee('data-plantilla="'.$clave.'"', false);
            $this->menu('otro-'.$clave)->assertOk()->assertViewIs('menu.publico')->assertSee('css/menuonl.css', false)->assertDontSee('sitio-e', false);
            $restaurante->delete();
        }
    }

    public function test_cambiar_de_e_a_otra_plantilla_vuelve_al_diseno_anterior(): void
    {
        $restaurante = $this->restauranteCompleto();
        $this->menu()->assertViewIs('menu.plantillas.e');

        $restaurante->forceFill(['sitio_plantilla' => 'b'])->saveQuietly();

        $this->sitio()->assertViewIs('sitio.b');
        $this->menu()->assertViewIs('menu.publico');
    }

    public function test_sin_datos_opcionales_se_omiten_las_secciones_sin_textos_de_relleno(): void
    {
        $this->restaurante(['eslogan' => null, 'descripcion' => null, 'portada' => null, 'whatsapp_pedidos' => null]);

        $sitio = $this->sitio()->assertOk();
        foreach (['id="contacto"', 'cal-portada__acento', 'cal-portada__slogan', 'wa.me', 'tel:', 'cal-icono'] as $ausente) {
            $sitio->assertDontSee($ausente, false);
        }
        $sitio->assertSee('role="img"', false)->assertSee('(foto pendiente)', false);

        $menu = $this->menu()->assertOk();
        foreach (['id="contacto"', 'cal-nueva', 'wa.me', 'data-cal-categorias', 'Pedir por WhatsApp'] as $ausente) {
            $menu->assertDontSee($ausente, false);
        }
        $menu->assertSee('Este menú se está preparando.');
    }

    public function test_un_platillo_sin_foto_muestra_un_recuadro_neutro_y_la_banda_se_omite_sin_destacados(): void
    {
        $r = $this->restaurante();
        $this->platillo($r, 'Espresso', ['precio' => 38]);

        $menu = $this->menu()->assertOk();

        $menu->assertSee('Espresso')->assertSee('foto-vacia', false)->assertDontSee('cal-nueva', false);
    }

    public function test_el_pedido_por_whatsapp_solo_aparece_si_los_pedidos_estan_activos(): void
    {
        $this->restauranteCompleto(['pedidos_activos' => false]);

        $this->menu()->assertOk()->assertDontSee('Pedir por WhatsApp');
    }

    public function test_color_acento_hostil_no_se_inyecta(): void
    {
        $this->restauranteCompleto(['color_acento' => '#fff;}</style><script>alert(1)</script>']);

        foreach ([$this->sitio(), $this->menu()] as $respuesta) {
            $respuesta->assertOk()->assertDontSee('<script>alert(1)', false)->assertDontSee('--cal-acento:', false);
        }
    }

    public function test_color_acento_legible_se_aplica_y_el_ilegible_se_descarta(): void
    {
        $r = $this->restauranteCompleto(['color_acento' => '#1d4ed8']);

        $this->sitio()->assertOk()->assertSee('--cal-acento: #1d4ed8; --cal-acento-texto: #ffffff;', false);
        $this->menu()->assertOk()->assertSee('--cal-acento: #1d4ed8;', false);

        $r->forceFill(['color_acento' => '#808080'])->saveQuietly();
        $this->sitio()->assertOk()->assertDontSee('--cal-acento:', false);
    }

    public function test_el_textos_y_los_datos_del_restaurante_van_escapados(): void
    {
        $this->restauranteCompleto(['nombre' => 'Café <script>alert(1)</script>', 'eslogan' => '"><img src=x onerror=alert(1)>']);

        foreach ([$this->sitio(), $this->menu()] as $respuesta) {
            $respuesta->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x', false);
        }
    }

    public function test_no_hay_recursos_externos_ni_emojis(): void
    {
        $this->restauranteCompleto();

        foreach ([$this->sitio(), $this->menu()] as $respuesta) {
            $html = $respuesta->assertOk()->getContent();

            preg_match_all('#<(?:link|script|img|source|iframe)\b[^>]*\b(?:src|href)="((?:https?:)?//[^"]+)"#i', $html, $cargados);
            $externos = array_filter($cargados[1], fn (string $url): bool => ! str_starts_with($url, url('/')));
            $this->assertSame([], array_values($externos), 'Recurso externo cargado');
            $this->assertStringNotContainsString('@import', $html);
            $this->assertStringNotContainsString('fonts.googleapis', $html);
            $this->assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', $html), 'Emoji en la página');
        }
    }

    public function test_la_hoja_usa_prefijo_cal_sin_chocar_con_menuonl_css_y_colores_solo_en_variables(): void
    {
        $hoja = (string) file_get_contents(resource_path('css/sitio/e.css'));
        $compartida = (string) file_get_contents(public_path('css/menuonl.css'));

        preg_match_all('/\.([a-z][a-z0-9_-]*)/i', (string) preg_replace('/url\([^)]*\)|"[^"]*"|\d+\.\d+/', '', $hoja), $coincidencias);
        $clases = array_unique($coincidencias[1]);
        $permitidas = ['foto', 'foto-vacia', 'sitio', 'sitio-e'];

        foreach ($clases as $clase) {
            if (in_array($clase, $permitidas, true)) {
                continue;
            }
            $this->assertStringStartsWith('cal-', $clase, "La clase .$clase no lleva el prefijo cal-");
            $this->assertStringNotContainsString('.'.$clase, $compartida, "La clase .$clase choca con menuonl.css");
        }

        $sinRaiz = (string) preg_replace('/:root\s*\{.*?\n\}/s', '', $hoja);
        $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b/', $sinRaiz), 'Hay un color hexadecimal fuera de :root');
        $this->assertSame(0, preg_match('/\b(?:rgb|hsl)a?\(/i', $sinRaiz), 'Hay un color rgb/hsl fuera de :root');
    }

    public function test_las_vistas_de_la_plantilla_no_llevan_colores_sueltos(): void
    {
        $archivos = [
            resource_path('views/sitio/e.blade.php'),
            resource_path('views/menu/plantillas/e.blade.php'),
            ...glob(resource_path('views/components/sitio/cafeteria/*.blade.php')),
        ];

        foreach ($archivos as $archivo) {
            $this->assertSame(0, preg_match('/#[0-9a-fA-F]{6}\b/', (string) file_get_contents($archivo)), basename($archivo).' tiene un color suelto');
        }
    }

    public function test_la_hoja_respeta_movimiento_reducido_transicion_de_vista_y_fuentes_propias(): void
    {
        $hoja = (string) file_get_contents(resource_path('css/sitio/e.css'));

        $this->assertStringContainsString('@view-transition { navigation: auto; }', $hoja);
        $this->assertStringContainsString('prefers-reduced-motion: no-preference', $hoja);
        $this->assertStringNotContainsString('http', $hoja);

        preg_match_all('#url\("(/fonts/sitio/[^"]+)"\)#', $hoja, $fuentes);
        $this->assertCount(3, $fuentes[1]);
        foreach ($fuentes[1] as $ruta) {
            $this->assertFileExists(public_path($ruta));
        }
        foreach (['OFL-Syne.txt', 'OFL-PlayfairDisplay.txt', 'OFL-DMSans.txt'] as $licencia) {
            $this->assertFileExists(public_path('fonts/sitio/'.$licencia));
        }
    }

    public function test_el_selector_de_idioma_del_menu_usa_route_con_lang(): void
    {
        $r = $this->restauranteCompleto();
        RestauranteIdioma::factory()->create(['restaurante_id' => $r->id, 'idioma' => 'en']);

        $this->menu()->assertOk()->assertSee(route('menu.publico', ['slug' => 'cafe-mezquite', 'lang' => 'en']), false);
    }
}
