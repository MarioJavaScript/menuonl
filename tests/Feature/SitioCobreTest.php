<?php

namespace Tests\Feature;

use App\Filament\Pages\Apariencia;
use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\Contacto;
use App\Models\Etiqueta;
use App\Models\GaleriaImagen;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Models\Scopes\RestauranteScope;
use App\Models\VarianteTraduccion;
use App\Services\AparienciaSitio;
use App\Services\Catalogos;
use App\Services\SitioPublicoDatos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Plantilla O "Negro y cobre": sitio (/{slug}) y menú (/{slug}/menu) con su propio diseño, sin depender del estilo del
 * restaurante ni de menuonl.css. Usa withoutVite() para no depender de un build previo de los assets.
 */
class SitioCobreTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    /** Miércoles 20:00 en Ciudad de México: dentro del horario de completo(). */
    private const ABIERTO = '2026-10-22 02:00:00';

    /** Miércoles 12:00 en Ciudad de México: antes de abrir. */
    private const CERRADO = '2026-10-21 18:00:00';

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

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function restaurante(array $atributos = []): Restaurante
    {
        return Restaurante::factory()->create([
            'nombre' => 'Verdelux',
            'slug' => 'verdelux',
            'sitio_plantilla' => 'o',
            'eslogan' => 'Brasa y humo',
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

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function platillo(Restaurante $restaurante, string $nombre, array $atributos = [], ?Categoria $categoria = null): Producto
    {
        $categoria ??= $this->categoria($restaurante, 'Categoría de '.$nombre);
        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id, 'visible' => true, 'disponible' => true, ...$atributos]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => $nombre, 'descripcion' => 'Descripción de '.$nombre]);

        return $producto;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function completo(array $atributos = []): Restaurante
    {
        $r = $this->restaurante([
            'whatsapp_pedidos' => '+52 55 1234 5678',
            'pedidos_activos' => true,
            'reservas_activas' => true,
            'descripcion' => 'Cena de autor.',
            ...$atributos,
        ]);
        $r->forceFill(['portada' => "restaurantes/{$r->id}/portadas/portada.webp"])->saveQuietly();
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'direccion', 'valor' => 'Calle de ejemplo 321']);
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'telefono', 'valor' => '66 2111 2222']);
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'instagram', 'valor' => '@labahia.mar']);
        Horario::factory()->create(['restaurante_id' => $r->id, 'dia_semana' => 3, 'abre' => '18:00:00', 'cierra' => '23:59:00', 'cerrado' => false]);

        $naturales = $this->categoria($r, 'Parrilla');
        $bebidas = $this->categoria($r, 'Bebidas');
        $casa = $this->platillo($r, 'Rib eye', ['precio' => 190, 'precio_oferta' => 175, 'destacado' => true], $naturales);
        $casa->forceFill(['imagen' => "restaurantes/{$r->id}/productos/casa.webp"])->saveQuietly();
        $casa->etiquetas()->attach(Etiqueta::firstOrCreate(['codigo' => 'picante'])->id);
        $this->platillo($r, 'Arrachera', ['precio' => 140], $naturales);
        $this->platillo($r, 'Tuétano agotado', ['precio' => 60, 'disponible' => false], $bebidas);
        $te = $this->platillo($r, 'Vino de la casa', ['precio' => 40], $bebidas);
        $variante = ProductoVariante::create(['producto_id' => $te->id, 'precio' => 70, 'disponible' => true, 'orden' => 1]);
        VarianteTraduccion::create(['variante_id' => $variante->id, 'idioma' => 'es', 'nombre' => 'Botella']);

        return $r;
    }

    private function sitio(string $slug = 'verdelux'): TestResponse
    {
        return $this->get(route('sitio.publico', ['slug' => $slug]));
    }

    /**
     * @param  array<string, string>  $consulta
     */
    private function menu(string $slug = 'verdelux', array $consulta = []): TestResponse
    {
        return $this->get(route('menu.publico', ['slug' => $slug, ...$consulta]));
    }

    public function test_el_catalogo_registra_la_plantilla_o_negro_y_cobre(): void
    {
        $this->assertSame(['b', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o'], array_keys(Catalogos::plantillasSitio()));
        $this->assertSame('Negro y cobre', Catalogos::plantillasSitio()['o']['nombre']);
        $this->assertSame('o', AparienciaSitio::plantillaVigente('o'));
    }

    public function test_el_panel_tiene_la_ficha_y_no_ofrece_el_estilo_del_menu(): void
    {
        $ficha = AparienciaSitio::fichas()['o'] ?? null;

        $this->assertNotNull($ficha, 'Falta la ficha de la plantilla o en AparienciaSitio::fichas().');
        $this->assertSame('Negro y cobre', $ficha['nombre']);
        $this->assertNotSame('', $ficha['para']);
        $this->assertNotContains('o', AparienciaSitio::PLANTILLAS_CON_ESTILO);
        $this->assertFalse(AparienciaSitio::usaEstilo('o'));

        $hoja = (string) file_get_contents(resource_path('css/sitio/o.css'));
        foreach (['fondo' => '--cob-negro', 'texto' => '--cob-crema', 'acento' => '--cob-cobre', 'bloque' => '--cob-cobre-texto'] as $campo => $variable) {
            $this->assertStringContainsString($variable.': '.$ficha[$campo].';', $hoja, "El color '{$campo}' de la ficha no sale de la hoja.");
        }
    }

    public function test_admin_permite_elegir_la_plantilla_o(): void
    {
        $restaurante = Restaurante::factory()->create();
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Apariencia::class)
            ->assertSee('Negro y cobre')
            ->set('data.sitio_plantilla', 'o')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('restaurantes', ['id' => $restaurante->id, 'sitio_plantilla' => 'o']);
    }

    public function test_el_sitio_se_renderiza_con_un_restaurante_completo(): void
    {
        $this->travelTo(self::ABIERTO);
        $this->completo();

        $respuesta = $this->sitio()->assertOk()->assertViewIs('sitio.o');

        $respuesta->assertSee('data-plantilla="o"', false)->assertSee('sitio-o', false)
            ->assertSee('Verdelux')->assertSee('Brasa y humo')->assertSee('Cena de autor.')
            ->assertSee('Calle de ejemplo 321')->assertSee('tel:6621112222', false)->assertSee('https://wa.me/525512345678', false)
            ->assertSee('https://instagram.com/labahia.mar', false)->assertSee('18:00 – 23:59')
            ->assertSee('alt="Foto de Verdelux"', false)->assertSee('Sitio creado con '.config('app.name'))
            ->assertSee('Ver menú y pedir')->assertSee('>Reservar mesa</a>', false)->assertSee('>Reservar</a>', false)->assertSee('Reserva tu mesa')->assertSee('Visítanos')->assertSee('Cómo llegar')
            ->assertSee('<h2 id="destacados-titulo">Recomendados</h2>', false)->assertSee('Descripción de Rib eye')
            ->assertSee(route('menu.publico', ['slug' => 'verdelux']), false)
            ->assertDontSee('Smash al carbón')->assertDontSee('Foto del platillo')->assertDontSee('Una noche para recordar')->assertDontSee('Selección del chef')->assertDontSee('Cocina de autor')->assertDontSee('Lo más pedido')->assertDontSee('cob-cinta', false)->assertDontSee('Pedir ahora')->assertDontSee('Te confirmamos por WhatsApp')->assertDontSee('Hecho con Menubly')->assertDontSee('cierra')->assertDontSee('Foto de portada')->assertDontSee('cob-kanji', false);
        $this->assertSame(1, substr_count($respuesta->getContent(), '<h1'));
    }

    public function test_abierto_ahora_y_cerrado_ahora_salen_del_horario_del_restaurante(): void
    {
        $this->completo();

        $this->travelTo(self::ABIERTO);
        $this->sitio()->assertOk()->assertSee('<p class="cob-estado cob-estado--abierto">Abierto ahora</p>', false)->assertSee('Hoy: 18:00 – 23:59');
        $this->menu()->assertOk()->assertSee('<p class="cob-estado cob-estado--abierto">Abierto</p>', false);

        Cache::flush();
        $this->travelTo(self::CERRADO);
        $this->sitio()->assertOk()->assertSee('<p class="cob-estado">Cerrado ahora</p>', false)->assertDontSee('Abierto ahora');
        $this->menu()->assertOk()->assertSee('<p class="cob-estado">Cerrado</p>', false);
    }

    public function test_sin_pedidos_el_boton_de_portada_no_promete_pedir(): void
    {
        $this->completo(['pedidos_activos' => false]);

        $this->sitio()->assertOk()->assertSee('>Ver el menú</a>', false)->assertDontSee('Ver menú y pedir')->assertDontSee('Pedir ahora');
    }

    public function test_los_recomendados_ocultan_los_agotados_y_muestran_el_precio_vigente(): void
    {
        $this->completo()->productos()->where('id', '>', 0)->get()->each(fn ($p) => $p->forceFill(['destacado' => true])->saveQuietly());

        $this->sitio()->assertOk()
            ->assertSee('<h2 id="destacados-titulo">Recomendados</h2>', false)
            ->assertSee('$175.00')->assertSee('<s class="cob-precio__antes">', false)->assertSee('$190.00')
            ->assertSee('Arrachera')->assertSee('Picante')->assertDontSee('Tuétano agotado');
    }

    public function test_el_horario_marca_el_dia_de_hoy_segun_la_zona_del_restaurante(): void
    {
        $this->travelTo('2026-10-22 01:00:00'); // miércoles 19:00 en Ciudad de México, jueves en UTC
        $this->completo();

        $html = (string) $this->sitio()->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'aria-current="date"'));
        $this->assertStringContainsString('Miércoles<span class="cob-solo-lectores"> (hoy)</span>', $html);
    }

    public function test_la_galeria_sigue_el_orden_y_usa_la_descripcion_escapada_como_texto_alternativo(): void
    {
        $r = $this->completo();
        GaleriaImagen::factory()->create(['restaurante_id' => $r->id, 'imagen' => "restaurantes/{$r->id}/galeria/tercera.webp", 'orden' => 3, 'descripcion' => null]);
        GaleriaImagen::factory()->create(['restaurante_id' => $r->id, 'imagen' => "restaurantes/{$r->id}/galeria/primera.webp", 'orden' => 1, 'descripcion' => 'Barra de madera']);
        GaleriaImagen::factory()->create(['restaurante_id' => $r->id, 'imagen' => "restaurantes/{$r->id}/galeria/segunda.webp", 'orden' => 2, 'descripcion' => '"><script>alert(1)</script>']);

        $respuesta = $this->sitio()->assertOk()
            ->assertSee('id="galeria"', false)->assertSee('href="#galeria">Galería</a>', false)
            ->assertSee('alt="Barra de madera"', false)
            ->assertSee('alt="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', false)
            ->assertSee('alt="Foto de Verdelux"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $respuesta->assertSeeInOrder(['galeria/primera.webp', 'galeria/segunda.webp', 'galeria/tercera.webp'], false);

        $this->menu()->assertOk()->assertSee(route('sitio.publico', ['slug' => 'verdelux']).'#galeria', false);
    }

    public function test_sin_fotos_de_galeria_no_hay_seccion_ni_enlace(): void
    {
        $this->completo();

        $this->sitio()->assertOk()->assertDontSee('id="galeria"', false)->assertDontSee('#galeria', false)->assertDontSee('>Galería<', false);
    }

    public function test_la_galeria_de_la_plantilla_b_sigue_igual(): void
    {
        $r = $this->completo(['sitio_plantilla' => 'b']);
        GaleriaImagen::factory()->create(['restaurante_id' => $r->id, 'imagen' => "restaurantes/{$r->id}/galeria/segunda.webp", 'orden' => 2, 'descripcion' => 'Barra de madera']);
        GaleriaImagen::factory()->create(['restaurante_id' => $r->id, 'imagen' => "restaurantes/{$r->id}/galeria/primera.webp", 'orden' => 1, 'descripcion' => null]);

        $this->sitio()->assertOk()->assertViewIs('sitio.b')
            ->assertSee('alt="Foto 1 de Verdelux"', false)->assertSee('alt="Foto 2 de Verdelux"', false)
            ->assertDontSee('Barra de madera')
            ->assertSeeInOrder(['galeria/primera.webp', 'galeria/segunda.webp'], false);

        RestauranteScope::setRestauranteId($r->id);
        $datos = app(SitioPublicoDatos::class)->para($r);
        $this->assertSame(["restaurantes/{$r->id}/galeria/primera.webp", "restaurantes/{$r->id}/galeria/segunda.webp"], $datos['galeria']);
        $this->assertSame([
            ['imagen' => "restaurantes/{$r->id}/galeria/primera.webp", 'descripcion' => null],
            ['imagen' => "restaurantes/{$r->id}/galeria/segunda.webp", 'descripcion' => 'Barra de madera'],
        ], $datos['galeria_fotos']);
    }

    public function test_el_menu_se_renderiza_con_la_vista_de_la_plantilla_o(): void
    {
        $this->travelTo(self::ABIERTO);
        $r = $this->completo();
        $categorias = Categoria::where('restaurante_id', $r->id)->orderBy('id')->get();

        $respuesta = $this->menu()->assertOk()->assertViewIs('menu.plantillas.o');

        $respuesta->assertSee('Rib eye')->assertSee('Arrachera')->assertSee('Parrilla')->assertSee('Bebidas')
            ->assertSee('$175.00')->assertSee('<s class="cob-precio__antes">', false)->assertSee('$190.00')
            ->assertSee('Agotado')->assertSee('Botella')->assertSee('$70.00')
            ->assertSee('data-cob-categorias', false)->assertSee('Pedir por WhatsApp')
            ->assertSee('Calle de ejemplo 321')->assertSee('>Menú</h1>', false)
            ->assertSee(route('sitio.publico', ['slug' => 'verdelux']).'#reservar', false)
            ->assertSee('aria-label="Agregar Arrachera al pedido"', false);
        foreach ($categorias as $categoria) {
            $respuesta->assertSee('href="#categoria-'.$categoria->id.'"', false)->assertSee('id="categoria-'.$categoria->id.'"', false);
        }
        $this->assertSame(1, substr_count($respuesta->getContent(), '<h1'));
        $respuesta->assertDontSee('css/menuonl.css', false)->assertDontSee('data-tema', false)->assertDontSee('Carta')->assertDontSee('cob-kanji', false);
    }

    public function test_con_una_sola_categoria_no_hay_barra_de_categorias(): void
    {
        $r = $this->restaurante();
        $categoria = $this->categoria($r, 'Entradas');
        $this->platillo($r, 'Queso fundido', ['precio' => 165], $categoria);
        $this->platillo($r, 'Frijoles charros', ['precio' => 170], $categoria);

        $this->menu()->assertOk()->assertSee('Queso fundido')->assertDontSee('data-cob-categorias', false);
    }

    public function test_la_mesa_se_ignora_por_completo(): void
    {
        $this->travelTo(self::ABIERTO);
        $this->completo();
        $base = $this->contenido($this->menu());

        foreach (['3', '<b>x</b>'] as $mesa) {
            $respuesta = $this->menu('verdelux', ['mesa' => $mesa])->assertOk()
                ->assertDontSee('Mesa 3')->assertDontSee('cob-mesa', false)->assertDontSee('data-mesa', false)->assertDontSee('id="pedido-mesa"', false);
            $this->assertSame($base, $this->contenido($respuesta), 'El parámetro ?mesa= cambió el menú.');
        }
        $this->sitio()->assertOk()->assertDontSee('cob-mesa', false);
    }

    public function test_las_otras_plantillas_siguen_usando_las_mismas_vistas(): void
    {
        foreach (['b', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm'] as $clave) {
            $restaurante = $this->completo(['sitio_plantilla' => $clave, 'slug' => 'otro-'.$clave]);

            $sitio = $this->sitio('otro-'.$clave)->assertOk()->assertViewIs('sitio.'.$clave);
            $menu = $this->menu('otro-'.$clave)->assertOk()->assertViewIs(in_array($clave, ['e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm'], true) ? 'menu.plantillas.'.$clave : 'menu.publico');
            foreach ([$sitio, $menu] as $respuesta) {
                $this->assertDoesNotMatchRegularExpression('/\bsitio-o\b/', $this->contenido($respuesta));
                $this->assertStringNotContainsString('data-cob-', $this->contenido($respuesta));
                $this->assertStringNotContainsString('class="cob-', $this->contenido($respuesta));
                $this->assertStringNotContainsString('menuonl_cart_cob', $this->contenido($respuesta));
            }
            $restaurante->delete();
        }
    }

    public function test_cambiar_de_o_a_otra_plantilla_vuelve_al_diseno_anterior(): void
    {
        $restaurante = $this->completo();
        $this->menu()->assertViewIs('menu.plantillas.o');

        $restaurante->forceFill(['sitio_plantilla' => 'b'])->saveQuietly();

        $this->sitio()->assertViewIs('sitio.b');
        $this->menu()->assertViewIs('menu.publico');
    }

    public function test_sin_datos_opcionales_se_omiten_las_secciones_sin_textos_de_relleno(): void
    {
        $this->restaurante(['eslogan' => null, 'descripcion' => null, 'portada' => null, 'whatsapp_pedidos' => null, 'reservas_activas' => false]);

        $sitio = $this->sitio()->assertOk();
        foreach (['id="visitanos"', 'id="horario"', 'id="ubicacion"', 'id="reservar"', 'id="destacados"', 'id="galeria"', 'cob-portada__foto', 'cob-numero', 'cob-portada__eslogan', 'cob-portada__descripcion', 'Hoy:', 'cob-estado', 'wa.me', 'tel:', 'Reservar', '>Horario<', '>Galería<'] as $ausente) {
            $sitio->assertDontSee($ausente, false);
        }
        $sitio->assertSee('>Ver el menú</a>', false);

        $menu = $this->menu()->assertOk();
        foreach (['id="visitanos"', 'wa.me', 'data-cob-categorias', 'Pedir por WhatsApp', 'data-cob-pedido'] as $ausente) {
            $menu->assertDontSee($ausente, false);
        }
        $menu->assertSee('Este menú se está preparando.');
    }

    public function test_un_platillo_sin_foto_no_dibuja_recuadro_y_con_foto_muestra_una_miniatura(): void
    {
        $r = $this->restaurante();
        $this->platillo($r, 'Papa al horno', ['precio' => 155]);
        $conFoto = $this->platillo($r, 'Tomahawk', ['precio' => 120]);
        $conFoto->forceFill(['imagen' => "restaurantes/{$r->id}/productos/tomahawk.webp"])->saveQuietly();

        $html = (string) $this->menu()->assertOk()->getContent();

        $this->assertStringContainsString('alt="Tomahawk"', $html);
        $this->assertStringNotContainsString('foto-vacia', $html);
        $this->assertStringNotContainsString('foto pendiente', $html);
        $this->assertSame(1, substr_count($html, 'cob-fila__foto'));
        $this->assertStringContainsString('width: 4.75rem; height: 4.75rem', (string) file_get_contents(resource_path('css/sitio/o.css')));
    }

    public function test_el_logo_se_muestra_junto_al_nombre_solo_si_existe_y_con_tamano_fijo(): void
    {
        $r = $this->restaurante(['logo' => null]);
        foreach ([$this->sitio(), $this->menu()] as $respuesta) {
            $respuesta->assertOk()->assertDontSee('cob-marca__logo', false)->assertSee('<span>Verdelux</span>', false);
        }

        $r->forceFill(['logo' => "restaurantes/{$r->id}/logo.webp"])->saveQuietly();
        Cache::flush();

        foreach ([$this->sitio(), $this->menu()] as $respuesta) {
            $respuesta->assertOk()->assertSee('cob-marca__logo', false)->assertSee('<span>Verdelux</span>', false);
        }
        $this->assertStringContainsString('.cob-pagina .cob-marca__logo { flex: none; width: auto; height: auto; max-width: 2.75rem; max-height: 2.75rem;', (string) file_get_contents(resource_path('css/sitio/o.css')));
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
            $respuesta->assertOk()->assertDontSee($hostilCorto, false)->assertDontSee('--cob-acento:', false);
        }

        foreach (['#fff;}</style><script>alert(1)</script>', '#12345', '#12345g', 'red', '#1234567'] as $hostil) {
            $restaurante = new Restaurante(['color_acento' => $hostil]);
            $this->assertNull($restaurante->colorAcentoSeguro(), $hostil);
            $this->assertNull($restaurante->colorAcentoTexto(), $hostil);
        }
    }

    public function test_color_acento_legible_se_aplica_y_el_ilegible_se_descarta(): void
    {
        $r = $this->completo(['color_acento' => '#1f5f3f']);

        $this->sitio()->assertOk()->assertSee('--cob-acento: #1f5f3f; --cob-acento-hover: #1f5f3f; --cob-texto-acento: #ffffff;', false);
        $this->menu()->assertOk()->assertSee('--cob-acento: #1f5f3f;', false);

        $r->forceFill(['color_acento' => '#808080'])->saveQuietly();
        Cache::flush();
        $this->sitio()->assertOk()->assertDontSee('--cob-acento:', false);
    }

    public function test_los_textos_y_los_datos_del_restaurante_van_escapados(): void
    {
        $this->completo(['nombre' => 'Verdelux <script>alert(1)</script>', 'eslogan' => '"><img src=x onerror=alert(1)>', 'descripcion' => '<b>hola</b>']);

        foreach ([$this->sitio(), $this->menu()] as $respuesta) {
            $respuesta->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x', false)->assertDontSee('<b>hola</b>', false);
        }
    }

    public function test_no_hay_recursos_externos_ni_emojis(): void
    {
        $this->travelTo(self::ABIERTO);
        $r = $this->completo();
        GaleriaImagen::factory()->create(['restaurante_id' => $r->id, 'orden' => 1, 'descripcion' => 'Barra']);

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

    public function test_los_enlaces_salen_de_rutas_con_nombre(): void
    {
        $this->travelTo(self::ABIERTO);
        $this->completo();
        $inicio = route('sitio.publico', ['slug' => 'verdelux']);
        $menu = route('menu.publico', ['slug' => 'verdelux']);

        $sitio = (string) $this->sitio()->assertOk()->getContent();
        $carta = (string) $this->menu()->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#href="'.preg_quote($menu, '#').'"\s+aria-current="page"#', $carta);
        $this->assertStringContainsString('href="'.$inicio.'"', $sitio);
        $this->assertStringContainsString('href="'.$inicio.'#visitanos">Horario</a>', $carta);
        $this->assertStringContainsString('href="#visitanos">Horario</a>', $sitio);

        foreach ([$sitio, $carta] as $html) {
            preg_match_all('/href="([^"]+)"/', $html, $enlaces);
            foreach ($enlaces[1] as $enlace) {
                $valido = str_starts_with($enlace, '#') || str_starts_with($enlace, url('/')) || str_starts_with($enlace, '/fonts/')
                    || str_starts_with($enlace, 'tel:') || str_starts_with($enlace, 'https://wa.me/') || str_starts_with($enlace, 'https://www.google.com/maps/')
                    || str_starts_with($enlace, 'https://instagram.com/') || str_starts_with($enlace, '/favicon') || str_starts_with($enlace, '/apple-touch') || str_starts_with($enlace, '/site.webmanifest');
                $this->assertTrue($valido, "Enlace inesperado: {$enlace}");
            }
        }
    }

    public function test_la_palabra_carta_no_aparece_en_la_interfaz_de_o(): void
    {
        $archivos = [
            resource_path('views/sitio/o.blade.php'),
            resource_path('views/menu/plantillas/o.blade.php'),
            resource_path('js/sitio-cobre.js'),
            resource_path('js/sitio-cobre-pedido.js'),
            ...glob(resource_path('views/components/sitio/cobre/*.blade.php')),
        ];

        foreach ($archivos as $archivo) {
            $this->assertSame(0, preg_match('/\bcarta\b/i', (string) file_get_contents($archivo)), basename($archivo).' dice "carta"');
        }
    }

    public function test_la_hoja_usa_prefijo_cob_sin_chocar_con_menuonl_css_y_colores_solo_en_variables(): void
    {
        $hoja = $this->hojaSinComentarios();
        $compartida = (string) file_get_contents(public_path('css/menuonl.css'));

        preg_match_all('/\.([a-z][a-z0-9_-]*)/i', (string) preg_replace('/url\([^)]*\)|"[^"]*"|\d+\.\d+/', '', $hoja), $coincidencias);
        $permitidas = ['foto', 'foto-vacia', 'sitio', 'sitio-o'];

        foreach (array_unique($coincidencias[1]) as $clase) {
            if (in_array($clase, $permitidas, true)) {
                continue;
            }
            $this->assertStringStartsWith('cob-', $clase, "La clase .$clase no lleva el prefijo cob-");
            $this->assertStringNotContainsString('.'.$clase, $compartida, "La clase .$clase choca con menuonl.css");
        }

        $sinRaiz = (string) preg_replace('/:root\s*\{.*?\n\}/s', '', $hoja);
        $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b/', $sinRaiz), 'Hay un color hexadecimal fuera de :root');
        $this->assertSame(0, preg_match('/\b(?:rgb|hsl)a?\(/i', $sinRaiz), 'Hay un color rgb/hsl fuera de :root');
    }

    private function hojaSinComentarios(): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(resource_path('css/sitio/o.css')));
    }

    /**
     * Colores de :root de la hoja, con los alias (var(--otro)) ya resueltos.
     *
     * @return array<string, string>
     */
    private function coloresDeLaHoja(): array
    {
        $hoja = $this->hojaSinComentarios();
        preg_match_all('/--(cob-[a-z0-9-]+): ([^;]+);/', $hoja, $definidos);
        $valores = array_combine($definidos[1], array_map('trim', $definidos[2]));
        $resolver = function (string $nombre) use (&$resolver, $valores): ?string {
            $valor = $valores[$nombre] ?? null;
            if ($valor !== null && preg_match('/^var\(--(cob-[a-z0-9-]+)\)$/', $valor, $alias)) {
                return $resolver($alias[1]);
            }

            return $valor !== null && preg_match('/^#[0-9a-f]{6}$/', $valor) ? $valor : null;
        };

        return array_filter(array_combine(array_keys($valores), array_map($resolver, array_keys($valores))));
    }

    private function contraste(string $a, string $b): float
    {
        $luz = function (string $hex): float {
            [$r, $g, $bl] = array_map(fn (string $par): float => hexdec($par) / 255, str_split(ltrim($hex, '#'), 2));
            $lineal = fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

            return 0.2126 * $lineal($r) + 0.7152 * $lineal($g) + 0.0722 * $lineal($bl);
        };

        return (max($luz($a), $luz($b)) + 0.05) / (min($luz($a), $luz($b)) + 0.05);
    }

    public function test_los_pares_de_la_hoja_llegan_a_4_5_para_texto_y_3_para_bordes(): void
    {
        $c = $this->coloresDeLaHoja();

        // Texto (4.5:1) sobre cada superficie donde vive: página (negro), paneles (tarjetas, cajón, cabecera del menú) y campos (negro).
        $textos = [
            ['cob-crema', 'cob-negro'], ['cob-crema', 'cob-panel'], ['cob-suave', 'cob-negro'], ['cob-suave', 'cob-panel'],
            ['cob-mudo', 'cob-negro'], ['cob-mudo', 'cob-panel'], ['cob-cobre-texto', 'cob-negro'], ['cob-cobre-texto', 'cob-panel'],
            ['cob-abierto', 'cob-negro'], ['cob-abierto', 'cob-panel'], ['cob-cerrado', 'cob-negro'], ['cob-cerrado', 'cob-panel'],
            ['cob-error', 'cob-negro'], ['cob-error', 'cob-panel'],
            // Texto oscuro sobre los botones de color: el cobre, su hover y el cobre claro del hover del botón crema.
            ['cob-negro', 'cob-cobre'], ['cob-negro', 'cob-cobre-hover'], ['cob-negro', 'cob-cobre-texto'], ['cob-negro', 'cob-crema'],
        ];
        foreach ($textos as [$texto, $fondo]) {
            $this->assertGreaterThanOrEqual(4.5, $this->contraste($c[$texto], $c[$fondo]), "{$texto} sobre {$fondo} no llega a 4.5:1");
        }

        // Bordes y componentes de interfaz (3:1): campos, categorías, cantidades y etiquetas contra el panel y la página; el cobre del
        // botón "+" contra la tarjeta; el punto de "Abierto"; el contorno crema de los botones.
        $bordes = [
            ['cob-borde-ui', 'cob-negro'], ['cob-borde-ui', 'cob-panel'], ['cob-cobre', 'cob-negro'], ['cob-cobre', 'cob-panel'],
            ['cob-abierto-punto', 'cob-negro'], ['cob-abierto-punto', 'cob-panel'], ['cob-crema', 'cob-negro'], ['cob-crema', 'cob-panel'],
        ];
        foreach ($bordes as [$borde, $fondo]) {
            $this->assertGreaterThanOrEqual(3.0, $this->contraste($c[$borde], $c[$fondo]), "{$borde} contra {$fondo} no llega a 3:1");
        }
    }

    public function test_el_acento_del_restaurante_y_el_cobre_de_relleno_nunca_son_color_de_texto(): void
    {
        $sinRaiz = (string) preg_replace('/:root\s*\{.*?\n\}/s', '', $this->hojaSinComentarios());
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $sinRaiz, $reglas, PREG_SET_ORDER);

        // El texto de precios, números y enlaces usa --cob-cobre-texto; --cob-cobre (relleno) y --cob-acento (propio del restaurante)
        // solo son fondo, borde o el color de la cifra del contador (que va sobre --cob-texto-acento).
        $acentoComoTexto = ['.cob-barra__cuenta'];
        $vistoCobreTexto = 0;
        foreach ($reglas as [, $selector, $cuerpo]) {
            $selector = trim((string) preg_replace('/\s+/', ' ', $selector));
            $this->assertDoesNotMatchRegularExpression('/(?<![-a-z])color:\s*var\(--cob-cobre\)/', $cuerpo, "El cobre de relleno se usa como texto en {$selector}.");
            if (preg_match('/(?<![-a-z])color:\s*var\(--cob-acento(?:-hover)?\)/', $cuerpo)) {
                $this->assertContains($selector, $acentoComoTexto, "El acento se usa como texto en {$selector}.");
            }
            if (preg_match('/(?<![-a-z])color:\s*var\(--cob-cobre-texto\)/', $cuerpo)) {
                $vistoCobreTexto++;
            }
        }
        $this->assertGreaterThanOrEqual(8, $vistoCobreTexto);
    }

    public function test_ningun_hover_deja_el_texto_igual_al_fondo_en_ninguna_superficie(): void
    {
        $c = $this->coloresDeLaHoja();
        $sinRaiz = (string) preg_replace('/:root\s*\{.*?\n\}/s', '', $this->hojaSinComentarios());
        // Los alias del acento resuelven al cobre por defecto; con acento propio el hover es el mismo color (ver test del acento).
        $acentos = ['cob-acento' => 'cob-cobre', 'cob-acento-hover' => 'cob-cobre-hover', 'cob-texto-acento' => 'cob-negro'];
        $superficies = ['cob-negro', 'cob-panel'];

        $color = function (string $valor) use ($c, $acentos): ?string {
            return preg_match('/^var\(--(cob-[a-z0-9-]+)\)$/', trim($valor), $m) ? ($c[$acentos[$m[1]] ?? $m[1]] ?? null) : null;
        };

        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $sinRaiz, $reglas, PREG_SET_ORDER);
        $medidos = 0;
        foreach ($reglas as [, $selector, $cuerpo]) {
            if (! str_contains($selector, ':hover')) {
                continue;
            }
            $textoCrudo = preg_match('/(?<![-a-z])color:\s*([^;]+);/', $cuerpo, $t) ? $t[1] : null;
            $texto = $textoCrudo !== null ? $color($textoCrudo) : null;
            $fondo = preg_match('/(?<![-a-z])background:\s*([^;]+);/', $cuerpo, $f) ? $color($f[1]) : null;
            if ($texto !== null && $fondo !== null) {
                $medidos++;
                $this->assertGreaterThanOrEqual(4.5, $this->contraste($texto, $fondo), 'Hover ilegible: '.trim($selector));
            } elseif ($texto !== null && ! str_contains((string) $textoCrudo, 'texto-acento')) {
                // Enlace que solo cambia de color: debe leerse sobre la página y sobre los paneles.
                foreach ($superficies as $superficie) {
                    $medidos++;
                    $this->assertGreaterThanOrEqual(4.5, $this->contraste($texto, $c[$superficie]), 'Hover ilegible sobre '.$superficie.': '.trim($selector));
                }
            }
        }
        $this->assertGreaterThanOrEqual(20, $medidos, 'Se esperaba medir los hover de botones, "+", cantidades, categorías y enlaces.');

        // Sin a:hover global: cada superficie define el suyo, y cada botón fija su propio color al pasar.
        $this->assertDoesNotMatchRegularExpression('/(?:^|\})\s*(?::where\(\.cob-pagina\)\s+)?a:hover/', $sinRaiz, 'No debe haber un a:hover global.');
        foreach (['.cob-boton', '.cob-boton--claro', '.cob-boton--borde', '.cob-nav__reservar'] as $boton) {
            $this->assertMatchesRegularExpression('/\.cob-pagina '.preg_quote($boton, '/').':hover \{[^}]*color:/', $sinRaiz, "{$boton} no fija su color en hover.");
        }

        // El texto del botón de acento conserva --cob-texto-acento al pasar (con acento propio el fondo no cambia y el texto sigue legible).
        $this->assertStringContainsString('.cob-pagina .cob-boton:hover { background: var(--cob-acento-hover); border-color: var(--cob-acento-hover); color: var(--cob-texto-acento);', $sinRaiz);
    }

    public function test_el_estado_cerrado_se_distingue_por_la_palabra_y_un_punto_hueco(): void
    {
        $hoja = (string) file_get_contents(resource_path('css/sitio/o.css'));

        $this->assertStringContainsString('.cob-estado::before { content: ""; flex: none; width: 0.4375rem; height: 0.4375rem; border: 2px solid currentColor;', $hoja);
        $this->assertStringContainsString('.cob-estado--abierto::before { border-color: var(--cob-abierto-punto); background: var(--cob-abierto-punto); }', $hoja);

        $this->completo();
        $this->travelTo(self::CERRADO);
        $this->sitio()->assertOk()->assertSee('<p class="cob-estado">Cerrado ahora</p>', false);
        $this->menu()->assertOk()->assertSee('<p class="cob-estado">Cerrado</p>', false);
    }

    public function test_los_campos_y_controles_llevan_borde_visible(): void
    {
        $hoja = $this->hojaSinComentarios();

        foreach (['.cob-campo__entrada {', '.cob-mas {', '.cob-cantidad {', '.cob-entrega__opcion {', '.cob-pedido__cerrar {'] as $regla) {
            $inicio = strpos($hoja, $regla);
            $this->assertNotFalse($inicio, $regla);
            $cuerpo = substr($hoja, $inicio, (int) strpos($hoja, '}', $inicio) - $inicio);
            $this->assertStringContainsString('border: 1px solid var(--cob-borde-ui)', $cuerpo, $regla.' no lleva el borde de interfaz de 3:1');
        }
        $this->assertStringContainsString('border: 1px solid var(--cob-borde-ui)', substr($hoja, (int) strpos($hoja, '.cob-idiomas__enlace {'), 400));
    }

    public function test_b_i_j_k_l_y_m_no_llevan_marcas_de_la_plantilla_o(): void
    {
        $this->travelTo(self::ABIERTO);
        $r = $this->completo(['sitio_plantilla' => 'b']);
        $prefijos = [
            'b' => [null, null],
            'i' => ['jap-', 'data-jap-'],
            'j' => ['mar-', 'data-mar-'],
            'k' => ['cor-', 'data-cor-'],
            'l' => ['esm-', 'data-esm-'],
            'm' => ['ham-', 'data-ham-'],
        ];

        foreach ($prefijos as $clave => [$clase, $dato]) {
            $r->forceFill(['sitio_plantilla' => $clave])->saveQuietly();
            Cache::flush();

            $sitio = $this->sitio()->assertOk()->assertViewIs('sitio.'.$clave);
            $menu = $this->menu()->assertOk()->assertViewIs($clave === 'b' ? 'menu.publico' : 'menu.plantillas.'.$clave);
            if ($clase !== null) {
                $sitio->assertSee('class="'.$clase, false);
                $menu->assertSee($dato, false);
            }
            foreach ([$sitio, $menu] as $respuesta) {
                $html = (string) $respuesta->getContent();
                $this->assertDoesNotMatchRegularExpression('/(?<![a-z])cob-[a-z]/', $html, "La plantilla {$clave} lleva clases cob-.");
                $this->assertDoesNotMatchRegularExpression('/\bsitio-o\b/', $html);
                $this->assertStringNotContainsString('menuonl_cart_cob', $html);
                $this->assertStringNotContainsString('data-plantilla="o"', $html);
            }
        }
    }

    public function test_el_carrito_de_o_no_comparte_clave_con_el_menu_generico(): void
    {
        $this->travelTo(self::ABIERTO);
        $r = $this->completo();

        $this->menu()->assertOk()->assertSee('data-clave="menuonl_cart_cob_verdelux"', false)->assertDontSee('menuonl_cart_m_', false);

        $r->forceFill(['sitio_plantilla' => 'b'])->saveQuietly();
        Cache::flush();
        $this->menu()->assertOk()->assertSee('menuonl_cart_m_verdelux', false)->assertDontSee('menuonl_cart_cob', false);
    }

    public function test_no_hay_cinta_y_los_numeros_de_seccion_cuentan_solo_las_secciones_dibujadas(): void
    {
        $this->travelTo(self::ABIERTO);
        $r = $this->completo();
        $r->productos()->get()->each(fn (Producto $p) => $p->forceFill(['destacado' => true])->saveQuietly());

        $html = (string) $this->sitio()->assertOk()->assertDontSee('cob-cinta', false)->getContent();

        // Recomendados 01, Horario 02 y Visítanos 03 (sin galería).
        preg_match_all('#<span class="cob-numero" aria-hidden="true">(\d\d)</span>#', $html, $numeros);
        $this->assertSame(['01', '02', '03'], $numeros[1]);

        GaleriaImagen::factory()->create(['restaurante_id' => $r->id, 'orden' => 1, 'descripcion' => 'Barra']);
        Cache::flush();
        preg_match_all('#<span class="cob-numero" aria-hidden="true">(\d\d)</span>#', (string) $this->sitio()->assertOk()->getContent(), $conGaleria);
        $this->assertSame(['01', '02', '03', '04'], $conGaleria[1]);
    }

    public function test_el_horario_muestra_los_siete_dias_y_la_portada_con_foto_cambia_de_forma(): void
    {
        $this->travelTo(self::ABIERTO);
        $r = $this->completo();

        $html = (string) $this->sitio()->assertOk()->assertSee('cob-portada__tarjeta cob-portada__tarjeta--foto', false)->getContent();
        $this->assertSame(7, substr_count($html, '<dt'));

        $r->forceFill(['portada' => null])->saveQuietly();
        Cache::flush();
        $this->sitio()->assertOk()->assertDontSee('cob-portada__tarjeta--foto', false)->assertDontSee('cob-portada__foto', false);
    }

    public function test_los_recomendados_muestran_la_foto_solo_si_el_platillo_la_tiene(): void
    {
        $this->travelTo(self::ABIERTO);
        $r = $this->completo();
        $r->productos()->get()->each(fn (Producto $p) => $p->forceFill(['destacado' => true])->saveQuietly());

        $html = (string) $this->sitio()->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'cob-plato__foto'), 'Solo Rib eye tiene foto.');
        $this->assertStringContainsString('alt="Rib eye"', $html);
        $this->assertStringNotContainsString('role="img"', $html);
    }

    public function test_reservar_es_el_boton_de_acento_de_la_cabecera_solo_con_reservas_activas(): void
    {
        $this->travelTo(self::ABIERTO);
        $r = $this->completo();

        $this->sitio()->assertOk()->assertSee('class="cob-nav__enlace cob-nav__reservar"', false)->assertDontSee('Pedir ahora');
        $this->menu()->assertOk()->assertSee('class="cob-nav__enlace cob-nav__reservar"', false)->assertDontSee('Pedir ahora');

        $r->forceFill(['reservas_activas' => false])->saveQuietly();
        Cache::flush();
        $this->sitio()->assertOk()->assertDontSee('cob-nav__reservar', false);
    }

    public function test_las_vistas_de_la_plantilla_no_llevan_colores_sueltos(): void
    {
        $archivos = [
            resource_path('views/sitio/o.blade.php'),
            resource_path('views/menu/plantillas/o.blade.php'),
            ...glob(resource_path('views/components/sitio/cobre/*.blade.php')),
        ];

        foreach ($archivos as $archivo) {
            $this->assertSame(0, preg_match('/#[0-9a-fA-F]{6}\b/', (string) file_get_contents($archivo)), basename($archivo).' tiene un color suelto');
        }
    }

    public function test_la_hoja_respeta_movimiento_reducido_y_usa_fuentes_propias_ligeras(): void
    {
        $hoja = (string) file_get_contents(resource_path('css/sitio/o.css'));

        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: no-preference\)\s*\{\s*html \{ scroll-behavior: smooth; \}/', $hoja);
        $this->assertStringNotContainsString('http', $hoja);
        $this->assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)')", (string) file_get_contents(resource_path('js/sitio-cobre.js')));

        preg_match_all('#url\("(/fonts/sitio/[^"]+)"\)#', $hoja, $fuentes);
        $this->assertSame([
            '/fonts/sitio/marcellus-cobre-latin-400.woff2',
            '/fonts/sitio/karla-cobre-latin-400.woff2',
            '/fonts/sitio/karla-cobre-latin-500.woff2',
            '/fonts/sitio/karla-cobre-latin-700.woff2',
        ], $fuentes[1]);
        $this->assertStringNotContainsString('font-style: italic', $hoja);

        // Pesos cargados por familia: Marcellus solo 400; Karla 400, 500 y 700. Cualquier otro peso se sintetizaría.
        preg_match_all('/@font-face \{\s*font-family: "([^"]+)";[^}]*?font-weight: (\d+);/s', $hoja, $caras, PREG_SET_ORDER);
        $porFamilia = [];
        foreach ($caras as [, $familia, $peso]) {
            $porFamilia[$familia][] = (int) $peso;
        }
        $this->assertSame(['Marcellus Cobre' => [400], 'Karla Cobre' => [400, 500, 700]], $porFamilia);
        $this->assertDoesNotMatchRegularExpression('/font-weight: (?:100|200|300|600|800|900|bold|bolder)\b/', $hoja, 'Solo hay pesos 400, 500 y 700 cargados.');

        // Toda regla con Marcellus lleva peso 400 (Marcellus no tiene negrita; h1 a h3 la traen por defecto).
        $sinComentarios = $this->hojaSinComentarios();
        preg_match_all('/([^{}]+)\{([^{}]*var\(--cob-fuente-titulo\)[^{}]*)\}/', $sinComentarios, $conAnton, PREG_SET_ORDER);
        $this->assertGreaterThanOrEqual(4, count($conAnton));
        foreach ($conAnton as [, $selector, $cuerpo]) {
            if (str_contains($cuerpo, '--cob-fuente-titulo:')) {
                continue;
            }
            $this->assertMatchesRegularExpression('/font-weight: 400|font: 400/', $cuerpo, 'Marcellus sin peso 400 en '.trim($selector));
        }

        $peso = 0;
        $huellas = [];
        foreach ($fuentes[1] as $ruta) {
            $this->assertFileExists(public_path($ruta));
            $this->assertSame('wOF2', substr((string) file_get_contents(public_path($ruta), false, null, 0, 4), 0, 4), $ruta.' no es woff2');
            $peso += filesize(public_path($ruta));
            $huellas[] = hash_file('sha256', public_path($ruta));
        }
        $this->assertLessThan(60 * 1024, $peso, 'Las fuentes de la plantilla deben ser un subconjunto ligero.');
        $this->assertCount(4, array_unique($huellas), 'Cada peso debe ser un archivo distinto.');

        $ajenas = [];
        foreach (glob(public_path('fonts/sitio/*.woff2')) ?: [] as $otra) {
            if (! in_array('/fonts/sitio/'.basename($otra), $fuentes[1], true)) {
                $ajenas[] = hash_file('sha256', $otra);
            }
        }
        $this->assertSame([], array_values(array_intersect($huellas, $ajenas)), 'Una fuente de O es idéntica a la de otra plantilla: debe tener su propio archivo.');

        foreach (['OFL-marcellus.txt', 'OFL-karla.txt'] as $licencia) {
            $this->assertFileExists(public_path('fonts/sitio/'.$licencia));
            $this->assertStringContainsString('SIL Open Font License', (string) file_get_contents(public_path('fonts/sitio/'.$licencia)));
        }
        $this->assertStringContainsString('marcellus-cobre-latin-400.woff2', (string) file_get_contents(resource_path('views/components/sitio/cobre/layout.blade.php')));
    }

    public function test_los_numeros_de_seccion_y_los_precios_usan_la_fuente_de_texto(): void
    {
        $hoja = $this->hojaSinComentarios();

        // En Marcellus el 0 y el 1 se leen como O e I: toda cifra visible va en Karla.
        foreach (['.cob-numero {', '.cob-precio {', '.cob-pagina .cob-linea__subtotal {', '.cob-pagina .cob-pedido__total {'] as $regla) {
            $inicio = strpos($hoja, $regla);
            $this->assertNotFalse($inicio, $regla);
            $cuerpo = substr($hoja, $inicio, (int) strpos($hoja, '}', $inicio) - $inicio);
            $this->assertStringNotContainsString('var(--cob-fuente-titulo)', $cuerpo, $regla.' usa la fuente de títulos.');
            $this->assertStringNotContainsString('Marcellus', $cuerpo, $regla);
        }
        foreach (['.cob-numero {', '.cob-precio {'] as $regla) {
            $inicio = strpos($hoja, $regla);
            $this->assertStringContainsString('font-family: var(--cob-fuente-texto)', substr($hoja, $inicio, (int) strpos($hoja, '}', $inicio) - $inicio), $regla.' debe fijar la fuente de texto.');
        }
    }

    public function test_la_galeria_fija_proporcion_con_recorte_y_limita_el_ancho_de_cada_foto(): void
    {
        $hoja = (string) file_get_contents(resource_path('css/sitio/o.css'));

        $this->assertMatchesRegularExpression('/\.cob-pagina \.cob-galeria__foto \{[^}]*aspect-ratio: 4 \/ 3;[^}]*object-fit: cover;/', $hoja);
        $this->assertDoesNotMatchRegularExpression('/\.cob-galeria \{[^}]*repeat\(auto-fit/', $hoja, 'auto-fit estira 1 o 2 fotos a todo el ancho.');
        $this->assertMatchesRegularExpression('/\.cob-galeria \{[^}]*repeat\(auto-fill, minmax\(min\(100%, 16rem\), 22rem\)\)/', $hoja);
        $this->assertMatchesRegularExpression('/\.cob-acciones > \.cob-boton \{[^}]*flex: 0 0 auto;[^}]*height: var\(--cob-boton-alto\);/', $hoja);
    }

    public function test_el_diseno_no_desborda_a_360_px(): void
    {
        $hoja = $this->hojaSinComentarios();

        // Todo minmax con un mínimo fijo debe caber en 360 px (20 rem menos el margen); los mayores van envueltos en min(100%, …).
        preg_match_all('/minmax\(\s*(?!min\()([0-9.]+)(rem|px)/', $hoja, $fijos, PREG_SET_ORDER);
        foreach ($fijos as [, $valor, $unidad]) {
            $this->assertLessThanOrEqual(20 * 16, (float) $valor * ($unidad === 'rem' ? 16 : 1), 'minmax con mínimo fijo que no cabe a 360 px');
        }
        $this->assertStringContainsString('minmax(min(100%, 16rem), 1fr)', $hoja);
        $this->assertStringContainsString('overflow-x: clip;', $hoja);
    }

    public function test_un_nombre_de_90_caracteres_no_desborda_a_360_px(): void
    {
        $hoja = $this->hojaSinComentarios();

        // El h1 usa Marcellus con un tamaño que baja a 2.25 rem y parte cualquier palabra larga; el diseño no usa mayúsculas en los títulos.
        $this->assertStringContainsString('--cob-t-h1: clamp(2.25rem, 1.2rem + 4.6vw, 4rem);', $hoja);
        $this->assertMatchesRegularExpression('/:where\(\.cob-pagina\) h1,\s*:where\(\.cob-pagina\) h2,\s*:where\(\.cob-pagina\) h3 \{[^}]*font-weight: 400;[^}]*overflow-wrap: anywhere;/s', $hoja);
        $this->assertStringNotContainsString('text-transform: uppercase', substr($hoja, (int) strpos($hoja, ':where(.cob-pagina) h1'), 700));
        $this->assertStringContainsString('.cob-portada__titulo { max-width: 100%; }', $hoja);
        $this->assertStringContainsString('.cob-portada__tarjeta > * { min-width: 0; max-width: 100%; }', $hoja);
        // El nombre de la cabecera, del pie y de las tarjetas también se parte y la marca no usa nowrap ni puntos suspensivos.
        $this->assertSame(0, preg_match('/white-space: nowrap;[^}]*text-overflow|text-overflow: ellipsis/', $hoja), 'Truncar el nombre lo esconde.');
        foreach (['.cob-marca {', '.cob-pie__nombre {', '.cob-portada__eslogan {'] as $regla) {
            $inicio = strpos($hoja, $regla);
            $this->assertNotFalse($inicio, $regla);
            $this->assertStringContainsString('overflow-wrap: anywhere', substr($hoja, $inicio, (int) strpos($hoja, '}', $inicio) - $inicio), $regla);
        }

        $nombre = 'Cobre y Brasa Don Chuy del Norte y Compañía Supercalifragilísticaexpialidosa Hermanos S.A.';
        $this->assertSame(90, mb_strlen($nombre));
        $this->completo(['nombre' => $nombre, 'eslogan' => 'Costilla a la leña con mezcal y buena mesa para toda la familia']);
        foreach ([$this->sitio(), $this->menu()] as $respuesta) {
            $respuesta->assertOk()->assertSee($nombre);
        }
    }

    public function test_el_selector_de_idioma_del_menu_usa_route_con_lang(): void
    {
        $r = $this->completo();
        RestauranteIdioma::factory()->create(['restaurante_id' => $r->id, 'idioma' => 'en']);

        $this->menu()->assertOk()->assertSee(route('menu.publico', ['slug' => 'verdelux', 'lang' => 'en']), false);
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

    public function test_los_archivos_que_carga_el_layout_existen_y_estan_registrados_en_vite(): void
    {
        $layout = (string) file_get_contents(resource_path('views/components/sitio/cobre/layout.blade.php'));
        $vite = (string) file_get_contents(base_path('vite.config.js'));

        preg_match_all('#resources/(?:css|js)/[A-Za-z0-9/_.-]+.(?:css|js)#', $layout, $entradas);
        $this->assertNotEmpty($entradas[0]);

        foreach (array_unique($entradas[0]) as $entrada) {
            $this->assertFileExists(base_path($entrada), $entrada.' no existe');
            $this->assertStringContainsString("'{$entrada}'", $vite, $entrada.' no está registrada en vite.config.js');
        }
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
