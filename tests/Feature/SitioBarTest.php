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
use Tests\TestCase;

/**
 * Plantilla F "Bar": sitio (/{slug}) y menú (/{slug}/menu) con su propio diseño, sin depender del estilo del
 * restaurante ni de menuonl.css. Usa withoutVite() para no depender de un build previo de los assets.
 */
class SitioBarTest extends TestCase
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
            'nombre' => 'Ocaso',
            'slug' => 'ocaso',
            'sitio_plantilla' => 'f',
            'eslogan' => 'Cócteles para cuando cae el sol',
            'zona_horaria' => 'America/Mexico_City',
            ...$atributos,
        ]);
    }

    private function categoria(Restaurante $restaurante, string $nombre): Categoria
    {
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id, 'visible' => true]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => $nombre]);

        return $categoria;
    }

    private function platillo(Restaurante $restaurante, string $nombre, array $atributos = [], ?Categoria $categoria = null): Producto
    {
        $categoria ??= $this->categoria($restaurante, 'Categoría de '.$nombre);
        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id, 'visible' => true, 'disponible' => true, ...$atributos]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => $nombre, 'descripcion' => 'Descripción de '.$nombre]);

        return $producto;
    }

    private function completo(array $atributos = []): Restaurante
    {
        $r = $this->restaurante([
            'whatsapp_pedidos' => '+52 55 1234 5678',
            'pedidos_activos' => true,
            'reservas_activas' => true,
            'descripcion' => 'Coctelería de barra corta.',
            ...$atributos,
        ]);
        $r->forceFill(['portada' => "restaurantes/{$r->id}/portadas/portada.webp"])->saveQuietly();
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'direccion', 'valor' => 'Calle Serdán 118']);
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'telefono', 'valor' => '55 1111 2222']);
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'instagram', 'valor' => '@ocaso.bar']);
        Horario::factory()->create(['restaurante_id' => $r->id, 'dia_semana' => 3, 'abre' => '18:00:00', 'cierra' => '23:59:00', 'cerrado' => false]);

        $clasicos = $this->categoria($r, 'Clásicos');
        $sin = $this->categoria($r, 'Sin alcohol');
        $casa = $this->platillo($r, 'Mar de Cortés', ['precio' => 190, 'precio_oferta' => 170, 'destacado' => true], $clasicos);
        $casa->forceFill(['imagen' => "restaurantes/{$r->id}/productos/mar.webp"])->saveQuietly();
        $casa->etiquetas()->attach(Etiqueta::firstOrCreate(['codigo' => 'nuevo'])->id);
        $this->platillo($r, 'Negroni', ['precio' => 180], $clasicos);
        $this->platillo($r, 'Paloma agotada', ['precio' => 150, 'disponible' => false], $sin);
        $agua = $this->platillo($r, 'Agua fresca', ['precio' => 40], $sin);
        $variante = ProductoVariante::create(['producto_id' => $agua->id, 'precio' => 55, 'disponible' => true, 'orden' => 1]);
        VarianteTraduccion::create(['variante_id' => $variante->id, 'idioma' => 'es', 'nombre' => 'Jarra']);

        return $r;
    }

    private function sitio(string $slug = 'ocaso'): TestResponse
    {
        return $this->get(route('sitio.publico', ['slug' => $slug]));
    }

    private function menu(string $slug = 'ocaso', array $consulta = []): TestResponse
    {
        return $this->get(route('menu.publico', ['slug' => $slug, ...$consulta]));
    }

    public function test_el_catalogo_registra_la_plantilla_f_bar(): void
    {
        $this->assertSame(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'], array_keys(Catalogos::plantillasSitio()));
        $this->assertSame('Bar', Catalogos::plantillasSitio()['f']['nombre']);
    }

    public function test_el_panel_permite_elegir_la_plantilla_f(): void
    {
        $restaurante = Restaurante::factory()->create();
        $usuario = User::factory()->create();
        $restaurante->usuarios()->attach($usuario->id, ['rol' => 'gerente']);

        $this->actingAs($usuario)
            ->patchJson('/api/v1/panel/'.$restaurante->slug.'/ajustes', ['plantilla' => 'f'])
            ->assertOk()->assertJsonPath('data.plantilla', 'f');

        $this->assertDatabaseHas('restaurantes', ['id' => $restaurante->id, 'sitio_plantilla' => 'f']);
    }

    public function test_el_sitio_se_renderiza_con_un_restaurante_completo(): void
    {
        $this->completo();

        $respuesta = $this->sitio()->assertOk()->assertViewIs('sitio.f');

        $respuesta->assertSee('data-plantilla="f"', false)->assertSee('sitio-f', false)
            ->assertSee('Ocaso')->assertSee('Cócteles para cuando cae el sol')->assertSee('Coctelería de barra corta.')
            ->assertSee('Calle Serdán 118')->assertSee('tel:5511112222', false)->assertSee('https://wa.me/525512345678', false)
            ->assertSee('https://instagram.com/ocaso.bar', false)->assertSee('18:00 – 23:59')
            ->assertSee('alt="Portada de Ocaso"', false)->assertSee('Sitio creado con '.config('app.name'))
            ->assertSee('Ver el menú')->assertSee('Reservar mesa')
            ->assertSee(route('menu.publico', ['slug' => 'ocaso']), false);
        $this->assertSame(1, substr_count($respuesta->getContent(), '<h1'));
    }

    public function test_el_destacado_usa_nuevo_o_recomendado_sin_inventar_vocabulario_de_cocteleria(): void
    {
        $this->completo();

        $this->sitio()->assertOk()->assertSee('>Nuevo</h2>', false)->assertSee('Mar de Cortés')->assertDontSee('Cóctel de la casa')->assertDontSee('Carta');

        Etiqueta::where('codigo', 'nuevo')->first()->productos()->detach();
        Cache::flush();

        $this->sitio()->assertOk()->assertSee('>Recomendado</h2>', false);
    }

    public function test_la_vista_previa_oculta_los_agotados_y_muestra_el_precio_vigente(): void
    {
        $this->completo()->productos()->where('id', '>', 0)->get()->each(fn ($p) => $p->forceFill(['destacado' => true])->saveQuietly());

        $this->sitio()->assertOk()->assertSee('$170.00')->assertSee('$190.00')->assertSee('Negroni')->assertDontSee('Paloma agotada');
    }

    public function test_el_horario_marca_el_dia_de_hoy_segun_la_zona_del_restaurante(): void
    {
        $this->travelTo('2026-10-22 01:00:00'); // miércoles 19:00 en Ciudad de México, jueves en UTC
        $this->completo();

        $this->assertSame(1, substr_count($this->sitio()->assertOk()->getContent(), 'bar-horario__fila--hoy'));
        $this->assertStringContainsString('Miércoles<span class="bar-horario__hoy"> (hoy)</span>', (string) $this->sitio()->getContent());
    }

    public function test_el_menu_se_renderiza_con_la_vista_de_la_plantilla_f(): void
    {
        $this->travelTo('2026-10-22 02:00:00'); // miércoles 20:00 en Ciudad de México: abierto
        $this->completo();

        $respuesta = $this->menu()->assertOk()->assertViewIs('menu.plantillas.f');

        $respuesta->assertSee('Mar de Cortés')->assertSee('Negroni')->assertSee('Clásicos')->assertSee('Sin alcohol')
            ->assertSee('$170.00')->assertSee('<s class="bar-precio__antes">', false)->assertSee('$190.00')
            ->assertSee('Agotado')->assertSee('Jarra')->assertSee('$55.00')
            ->assertSee('data-bar-filtros', false)->assertSee('data-bar-grupo', false)->assertSee('Pedir por WhatsApp')
            ->assertSee('Calle Serdán 118')->assertSee('>Menú</h1>', false)
            ->assertSee(route('sitio.publico', ['slug' => 'ocaso']).'#reservar', false);
        $this->assertSame(1, substr_count($respuesta->getContent(), '<h1'));
        $respuesta->assertDontSee('css/menuonl.css', false)->assertDontSee('data-tema', false)->assertDontSee('Carta');
    }

    public function test_sin_javascript_el_menu_conserva_todas_las_categorias_visibles(): void
    {
        $this->completo();

        $html = (string) $this->menu()->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<div class="bar-filtros"[^>]*data-bar-filtros hidden>/', $html);
        $this->assertSame(0, preg_match('/data-bar-grupo="\d+"[^>]*\bhidden\b/', $html));
    }

    public function test_la_mesa_valida_aparece_como_insignia_y_la_invalida_no(): void
    {
        $this->travelTo('2026-10-22 02:00:00'); // miércoles 20:00 en Ciudad de México: abierto
        $this->completo();

        $this->menu('ocaso', ['mesa' => '3'])->assertOk()->assertSee('<span class="bar-mesa">Mesa 3</span>', false)->assertSee('Mesa 3: agrega lo que se te antoje', false);
        $this->menu('ocaso', ['mesa' => '<b>x</b>'])->assertOk()->assertDontSee('class="bar-mesa"', false);
        $this->sitio()->assertOk()->assertDontSee('class="bar-mesa"', false);
    }

    public function test_las_otras_plantillas_siguen_usando_las_mismas_vistas(): void
    {
        foreach (['a', 'b', 'c', 'd', 'e'] as $clave) {
            $restaurante = $this->completo(['sitio_plantilla' => $clave, 'slug' => 'otro-'.$clave]);

            $sitio = $this->sitio('otro-'.$clave)->assertOk()->assertViewIs('sitio.'.$clave);
            $menu = $this->menu('otro-'.$clave)->assertOk()->assertViewIs($clave === 'e' ? 'menu.plantillas.e' : 'menu.publico');
            foreach ([$sitio, $menu] as $respuesta) {
                $this->assertStringNotContainsString('sitio-f', $this->contenido($respuesta));
                $this->assertStringNotContainsString('data-bar-', $this->contenido($respuesta));
                $this->assertStringNotContainsString('class="bar-', $this->contenido($respuesta));
            }
            $restaurante->delete();
        }
    }

    public function test_cambiar_de_f_a_otra_plantilla_vuelve_al_diseno_anterior(): void
    {
        $restaurante = $this->completo();
        $this->menu()->assertViewIs('menu.plantillas.f');

        $restaurante->forceFill(['sitio_plantilla' => 'b'])->saveQuietly();

        $this->sitio()->assertViewIs('sitio.b');
        $this->menu()->assertViewIs('menu.publico');
    }

    public function test_sin_datos_opcionales_se_omiten_las_secciones_sin_textos_de_relleno(): void
    {
        $this->restaurante(['eslogan' => null, 'descripcion' => null, 'portada' => null, 'whatsapp_pedidos' => null, 'reservas_activas' => false]);

        $sitio = $this->sitio()->assertOk();
        foreach (['id="contacto"', 'id="horario"', 'id="ubicacion"', 'id="reservar"', 'bar-portada__eslogan', 'bar-portada__desc', 'bar-casa', 'wa.me', 'tel:', 'Reservar'] as $ausente) {
            $sitio->assertDontSee($ausente, false);
        }
        $sitio->assertSee('bar-portada bar-portada--sin-foto', false);

        $menu = $this->menu()->assertOk();
        foreach (['id="contacto"', 'wa.me', 'data-bar-filtros', 'Pedir por WhatsApp'] as $ausente) {
            $menu->assertDontSee($ausente, false);
        }
        $menu->assertSee('Este menú se está preparando.');
    }

    public function test_un_platillo_sin_foto_no_lleva_miniatura_y_con_foto_si(): void
    {
        $r = $this->restaurante();
        $sinFoto = $this->platillo($r, 'Espresso martini', ['precio' => 160]);
        $conFoto = $this->platillo($r, 'Old fashioned', ['precio' => 170]);
        $conFoto->forceFill(['imagen' => "restaurantes/{$r->id}/productos/of.webp"])->saveQuietly();

        $html = (string) $this->menu()->assertOk()->getContent();

        $this->assertStringContainsString('bar-plato bar-plato--foto', $html);
        $this->assertStringContainsString('alt="Old fashioned"', $html);
        $this->assertStringNotContainsString('alt="Espresso martini"', $html);
        $this->assertSame(1, substr_count($html, 'bar-plato__foto'));
        $this->assertNotNull($sinFoto);
    }

    public function test_el_logo_se_muestra_junto_al_nombre_solo_si_existe(): void
    {
        $r = $this->restaurante(['logo' => null]);
        $this->sitio()->assertOk()->assertDontSee('bar-marca__logo', false);

        $r->forceFill(['logo' => "restaurantes/{$r->id}/logo.webp"])->saveQuietly();
        Cache::flush();

        $this->sitio()->assertOk()->assertSee('bar-marca__logo', false);
    }

    public function test_color_acento_hostil_no_se_inyecta(): void
    {
        // La columna color_acento mide 7 caracteres: en la base solo cabe un valor hostil corto; el largo se prueba en memoria.
        $hostilCorto = '#1;}<s>';
        preg_match("/string\('color_acento', (\d+)\)/", (string) file_get_contents(glob(database_path('migrations/*apariencia_to_restaurantes*'))[0]), $largo);
        $this->assertNotEmpty($largo, 'No se encontró el largo de color_acento en la migración.');
        $this->assertLessThanOrEqual((int) $largo[1], mb_strlen($hostilCorto), 'El valor hostil no cabe en la columna color_acento (MariaDB estricto lo rechazaría).');
        $this->assertNull((new Restaurante(['color_acento' => $hostilCorto]))->colorAcentoSeguro());

        $this->completo(['color_acento' => $hostilCorto]);

        foreach ([$this->sitio(), $this->menu()] as $respuesta) {
            $respuesta->assertOk()->assertDontSee($hostilCorto, false)->assertDontSee('--bar-acento:', false);
        }

        foreach (['#fff;}</style><script>alert(1)</script>', '#12345', '#12345g', 'red', '#1234567'] as $hostil) {
            $restaurante = new Restaurante(['color_acento' => $hostil]);
            $this->assertNull($restaurante->colorAcentoSeguro(), $hostil);
            $this->assertNull($restaurante->colorAcentoTexto(), $hostil);
        }
    }

    public function test_color_acento_legible_se_aplica_y_el_ilegible_se_descarta(): void
    {
        $r = $this->completo(['color_acento' => '#1d4ed8']);

        $this->sitio()->assertOk()->assertSee('--bar-acento: #1d4ed8; --bar-sobre-acento: #ffffff;', false);
        $this->menu()->assertOk()->assertSee('--bar-acento: #1d4ed8;', false);

        $r->forceFill(['color_acento' => '#808080'])->saveQuietly();
        $this->sitio()->assertOk()->assertDontSee('--bar-acento:', false);
    }

    public function test_los_textos_y_los_datos_del_restaurante_van_escapados(): void
    {
        $this->completo(['nombre' => 'Bar <script>alert(1)</script>', 'eslogan' => '"><img src=x onerror=alert(1)>', 'descripcion' => '<b>hola</b>']);

        foreach ([$this->sitio(), $this->menu()] as $respuesta) {
            $respuesta->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x', false)->assertDontSee('<b>hola</b>', false);
        }
    }

    public function test_no_hay_recursos_externos_ni_emojis(): void
    {
        $this->completo();

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

    public function test_la_palabra_carta_no_aparece_en_la_interfaz_de_f(): void
    {
        $archivos = [
            resource_path('views/sitio/f.blade.php'),
            resource_path('views/menu/plantillas/f.blade.php'),
            resource_path('js/sitio-bar.js'),
            resource_path('js/sitio-bar-pedido.js'),
            ...glob(resource_path('views/components/sitio/bar/*.blade.php')),
        ];

        foreach ($archivos as $archivo) {
            $this->assertSame(0, preg_match('/\bcarta\b/i', (string) file_get_contents($archivo)), basename($archivo).' dice "carta"');
        }
    }

    public function test_la_hoja_usa_prefijo_bar_sin_chocar_con_menuonl_css_y_colores_solo_en_variables(): void
    {
        $hoja = (string) file_get_contents(resource_path('css/sitio/f.css'));
        $compartida = (string) file_get_contents(public_path('css/menuonl.css'));

        preg_match_all('/\.([a-z][a-z0-9_-]*)/i', (string) preg_replace('/url\([^)]*\)|"[^"]*"|\d+\.\d+/', '', $hoja), $coincidencias);
        $permitidas = ['foto', 'foto-vacia', 'sitio', 'sitio-f'];

        foreach (array_unique($coincidencias[1]) as $clase) {
            if (in_array($clase, $permitidas, true)) {
                continue;
            }
            $this->assertStringStartsWith('bar-', $clase, "La clase .$clase no lleva el prefijo bar-");
            $this->assertStringNotContainsString('.'.$clase, $compartida, "La clase .$clase choca con menuonl.css");
        }

        $sinRaiz = (string) preg_replace('/:root\s*\{.*?\n\}/s', '', $hoja);
        $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b/', $sinRaiz), 'Hay un color hexadecimal fuera de :root');
        $this->assertSame(0, preg_match('/\b(?:rgb|hsl)a?\(/i', $sinRaiz), 'Hay un color rgb/hsl fuera de :root');
    }

    public function test_las_vistas_de_la_plantilla_no_llevan_colores_sueltos(): void
    {
        $archivos = [
            resource_path('views/sitio/f.blade.php'),
            resource_path('views/menu/plantillas/f.blade.php'),
            ...glob(resource_path('views/components/sitio/bar/*.blade.php')),
        ];

        foreach ($archivos as $archivo) {
            $this->assertSame(0, preg_match('/#[0-9a-fA-F]{6}\b/', (string) file_get_contents($archivo)), basename($archivo).' tiene un color suelto');
        }
    }

    public function test_la_hoja_respeta_movimiento_reducido_transicion_de_vista_y_fuentes_propias(): void
    {
        $hoja = (string) file_get_contents(resource_path('css/sitio/f.css'));

        $this->assertMatchesRegularExpression('/prefers-reduced-motion: no-preference\)\s*\{\s*@view-transition\s*\{\s*navigation: auto;/', $hoja);
        $this->assertStringNotContainsString('http', $hoja);

        preg_match_all('#url\("(/fonts/sitio/[^"]+)"\)#', $hoja, $fuentes);
        $this->assertCount(4, $fuentes[1]);
        foreach ($fuentes[1] as $ruta) {
            $this->assertFileExists(public_path($ruta));
        }
        foreach (['OFL-bebas-neue.txt', 'OFL-space-grotesk.txt'] as $licencia) {
            $this->assertFileExists(public_path('fonts/sitio/'.$licencia));
        }
    }

    public function test_el_selector_de_idioma_del_menu_usa_route_con_lang(): void
    {
        $r = $this->completo();
        RestauranteIdioma::factory()->create(['restaurante_id' => $r->id, 'idioma' => 'en']);

        $this->menu()->assertOk()->assertSee(route('menu.publico', ['slug' => 'ocaso', 'lang' => 'en']), false);
    }

    public function test_sitio_y_menu_no_cambian_con_el_estilo_del_restaurante(): void
    {
        $this->freezeTime();
        $restaurante = $this->completo(['tema' => 'calido']);
        $sitioBase = $this->contenido($this->sitio());
        $menuBase = $this->contenido($this->menu());

        $restaurante->forceFill(['tema' => 'editorial'])->saveQuietly();
        Cache::flush();

        $this->assertSame($sitioBase, $this->contenido($this->sitio()));
        $this->assertSame($menuBase, $this->contenido($this->menu()));
    }

    /**
     * Contenido de la página sin lo que Livewire inyecta (estilos, scripts y tokens), que cambia entre peticiones.
     */
    private function contenido(TestResponse $respuesta): string
    {
        $html = (string) $respuesta->assertOk()->getContent();
        $inicio = (int) strpos($html, '<body');

        return substr($html, $inicio, (int) strrpos($html, '</footer>') - $inicio);
    }
}
