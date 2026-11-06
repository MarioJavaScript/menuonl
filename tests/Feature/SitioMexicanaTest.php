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
 * Plantilla N "Mexicana colorida": sitio (/{slug}) y menú (/{slug}/menu) con su propio diseño, sin depender del estilo del
 * restaurante ni de menuonl.css. Usa withoutVite() para no depender de un build previo de los assets.
 */
class SitioMexicanaTest extends TestCase
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
            'sitio_plantilla' => 'n',
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

    public function test_el_catalogo_registra_la_plantilla_n_mexicana_colorida(): void
    {
        $this->assertSame(['b', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p'], array_keys(Catalogos::plantillasSitio()));
        $this->assertSame('Mexicana colorida', Catalogos::plantillasSitio()['n']['nombre']);
        $this->assertSame('n', AparienciaSitio::plantillaVigente('n'));
    }

    public function test_el_panel_tiene_la_ficha_y_no_ofrece_el_estilo_del_menu(): void
    {
        $ficha = AparienciaSitio::fichas()['n'] ?? null;

        $this->assertNotNull($ficha, 'Falta la ficha de la plantilla n en AparienciaSitio::fichas().');
        $this->assertSame('Mexicana colorida', $ficha['nombre']);
        $this->assertNotSame('', $ficha['para']);
        $this->assertNotContains('n', AparienciaSitio::PLANTILLAS_CON_ESTILO);
        $this->assertFalse(AparienciaSitio::usaEstilo('n'));

        $hoja = (string) file_get_contents(resource_path('css/sitio/n.css'));
        foreach (['fondo' => '--mex-cafe', 'texto' => '--mex-crema', 'acento' => '--mex-terracota', 'bloque' => '--mex-dorado'] as $campo => $variable) {
            $this->assertStringContainsString($variable.': '.$ficha[$campo].';', $hoja, "El color '{$campo}' de la ficha no sale de la hoja.");
        }
    }

    public function test_admin_permite_elegir_la_plantilla_n(): void
    {
        $restaurante = Restaurante::factory()->create();
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Apariencia::class)
            ->assertSee('Mexicana colorida')
            ->set('data.sitio_plantilla', 'n')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('restaurantes', ['id' => $restaurante->id, 'sitio_plantilla' => 'n']);
    }

    public function test_el_sitio_se_renderiza_con_un_restaurante_completo(): void
    {
        $this->travelTo(self::ABIERTO);
        $this->completo();

        $respuesta = $this->sitio()->assertOk()->assertViewIs('sitio.n');

        $respuesta->assertSee('data-plantilla="n"', false)->assertSee('sitio-n', false)
            ->assertSee('Verdelux')->assertSee('Brasa y humo')->assertSee('Cena de autor.')
            ->assertSee('Calle de ejemplo 321')->assertSee('tel:6621112222', false)->assertSee('https://wa.me/525512345678', false)
            ->assertSee('https://instagram.com/labahia.mar', false)->assertSee('18:00 – 23:59')
            ->assertSee('alt="Foto de Verdelux"', false)->assertSee('Sitio creado con '.config('app.name'))
            ->assertSee('Ver menú y pedir')->assertSee('Reservar mesa')->assertSee('>Reservar</a>', false)->assertSee('Nuestra historia')->assertSee('>Nosotros</a>', false)->assertSee('Visítanos')->assertSee('Cómo llegar')
            ->assertSee('<h2 id="destacados-titulo">Recomendados</h2>', false)->assertSee('Descripción de Rib eye')
            ->assertSee(route('menu.publico', ['slug' => 'verdelux']), false)
            ->assertDontSee('Smash al carbón')->assertDontSee('Foto del platillo')->assertDontSee('Una noche para recordar')->assertDontSee('Selección del chef')->assertDontSee('Cocina de autor')->assertDontSee('Lo más pedido')->assertDontSee('Pedir ahora')->assertDontSee('Sabor de casa')->assertDontSee('mex-cinta', false)->assertDontSee('mex-olas', false)->assertDontSee('Te confirmamos por WhatsApp')->assertDontSee('Hecho con Menubly')->assertDontSee('cierra')->assertDontSee('Foto de portada')->assertDontSee('mex-kanji', false);
        $this->assertSame(1, substr_count($respuesta->getContent(), '<h1'));
    }

    public function test_abierto_ahora_y_cerrado_ahora_salen_del_horario_del_restaurante(): void
    {
        $this->completo();

        $this->travelTo(self::ABIERTO);
        $this->sitio()->assertOk()->assertSee('<p class="mex-estado mex-estado--abierto">Abierto ahora</p>', false)->assertSee('Hoy: 18:00 – 23:59');
        $this->menu()->assertOk()->assertSee('<p class="mex-estado mex-estado--abierto">Abierto</p>', false);

        Cache::flush();
        $this->travelTo(self::CERRADO);
        $this->sitio()->assertOk()->assertSee('<p class="mex-estado">Cerrado ahora</p>', false)->assertDontSee('Abierto ahora');
        $this->menu()->assertOk()->assertSee('<p class="mex-estado">Cerrado</p>', false);
    }

    public function test_sin_pedidos_el_boton_de_portada_no_promete_pedir(): void
    {
        $this->completo(['pedidos_activos' => false]);

        $this->sitio()->assertOk()->assertSee('>Ver el menú</a>', false)->assertDontSee('Ver menú y pedir')->assertDontSee('Arma tu pedido')->assertDontSee('Pedir ahora');
    }

    public function test_los_recomendados_ocultan_los_agotados_y_muestran_el_precio_vigente(): void
    {
        $this->completo()->productos()->where('id', '>', 0)->get()->each(fn ($p) => $p->forceFill(['destacado' => true])->saveQuietly());

        $this->sitio()->assertOk()
            ->assertSee('<h2 id="destacados-titulo">Recomendados</h2>', false)
            ->assertSee('$175.00')->assertSee('<s class="mex-precio__antes">', false)->assertSee('$190.00')
            ->assertSee('Arrachera')->assertSee('Picante')->assertDontSee('Tuétano agotado');
    }

    public function test_el_horario_marca_el_dia_de_hoy_segun_la_zona_del_restaurante(): void
    {
        $this->travelTo('2026-10-22 01:00:00'); // miércoles 19:00 en Ciudad de México, jueves en UTC
        $this->completo();

        $html = (string) $this->sitio()->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'aria-current="date"'));
        $this->assertStringContainsString('Miércoles<span class="mex-solo-lectores"> (hoy)</span>', $html);
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

    public function test_el_menu_se_renderiza_con_la_vista_de_la_plantilla_n(): void
    {
        $this->travelTo(self::ABIERTO);
        $r = $this->completo();
        $categorias = Categoria::where('restaurante_id', $r->id)->orderBy('id')->get();

        $respuesta = $this->menu()->assertOk()->assertViewIs('menu.plantillas.n');

        $respuesta->assertSee('Rib eye')->assertSee('Arrachera')->assertSee('Parrilla')->assertSee('Bebidas')
            ->assertSee('$175.00')->assertSee('<s class="mex-precio__antes">', false)->assertSee('$190.00')
            ->assertSee('Agotado')->assertSee('Botella')->assertSee('$70.00')
            ->assertSee('data-mex-categorias', false)->assertSee('Pedir por WhatsApp')
            ->assertSee('Calle de ejemplo 321')->assertSee('>Menú</h1>', false)
            ->assertSee(route('sitio.publico', ['slug' => 'verdelux']).'#reservar', false)
            ->assertSee('aria-label="Agregar Arrachera al pedido"', false);
        foreach ($categorias as $categoria) {
            $respuesta->assertSee('href="#categoria-'.$categoria->id.'"', false)->assertSee('id="categoria-'.$categoria->id.'"', false);
        }
        $this->assertSame(1, substr_count($respuesta->getContent(), '<h1'));
        $respuesta->assertDontSee('css/menuonl.css', false)->assertDontSee('data-tema', false)->assertDontSee('Carta')->assertDontSee('mex-kanji', false);
    }

    public function test_con_una_sola_categoria_no_hay_barra_de_categorias(): void
    {
        $r = $this->restaurante();
        $categoria = $this->categoria($r, 'Entradas');
        $this->platillo($r, 'Queso fundido', ['precio' => 165], $categoria);
        $this->platillo($r, 'Frijoles charros', ['precio' => 170], $categoria);

        $this->menu()->assertOk()->assertSee('Queso fundido')->assertDontSee('data-mex-categorias', false);
    }

    public function test_la_mesa_se_ignora_por_completo(): void
    {
        $this->travelTo(self::ABIERTO);
        $this->completo();
        $base = $this->contenido($this->menu());

        foreach (['3', '<b>x</b>'] as $mesa) {
            $respuesta = $this->menu('verdelux', ['mesa' => $mesa])->assertOk()
                ->assertDontSee('Mesa 3')->assertDontSee('mex-mesa', false)->assertDontSee('data-mesa', false)->assertDontSee('id="pedido-mesa"', false);
            $this->assertSame($base, $this->contenido($respuesta), 'El parámetro ?mesa= cambió el menú.');
        }
        $this->sitio()->assertOk()->assertDontSee('mex-mesa', false);
    }

    public function test_las_otras_plantillas_siguen_usando_las_mismas_vistas(): void
    {
        foreach (['b', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm'] as $clave) {
            $restaurante = $this->completo(['sitio_plantilla' => $clave, 'slug' => 'otro-'.$clave]);

            $sitio = $this->sitio('otro-'.$clave)->assertOk()->assertViewIs('sitio.'.$clave);
            $menu = $this->menu('otro-'.$clave)->assertOk()->assertViewIs(in_array($clave, ['e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm'], true) ? 'menu.plantillas.'.$clave : 'menu.publico');
            foreach ([$sitio, $menu] as $respuesta) {
                $this->assertDoesNotMatchRegularExpression('/\bsitio-n\b/', $this->contenido($respuesta));
                $this->assertStringNotContainsString('data-mex-', $this->contenido($respuesta));
                $this->assertStringNotContainsString('class="mex-', $this->contenido($respuesta));
                $this->assertStringNotContainsString('menuonl_cart_mex', $this->contenido($respuesta));
            }
            $restaurante->delete();
        }
    }

    public function test_cambiar_de_n_a_otra_plantilla_vuelve_al_diseno_anterior(): void
    {
        $restaurante = $this->completo();
        $this->menu()->assertViewIs('menu.plantillas.n');

        $restaurante->forceFill(['sitio_plantilla' => 'b'])->saveQuietly();

        $this->sitio()->assertViewIs('sitio.b');
        $this->menu()->assertViewIs('menu.publico');
    }

    public function test_sin_datos_opcionales_se_omiten_las_secciones_sin_textos_de_relleno(): void
    {
        $this->restaurante(['eslogan' => null, 'descripcion' => null, 'portada' => null, 'whatsapp_pedidos' => null, 'reservas_activas' => false]);

        $sitio = $this->sitio()->assertOk();
        foreach (['id="visitanos"', 'id="horario"', 'id="ubicacion"', 'id="reservar"', 'id="destacados"', 'id="galeria"', 'mex-portada__foto', 'mex-cinta', 'mex-portada__eslogan', 'mex-nosotros', 'Nuestra historia', '>Nosotros<', 'Hoy:', 'mex-estado', 'wa.me', 'tel:', 'Reservar', '>Horario<', '>Galería<'] as $ausente) {
            $sitio->assertDontSee($ausente, false);
        }
        $sitio->assertSee('>Ver el menú</a>', false);

        $menu = $this->menu()->assertOk();
        foreach (['id="visitanos"', 'wa.me', 'data-mex-categorias', 'Pedir por WhatsApp', 'data-mex-pedido'] as $ausente) {
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
        $this->assertSame(1, substr_count($html, 'mex-fila__foto'));
        $this->assertStringContainsString('width: 4.75rem; height: 4.75rem', (string) file_get_contents(resource_path('css/sitio/n.css')));
    }

    public function test_el_logo_se_muestra_junto_al_nombre_solo_si_existe_y_con_tamano_fijo(): void
    {
        $r = $this->restaurante(['logo' => null]);
        foreach ([$this->sitio(), $this->menu()] as $respuesta) {
            $respuesta->assertOk()->assertDontSee('mex-marca__logo', false)->assertSee('<span>Verdelux</span>', false);
        }

        $r->forceFill(['logo' => "restaurantes/{$r->id}/logo.webp"])->saveQuietly();
        Cache::flush();

        foreach ([$this->sitio(), $this->menu()] as $respuesta) {
            $respuesta->assertOk()->assertSee('mex-marca__logo', false)->assertSee('<span>Verdelux</span>', false);
        }
        $this->assertStringContainsString('.mex-pagina .mex-marca__logo { flex: none; width: 3rem; height: 3rem; padding: 3px; border-radius: 12px; background: var(--mex-crema); object-fit: contain; }', (string) file_get_contents(resource_path('css/sitio/n.css')));
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
            $respuesta->assertOk()->assertDontSee($hostilCorto, false)->assertDontSee('--mex-acento:', false);
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

        $this->sitio()->assertOk()->assertSee('--mex-acento: #1f5f3f; --mex-acento-hover: #1f5f3f; --mex-texto-acento: #ffffff;', false);
        $this->menu()->assertOk()->assertSee('--mex-acento: #1f5f3f;', false);

        $r->forceFill(['color_acento' => '#808080'])->saveQuietly();
        Cache::flush();
        $this->sitio()->assertOk()->assertDontSee('--mex-acento:', false);
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

    public function test_la_palabra_carta_no_aparece_en_la_interfaz_de_n(): void
    {
        $archivos = [
            resource_path('views/sitio/n.blade.php'),
            resource_path('views/menu/plantillas/n.blade.php'),
            resource_path('js/sitio-mexicana.js'),
            resource_path('js/sitio-mexicana-pedido.js'),
            ...glob(resource_path('views/components/sitio/mexicana/*.blade.php')),
        ];

        foreach ($archivos as $archivo) {
            $this->assertSame(0, preg_match('/\bcarta\b/i', (string) file_get_contents($archivo)), basename($archivo).' dice "carta"');
        }
    }

    public function test_la_hoja_usa_prefijo_mex_sin_chocar_con_menuonl_css_y_colores_solo_en_variables(): void
    {
        $hoja = $this->hojaSinComentarios();
        $compartida = (string) file_get_contents(public_path('css/menuonl.css'));

        preg_match_all('/\.([a-z][a-z0-9_-]*)/i', (string) preg_replace('/url\([^)]*\)|"[^"]*"|\d+\.\d+/', '', $hoja), $coincidencias);
        $permitidas = ['foto', 'foto-vacia', 'sitio', 'sitio-n'];

        foreach (array_unique($coincidencias[1]) as $clase) {
            if (in_array($clase, $permitidas, true)) {
                continue;
            }
            $this->assertStringStartsWith('mex-', $clase, "La clase .$clase no lleva el prefijo mex-");
            $this->assertStringNotContainsString('.'.$clase, $compartida, "La clase .$clase choca con menuonl.css");
        }

        $sinRaiz = (string) preg_replace('/:root\s*\{.*?\n\}/s', '', $hoja);
        $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b/', $sinRaiz), 'Hay un color hexadecimal fuera de :root');
        $this->assertSame(0, preg_match('/\b(?:rgb|hsl)a?\(/i', $sinRaiz), 'Hay un color rgb/hsl fuera de :root');
    }

    private function hojaSinComentarios(): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(resource_path('css/sitio/n.css')));
    }

    /**
     * Colores de :root de la hoja, con los alias (var(--otro)) ya resueltos.
     *
     * @return array<string, string>
     */
    private function coloresDeLaHoja(): array
    {
        $hoja = $this->hojaSinComentarios();
        preg_match_all('/--(mex-[a-z0-9-]+): ([^;]+);/', $hoja, $definidos);
        $valores = array_combine($definidos[1], array_map('trim', $definidos[2]));
        $resolver = function (string $nombre) use (&$resolver, $valores): ?string {
            $valor = $valores[$nombre] ?? null;
            if ($valor !== null && preg_match('/^var\(--(mex-[a-z0-9-]+)\)$/', $valor, $alias)) {
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

        $textos = [
            ['mex-crema', 'mex-cafe'], ['mex-arena', 'mex-cafe'], ['mex-dorado', 'mex-cafe'], ['mex-cafe', 'mex-dorado'],
            ['mex-cafe', 'mex-dorado-hover'], ['mex-blanco', 'mex-terracota'], ['mex-blanco', 'mex-terracota-hover'],
            ['mex-terracota-texto', 'mex-crema'], ['mex-terracota-texto', 'mex-blanco'], ['mex-tinta', 'mex-blanco'], ['mex-tinta', 'mex-crema'],
            ['mex-crema', 'mex-pildora-cerrado'], ['mex-blanco', 'mex-verde'], ['mex-menta', 'mex-verde'], ['mex-verde', 'mex-crema'],
            ['mex-verde', 'mex-blanco'], ['mex-error', 'mex-blanco'], ['mex-error', 'mex-crema'], ['mex-cafe', 'mex-crema'],
            ['mex-cafe', 'mex-blanco'], ['mex-placeholder', 'mex-blanco'], ['mex-crema', 'mex-verde'], ['mex-cafe', 'mex-papel'],
        ];
        foreach ($textos as [$texto, $fondo]) {
            $this->assertGreaterThanOrEqual(4.5, $this->contraste($c[$texto], $c[$fondo]), "{$texto} sobre {$fondo} no llega a 4.5:1");
        }

        $this->assertSame('#9a3411', $c['mex-terracota-hover']);
        $this->assertSame('#f7c868', $c['mex-dorado-hover']);
        $this->assertSame('#4a2d20', $c['mex-pildora-cerrado']);

        // Bordes y componentes de interfaz (3:1): campos y opciones sobre blanco y crema; borde crema de los idiomas y de "Cerrado" sobre café;
        // el punto de "Abierto" sobre el verde; el anillo de foco dorado sobre café y sobre verde.
        foreach ([
            ['mex-borde', 'mex-blanco'], ['mex-borde', 'mex-crema'], ['mex-cafe', 'mex-blanco'], ['mex-cafe', 'mex-crema'], ['mex-crema', 'mex-cafe'],
            ['mex-abierto', 'mex-verde'], ['mex-dorado', 'mex-cafe'], ['mex-dorado', 'mex-verde'], ['mex-verde', 'mex-crema'],
        ] as [$borde, $fondo]) {
            $this->assertGreaterThanOrEqual(3.0, $this->contraste($c[$borde], $c[$fondo]), "{$borde} contra {$fondo} no llega a 3:1");
        }
    }

    public function test_la_terracota_nunca_es_texto_sobre_cafe_ni_el_dorado_sobre_claro(): void
    {
        $c = $this->coloresDeLaHoja();

        $this->assertLessThan(4.5, $this->contraste($c['mex-terracota'], $c['mex-cafe']), 'La terracota no sirve como texto sobre café.');
        $this->assertLessThan(4.5, $this->contraste($c['mex-terracota'], $c['mex-crema']), 'La terracota base no sirve como texto sobre crema: se usa la variante de texto.');
        $this->assertLessThan(4.5, $this->contraste($c['mex-dorado'], $c['mex-crema']), 'El dorado no sirve como texto sobre crema.');
        $this->assertLessThan(4.5, $this->contraste($c['mex-dorado'], $c['mex-blanco']), 'El dorado no sirve como texto sobre blanco.');
        $this->assertLessThan(4.5, $this->contraste($c['mex-dorado'], $c['mex-verde']), 'El dorado no sirve como texto sobre verde.');

        $hoja = $this->hojaSinComentarios();
        $sinRaiz = (string) preg_replace('/:root\s*\{.*?\n\}/s', '', $hoja);
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $sinRaiz, $reglas, PREG_SET_ORDER);

        // La terracota de texto solo en piezas que viven sobre crema o blanco; la terracota base nunca es texto y el dorado solo en el contorno de la portada (sobre café).
        $terracotaPermitida = ['.mex-enlace-rojo', '.mex-precio'];
        $doradoPermitido = ['.mex-portada .mex-boton--borde'];
        $visto = ['terracota' => [], 'dorado' => []];
        foreach ($reglas as [, $selector, $cuerpo]) {
            $selector = trim((string) preg_replace('/\s+/', ' ', $selector));
            $this->assertDoesNotMatchRegularExpression('/(?<![-a-z])color:\s*var\(--mex-terracota\)/', $cuerpo, "La terracota base se usa como texto en {$selector}.");
            if (preg_match('/(?<![-a-z])color:\s*var\(--mex-terracota-texto\)/', $cuerpo)) {
                $visto['terracota'][] = $selector;
                $this->assertContains($selector, $terracotaPermitida, "La terracota de texto se usa en {$selector}.");
            }
            if (preg_match('/(?<![-a-z])color:\s*var\(--mex-dorado\)/', $cuerpo)) {
                $visto['dorado'][] = $selector;
                $this->assertContains($selector, $doradoPermitido, "El dorado se usa como texto en {$selector}.");
            }
        }
        $this->assertNotEmpty($visto['terracota']);
        $this->assertNotEmpty($visto['dorado']);

        // Las zonas que ponen el enlace en dorado al pasar son las de fondo café; las que lo ponen en blanco, las de fondo verde.
        preg_match_all('/([^{}]+)\{([^{}]*--mex-enlace-hover:\s*var\(--mex-([a-z]+)\)[^{}]*)\}/', $sinRaiz, $zonas, PREG_SET_ORDER);
        $this->assertNotEmpty($zonas);
        foreach ($zonas as [, , $cuerpo, $color]) {
            match ($color) {
                'dorado' => $this->assertStringContainsString('background: var(--mex-cafe)', $cuerpo, 'Enlace dorado al pasar sobre una zona que no es café.'),
                'blanco' => $this->assertStringContainsString('background: var(--mex-verde)', $cuerpo, 'Enlace blanco al pasar sobre una zona que no es verde.'),
                default => $this->fail('Color de enlace al pasar inesperado: '.$color),
            };
        }
    }

    public function test_ningun_hover_deja_el_texto_igual_al_fondo_en_ninguna_superficie(): void
    {
        $c = $this->coloresDeLaHoja();
        $sinRaiz = (string) preg_replace('/:root\s*\{.*?\n\}/s', '', $this->hojaSinComentarios());
        // Superficies que invierten --mex-sup-texto y --mex-sup-fondo: [texto, fondo].
        $superficies = [['mex-cafe', 'mex-crema'], ['mex-crema', 'mex-cafe'], ['mex-blanco', 'mex-verde'], ['mex-cafe', 'mex-blanco']];
        $acentos = ['mex-acento' => 'mex-terracota', 'mex-acento-hover' => 'mex-terracota-hover', 'mex-texto-acento' => 'mex-blanco'];

        $color = function (string $valor, array $superficie) use ($c, $acentos): ?string {
            if (! preg_match('/^var\(--(mex-[a-z0-9-]+)\)$/', trim($valor), $m)) {
                return null;
            }

            return match ($m[1]) {
                'mex-sup-texto' => $c[$superficie[0]],
                'mex-sup-fondo' => $c[$superficie[1]],
                default => $c[$acentos[$m[1]] ?? $m[1]] ?? null,
            };
        };

        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $sinRaiz, $reglas, PREG_SET_ORDER);
        $medidos = 0;
        foreach ($reglas as [, $selector, $cuerpo]) {
            if (! str_contains($selector, ':hover') || str_contains($selector, ':has') || str_contains($selector, ':not([aria-current')) {
                continue;
            }
            foreach ($superficies as $superficie) {
                $texto = preg_match('/(?<![-a-z])color:\s*([^;]+);/', $cuerpo, $t) ? $color($t[1], $superficie) : null;
                $fondo = preg_match('/(?<![-a-z])background:\s*([^;]+);/', $cuerpo, $f) ? $color($f[1], $superficie) : null;
                if ($texto !== null && $fondo !== null) {
                    $medidos++;
                    $this->assertGreaterThanOrEqual(4.5, $this->contraste($texto, $fondo), 'Hover ilegible: '.trim($selector));
                }
            }
        }
        $this->assertGreaterThanOrEqual(30, $medidos, 'Se esperaba medir los hover de botones, "+", cantidades y categorías.');

        // Cada botón fija su propio color al pasar; ninguno hereda el global de los enlaces.
        foreach (['.mex-boton', '.mex-boton--claro', '.mex-boton--borde', '.mex-nav__reservar'] as $boton) {
            $this->assertMatchesRegularExpression('/\.mex-pagina '.preg_quote($boton, '/').':hover \{[^}]*color:/', $sinRaiz, "{$boton} no fija su color en hover.");
        }
        $this->assertStringContainsString(':where(.mex-pagina) a:hover { color: var(--mex-enlace-hover);', $sinRaiz);

        // El texto del botón de acento nunca pasa a terracota: el hover conserva --mex-texto-acento (el global del prototipo lo dejaba rojo sobre rojo).
        $this->assertStringContainsString('.mex-pagina .mex-boton:hover { background: var(--mex-acento-hover); border-color: var(--mex-acento-hover); color: var(--mex-texto-acento);', $sinRaiz);
    }

    public function test_el_estado_cerrado_se_distingue_por_la_palabra_y_un_punto_hueco(): void
    {
        $hoja = (string) file_get_contents(resource_path('css/sitio/n.css'));

        $this->assertStringContainsString('.mex-estado::before { content: ""; flex: none; width: 0.4375rem; height: 0.4375rem; border: 2px solid currentColor;', $hoja);
        $this->assertStringContainsString('.mex-estado--abierto::before { border-color: var(--mex-abierto); background: var(--mex-abierto); }', $hoja);

        $this->completo();
        $this->travelTo(self::CERRADO);
        $this->sitio()->assertOk()->assertSee('<p class="mex-estado">Cerrado ahora</p>', false);
        $this->menu()->assertOk()->assertSee('<p class="mex-estado">Cerrado</p>', false);
    }

    public function test_los_campos_y_controles_llevan_borde_visible(): void
    {
        $hoja = $this->hojaSinComentarios();

        foreach (['.mex-campo__entrada {' => 'borde', '.mex-entrega__opcion {' => 'borde', '.mex-cantidad {' => 'cafe', '.mex-pedido__cerrar {' => 'cafe', '.mex-categorias__enlace {' => 'cafe'] as $regla => $color) {
            $inicio = strpos($hoja, $regla);
            $this->assertNotFalse($inicio, $regla);
            $cuerpo = substr($hoja, $inicio, (int) strpos($hoja, '}', $inicio) - $inicio);
            $this->assertStringContainsString("border: 2px solid var(--mex-{$color})", $cuerpo, $regla.' no lleva borde de 2 px');
        }
        // El "+" es café sobre tarjeta blanca (16:1), sin borde: el relleno ya da el contraste de componente.
        $this->assertStringContainsString('background: var(--mex-cafe)', substr($hoja, (int) strpos($hoja, '.mex-mas {'), 400));
        $this->assertStringContainsString('border: 2px solid var(--mex-crema)', substr($hoja, (int) strpos($hoja, '.mex-idiomas__enlace {'), 400));
    }

    public function test_b_i_j_k_l_y_m_no_llevan_marcas_de_la_plantilla_n(): void
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
                $this->assertDoesNotMatchRegularExpression('/(?<![a-z])mex-[a-z]/', $html, "La plantilla {$clave} lleva clases mex-.");
                $this->assertDoesNotMatchRegularExpression('/\bsitio-n\b/', $html);
                $this->assertStringNotContainsString('menuonl_cart_mex', $html);
                $this->assertStringNotContainsString('data-plantilla="n"', $html);
            }
        }
    }

    public function test_los_archivos_de_las_otras_plantillas_no_llevan_marcas_de_la_plantilla_n(): void
    {
        $propios = [
            resource_path('views/sitio/n.blade.php'),
            resource_path('views/menu/plantillas/n.blade.php'),
            resource_path('css/sitio/n.css'),
            resource_path('js/sitio-mexicana.js'),
            resource_path('js/sitio-mexicana-pedido.js'),
            resource_path('js/sitio-mexicana-pedido-mensaje.js'),
        ];
        $archivos = [
            ...glob(resource_path('views/sitio/*.blade.php')),
            ...glob(resource_path('views/menu/*.blade.php')),
            ...glob(resource_path('views/menu/plantillas/*.blade.php')),
            ...glob(resource_path('views/components/sitio/*/*.blade.php')),
            ...glob(resource_path('views/components/sitio/*.blade.php')),
            ...glob(resource_path('css/sitio/*.css')),
            ...glob(resource_path('css/*.css')),
            ...glob(resource_path('js/*.js')),
            ...glob(resource_path('js/pedido/*.js')),
        ];
        $revisados = 0;

        foreach ($archivos as $archivo) {
            $ruta = str_replace(DIRECTORY_SEPARATOR, '/', $archivo);
            if (in_array($archivo, $propios, true) || str_contains($ruta, '/components/sitio/mexicana/')) {
                continue;
            }
            $revisados++;
            $contenido = (string) file_get_contents($archivo);
            $this->assertDoesNotMatchRegularExpression('/(?<![a-z])mex-[a-z]/', $contenido, basename($archivo).' lleva clases mex-');
            $this->assertStringNotContainsString('menuonl_cart_mex', $contenido, basename($archivo).' lleva la clave del carrito de N');
            $this->assertStringNotContainsString("iniciarPedido('mex'", $contenido, basename($archivo).' inicia el pedido de N');
            $this->assertStringNotContainsString('sitio-mexicana', $contenido, basename($archivo).' carga archivos de N');
        }
        $this->assertGreaterThan(40, $revisados);
    }

    public function test_el_carrito_de_n_no_comparte_clave_con_el_menu_generico(): void
    {
        $this->travelTo(self::ABIERTO);
        $r = $this->completo();

        $this->menu()->assertOk()->assertSee('data-clave="menuonl_cart_mex_verdelux"', false)->assertDontSee('menuonl_cart_m_', false);

        $r->forceFill(['sitio_plantilla' => 'b'])->saveQuietly();
        Cache::flush();
        $this->menu()->assertOk()->assertSee('menuonl_cart_m_verdelux', false)->assertDontSee('menuonl_cart_mex', false);
    }

    public function test_nosotros_muestra_la_descripcion_y_no_hay_cinta_ni_banda_decorativa_con_texto(): void
    {
        $this->travelTo(self::ABIERTO);
        $this->completo();

        $html = (string) $this->sitio()->assertOk()->getContent();

        $this->assertStringContainsString('<h2 id="nosotros-titulo">Nuestra historia</h2>', $html);
        $this->assertStringContainsString('<p class="mex-nosotros__texto">Cena de autor.</p>', $html);
        $this->assertStringContainsString('href="#nosotros">Nosotros</a>', $html);
        $this->assertStringNotContainsString('mex-cinta', $html);
        // Las montañas son un dibujo decorativo, estático y oculto a los lectores de pantalla; no llevan texto ni datos.
        $this->assertMatchesRegularExpression('#<svg class="mex-portada__montes"[^>]*aria-hidden="true"#', $html);
        $this->assertStringNotContainsString('<animate', $html);
        $this->assertStringNotContainsString('@keyframes', (string) file_get_contents(resource_path('css/sitio/n.css')));

        $this->menu()->assertOk()->assertSee(route('sitio.publico', ['slug' => 'verdelux']).'#nosotros', false);
    }

    public function test_los_recomendados_muestran_la_foto_solo_si_el_platillo_la_tiene(): void
    {
        $this->travelTo(self::ABIERTO);
        $r = $this->completo();
        $r->productos()->get()->each(fn (Producto $p) => $p->forceFill(['destacado' => true])->saveQuietly());

        $html = (string) $this->sitio()->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'mex-plato__foto'), 'Solo Rib eye tiene foto.');
        $this->assertStringContainsString('alt="Rib eye"', $html);
        $this->assertStringNotContainsString('role="img"', $html);
    }

    public function test_reservar_en_la_cabecera_es_un_boton_de_acento_solo_con_reservas_activas(): void
    {
        $this->travelTo(self::ABIERTO);
        $r = $this->completo();

        $this->sitio()->assertOk()->assertSee('class="mex-nav__enlace mex-nav__reservar" href="#reservar"', false);
        $this->menu()->assertOk()->assertSee('mex-nav__reservar', false)->assertDontSee('mex-nav__pedir', false);

        $r->forceFill(['reservas_activas' => false])->saveQuietly();
        Cache::flush();
        $this->sitio()->assertOk()->assertDontSee('mex-nav__reservar', false)->assertDontSee('Reservar mesa');
    }

    public function test_las_vistas_de_la_plantilla_no_llevan_colores_sueltos(): void
    {
        $archivos = [
            resource_path('views/sitio/n.blade.php'),
            resource_path('views/menu/plantillas/n.blade.php'),
            ...glob(resource_path('views/components/sitio/mexicana/*.blade.php')),
        ];

        foreach ($archivos as $archivo) {
            $this->assertSame(0, preg_match('/#[0-9a-fA-F]{6}\b/', (string) file_get_contents($archivo)), basename($archivo).' tiene un color suelto');
        }
    }

    public function test_la_hoja_respeta_movimiento_reducido_y_usa_fuentes_propias_ligeras(): void
    {
        $hoja = (string) file_get_contents(resource_path('css/sitio/n.css'));

        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: no-preference\)\s*\{\s*html \{ scroll-behavior: smooth; \}/', $hoja);
        $this->assertStringNotContainsString('http', $hoja);
        $this->assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)')", (string) file_get_contents(resource_path('js/sitio-mexicana.js')));

        preg_match_all('#url\("(/fonts/sitio/[^"]+)"\)#', $hoja, $fuentes);
        $this->assertSame([
            '/fonts/sitio/alfa-slab-one-mexicana-latin-400.woff2',
            '/fonts/sitio/work-sans-mexicana-latin-400.woff2',
            '/fonts/sitio/work-sans-mexicana-latin-600.woff2',
            '/fonts/sitio/work-sans-mexicana-latin-700.woff2',
        ], $fuentes[1]);
        $this->assertStringNotContainsString('font-style: italic', $hoja);

        // Pesos cargados por familia: Alfa Slab One solo 400; Work Sans 400, 600 y 700. Cualquier otro peso se sintetizaría.
        preg_match_all('/@font-face \{\s*font-family: "([^"]+)";[^}]*?font-weight: (\d+);/s', $hoja, $caras, PREG_SET_ORDER);
        $porFamilia = [];
        foreach ($caras as [, $familia, $peso]) {
            $porFamilia[$familia][] = (int) $peso;
        }
        $this->assertSame(['Alfa Slab One Mexicana' => [400], 'Work Sans Mexicana' => [400, 600, 700]], $porFamilia);
        $this->assertDoesNotMatchRegularExpression('/font-weight: (?:100|200|300|500|800|900|bold|bolder)\b/', $hoja, 'Solo hay pesos 400, 600 y 700 cargados.');

        // Toda regla con Alfa Slab One lleva peso 400 (no tiene negrita; h1 a h3 la traen por defecto).
        $sinComentarios = $this->hojaSinComentarios();
        preg_match_all('/([^{}]+)\{([^{}]*var\(--mex-fuente-titulo\)[^{}]*)\}/', $sinComentarios, $conAnton, PREG_SET_ORDER);
        $this->assertGreaterThanOrEqual(4, count($conAnton));
        foreach ($conAnton as [, $selector, $cuerpo]) {
            if (str_contains($cuerpo, '--mex-fuente-titulo:')) {
                continue;
            }
            $this->assertMatchesRegularExpression('/font-weight: 400|font: 400/', $cuerpo, 'Alfa Slab One sin peso 400 en '.trim($selector));
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
        $this->assertSame([], array_values(array_intersect($huellas, $ajenas)), 'Una fuente de N es idéntica a la de otra plantilla: debe tener su propio archivo.');

        foreach (['OFL-alfa-slab-one.txt', 'OFL-work-sans.txt'] as $licencia) {
            $this->assertFileExists(public_path('fonts/sitio/'.$licencia));
            $this->assertStringContainsString('SIL Open Font License', (string) file_get_contents(public_path('fonts/sitio/'.$licencia)));
        }
        $this->assertStringContainsString('alfa-slab-one-mexicana-latin-400.woff2', (string) file_get_contents(resource_path('views/components/sitio/mexicana/layout.blade.php')));
    }

    public function test_la_galeria_fija_proporcion_con_recorte_y_limita_el_ancho_de_cada_foto(): void
    {
        $hoja = (string) file_get_contents(resource_path('css/sitio/n.css'));

        $this->assertMatchesRegularExpression('/\.mex-pagina \.mex-galeria__foto \{[^}]*aspect-ratio: 4 \/ 3;[^}]*object-fit: cover;/', $hoja);
        $this->assertDoesNotMatchRegularExpression('/\.mex-galeria \{[^}]*repeat\(auto-fit/', $hoja, 'auto-fit estira 1 o 2 fotos a todo el ancho.');
        $this->assertMatchesRegularExpression('/\.mex-galeria \{[^}]*repeat\(auto-fill, minmax\(min\(100%, 16rem\), 22rem\)\)/', $hoja);
        $this->assertMatchesRegularExpression('/\.mex-acciones > \.mex-boton \{[^}]*flex: 0 0 auto;[^}]*height: var\(--mex-boton-alto\);/', $hoja);
    }

    public function test_el_diseno_no_desborda_a_360_px(): void
    {
        $hoja = $this->hojaSinComentarios();

        // Todo minmax con un mínimo fijo debe caber en 360 px (20 rem menos el margen); los mayores van envueltos en min(100%, …).
        preg_match_all('/minmax\(\s*(?!min\()([0-9.]+)(rem|px)/', $hoja, $fijos, PREG_SET_ORDER);
        foreach ($fijos as [, $valor, $unidad]) {
            $this->assertLessThanOrEqual(20 * 16, (float) $valor * ($unidad === 'rem' ? 16 : 1), 'minmax con mínimo fijo que no cabe a 360 px');
        }
        $this->assertStringContainsString('minmax(min(100%, 15rem), 1fr)', $hoja);
        $this->assertStringContainsString('overflow-x: clip;', $hoja);
    }

    public function test_un_nombre_largo_en_alfa_slab_one_no_desborda_a_360_px(): void
    {
        $hoja = $this->hojaSinComentarios();

        // El h1 usa Alfa Slab One con un tamaño que baja a 2.125 rem y parte cualquier palabra larga; el diseño no usa mayúsculas en los títulos.
        $this->assertStringContainsString('--mex-t-h1: clamp(2.125rem, 6.5vw + 0.4rem, 4rem);', $hoja);
        $this->assertMatchesRegularExpression('/:where\(\.mex-pagina\) h1,\s*:where\(\.mex-pagina\) h2,\s*:where\(\.mex-pagina\) h3 \{[^}]*overflow-wrap: anywhere;/s', $hoja);
        $this->assertStringContainsString('.mex-portada__titulo { max-width: 100%; }', $hoja);
        $this->assertStringContainsString('.mex-portada__rejilla > * { min-width: 0; max-width: 100%; }', $hoja);
        // El nombre de la cabecera, del pie y de las tarjetas también se parte y la marca no usa nowrap ni puntos suspensivos.
        $this->assertSame(0, preg_match('/white-space: nowrap;[^}]*text-overflow|text-overflow: ellipsis/', $hoja), 'Truncar el nombre lo esconde.');
        foreach (['.mex-marca {', '.mex-pie__nombre {', '.mex-nosotros__texto {', '.mex-portada__eslogan {'] as $regla) {
            $inicio = strpos($hoja, $regla);
            $this->assertNotFalse($inicio, $regla);
            $this->assertStringContainsString('overflow-wrap: anywhere', substr($hoja, $inicio, (int) strpos($hoja, '}', $inicio) - $inicio), $regla);
        }

        $nombre = 'TACOS Y ASADOS DON CHUY DEL NORTE Y COMPAÑÍA SUPERCALIFRAGILÍSTICAEXPIALIDOSA EN LEÑA XX';
        $this->completo(['nombre' => $nombre, 'eslogan' => 'Smash al carbón con papas cortadas a mano para toda la familia']);
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
        $layout = (string) file_get_contents(resource_path('views/components/sitio/mexicana/layout.blade.php'));
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
