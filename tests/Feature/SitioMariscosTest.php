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
 * Plantilla J "Mariscos": sitio (/{slug}) y menú (/{slug}/menu) con su propio diseño, sin depender del estilo del
 * restaurante ni de menuonl.css. Usa withoutVite() para no depender de un build previo de los assets.
 */
class SitioMariscosTest extends TestCase
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
            'nombre' => 'La Bahía',
            'slug' => 'la-bahia',
            'sitio_plantilla' => 'j',
            'eslogan' => 'Del mar a tu mesa',
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
            'descripcion' => 'Mariscos frescos del mar de Cortés.',
            ...$atributos,
        ]);
        $r->forceFill(['portada' => "restaurantes/{$r->id}/portadas/portada.webp"])->saveQuietly();
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'direccion', 'valor' => 'Calle de ejemplo 321']);
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'telefono', 'valor' => '66 2111 2222']);
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'instagram', 'valor' => '@labahia.mar']);
        Horario::factory()->create(['restaurante_id' => $r->id, 'dia_semana' => 3, 'abre' => '18:00:00', 'cierra' => '23:59:00', 'cerrado' => false]);

        $naturales = $this->categoria($r, 'Aguachiles');
        $bebidas = $this->categoria($r, 'Bebidas');
        $casa = $this->platillo($r, 'Aguachile verde', ['precio' => 190, 'precio_oferta' => 175, 'destacado' => true], $naturales);
        $casa->forceFill(['imagen' => "restaurantes/{$r->id}/productos/casa.webp"])->saveQuietly();
        $casa->etiquetas()->attach(Etiqueta::firstOrCreate(['codigo' => 'picante'])->id);
        $this->platillo($r, 'Tostada de callo', ['precio' => 140], $naturales);
        $this->platillo($r, 'Clamato agotado', ['precio' => 60, 'disponible' => false], $bebidas);
        $te = $this->platillo($r, 'Michelada', ['precio' => 40], $bebidas);
        $variante = ProductoVariante::create(['producto_id' => $te->id, 'precio' => 70, 'disponible' => true, 'orden' => 1]);
        VarianteTraduccion::create(['variante_id' => $variante->id, 'idioma' => 'es', 'nombre' => 'Litro']);

        return $r;
    }

    private function sitio(string $slug = 'la-bahia'): TestResponse
    {
        return $this->get(route('sitio.publico', ['slug' => $slug]));
    }

    /**
     * @param  array<string, string>  $consulta
     */
    private function menu(string $slug = 'la-bahia', array $consulta = []): TestResponse
    {
        return $this->get(route('menu.publico', ['slug' => $slug, ...$consulta]));
    }

    public function test_el_catalogo_registra_la_plantilla_j_mariscos(): void
    {
        $this->assertSame(['b', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'p'], array_keys(Catalogos::plantillasSitio()));
        $this->assertSame('Mariscos', Catalogos::plantillasSitio()['j']['nombre']);
        $this->assertSame('j', AparienciaSitio::plantillaVigente('j'));
    }

    public function test_el_panel_tiene_la_ficha_y_no_ofrece_el_estilo_del_menu(): void
    {
        $ficha = AparienciaSitio::fichas()['j'] ?? null;

        $this->assertNotNull($ficha, 'Falta la ficha de la plantilla j en AparienciaSitio::fichas().');
        $this->assertSame('Mariscos', $ficha['nombre']);
        $this->assertNotSame('', $ficha['para']);
        $this->assertNotContains('j', AparienciaSitio::PLANTILLAS_CON_ESTILO);
        $this->assertFalse(AparienciaSitio::usaEstilo('j'));

        $hoja = (string) file_get_contents(resource_path('css/sitio/j.css'));
        foreach (['fondo' => '--mar-marino', 'texto' => '--mar-papel', 'acento' => '--mar-coral', 'bloque' => '--mar-recuadro'] as $campo => $variable) {
            $this->assertStringContainsString($variable.': '.$ficha[$campo].';', $hoja, "El color '{$campo}' de la ficha no sale de la hoja.");
        }
    }

    public function test_admin_permite_elegir_la_plantilla_j(): void
    {
        $restaurante = Restaurante::factory()->create();
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);

        Livewire::test(Apariencia::class)
            ->assertSee('Mariscos')
            ->set('data.sitio_plantilla', 'j')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('restaurantes', ['id' => $restaurante->id, 'sitio_plantilla' => 'j']);
    }

    public function test_el_sitio_se_renderiza_con_un_restaurante_completo(): void
    {
        $this->travelTo(self::ABIERTO);
        $this->completo();

        $respuesta = $this->sitio()->assertOk()->assertViewIs('sitio.j');

        $respuesta->assertSee('data-plantilla="j"', false)->assertSee('sitio-j', false)
            ->assertSee('La Bahía')->assertSee('Del mar a tu mesa')->assertSee('Mariscos frescos del mar de Cortés.')
            ->assertSee('Calle de ejemplo 321')->assertSee('tel:6621112222', false)->assertSee('https://wa.me/525512345678', false)
            ->assertSee('https://instagram.com/labahia.mar', false)->assertSee('18:00 – 23:59')
            ->assertSee('alt="Portada de La Bahía"', false)->assertSee('Sitio creado con '.config('app.name'))
            ->assertSee('Ver menú y pedir')->assertSee('Reservar mesa')->assertSee('Visítanos')->assertSee('Cómo llegar')
            ->assertSee('<svg class="mar-olas"', false)
            ->assertSee(route('menu.publico', ['slug' => 'la-bahia']), false)
            ->assertDontSee('Del mar de Cortés')->assertDontSee('Lo más pedido')->assertDontSee('Te confirmamos por WhatsApp')->assertDontSee('Hecho con Menubly')->assertDontSee('cierra')->assertDontSee('Foto de portada')->assertDontSee('mar-kanji', false);
        $this->assertSame(1, substr_count($respuesta->getContent(), '<h1'));
    }

    public function test_abierto_ahora_y_cerrado_ahora_salen_del_horario_del_restaurante(): void
    {
        $this->completo();

        $this->travelTo(self::ABIERTO);
        $this->sitio()->assertOk()->assertSee('<p class="mar-estado mar-estado--abierto">Abierto ahora</p>', false)->assertSee('Hoy: 18:00 – 23:59');
        $this->menu()->assertOk()->assertSee('<p class="mar-estado mar-estado--abierto">Abierto</p>', false);

        Cache::flush();
        $this->travelTo(self::CERRADO);
        $this->sitio()->assertOk()->assertSee('<p class="mar-estado">Cerrado ahora</p>', false)->assertDontSee('Abierto ahora');
        $this->menu()->assertOk()->assertSee('<p class="mar-estado">Cerrado</p>', false);
    }

    public function test_sin_pedidos_el_boton_de_portada_no_promete_pedir(): void
    {
        $this->completo(['pedidos_activos' => false]);

        $this->sitio()->assertOk()->assertSee('>Ver el menú</a>', false)->assertDontSee('Ver menú y pedir');
    }

    public function test_los_recomendados_ocultan_los_agotados_y_muestran_el_precio_vigente(): void
    {
        $this->completo()->productos()->where('id', '>', 0)->get()->each(fn ($p) => $p->forceFill(['destacado' => true])->saveQuietly());

        $this->sitio()->assertOk()
            ->assertSee('<h2 id="destacados-titulo">Recomendados</h2>', false)
            ->assertSee('$175.00')->assertSee('<s class="mar-precio__antes">', false)->assertSee('$190.00')
            ->assertSee('Tostada de callo')->assertSee('Picante')->assertDontSee('Clamato agotado');
    }

    public function test_el_horario_marca_el_dia_de_hoy_segun_la_zona_del_restaurante(): void
    {
        $this->travelTo('2026-10-22 01:00:00'); // miércoles 19:00 en Ciudad de México, jueves en UTC
        $this->completo();

        $html = (string) $this->sitio()->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'aria-current="date"'));
        $this->assertStringContainsString('Miércoles<span class="mar-solo-lectores"> (hoy)</span>', $html);
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
            ->assertSee('alt="Foto de La Bahía"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $respuesta->assertSeeInOrder(['galeria/primera.webp', 'galeria/segunda.webp', 'galeria/tercera.webp'], false);

        $this->menu()->assertOk()->assertSee(route('sitio.publico', ['slug' => 'la-bahia']).'#galeria', false);
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
            ->assertSee('alt="Foto 1 de La Bahía"', false)->assertSee('alt="Foto 2 de La Bahía"', false)
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

    public function test_el_menu_se_renderiza_con_la_vista_de_la_plantilla_j(): void
    {
        $this->travelTo(self::ABIERTO);
        $r = $this->completo();
        $categorias = Categoria::where('restaurante_id', $r->id)->orderBy('id')->get();

        $respuesta = $this->menu()->assertOk()->assertViewIs('menu.plantillas.j');

        $respuesta->assertSee('Aguachile verde')->assertSee('Tostada de callo')->assertSee('Aguachiles')->assertSee('Bebidas')
            ->assertSee('$175.00')->assertSee('<s class="mar-precio__antes">', false)->assertSee('$190.00')
            ->assertSee('Agotado')->assertSee('Litro')->assertSee('$70.00')
            ->assertSee('data-mar-categorias', false)->assertSee('Pedir por WhatsApp')
            ->assertSee('Calle de ejemplo 321')->assertSee('>Menú</h1>', false)
            ->assertSee(route('sitio.publico', ['slug' => 'la-bahia']).'#reservar', false)
            ->assertSee('aria-label="Agregar Tostada de callo al pedido"', false);
        foreach ($categorias as $categoria) {
            $respuesta->assertSee('href="#categoria-'.$categoria->id.'"', false)->assertSee('id="categoria-'.$categoria->id.'"', false);
        }
        $this->assertSame(1, substr_count($respuesta->getContent(), '<h1'));
        $respuesta->assertDontSee('css/menuonl.css', false)->assertDontSee('data-tema', false)->assertDontSee('Carta')->assertDontSee('mar-kanji', false);
    }

    public function test_con_una_sola_categoria_no_hay_barra_de_categorias(): void
    {
        $r = $this->restaurante();
        $categoria = $this->categoria($r, 'Tostadas');
        $this->platillo($r, 'Tostada de ceviche', ['precio' => 165], $categoria);
        $this->platillo($r, 'Tostada de pulpo', ['precio' => 170], $categoria);

        $this->menu()->assertOk()->assertSee('Tostada de ceviche')->assertDontSee('data-mar-categorias', false);
    }

    public function test_la_mesa_se_ignora_por_completo(): void
    {
        $this->travelTo(self::ABIERTO);
        $this->completo();
        $base = $this->contenido($this->menu());

        foreach (['3', '<b>x</b>'] as $mesa) {
            $respuesta = $this->menu('la-bahia', ['mesa' => $mesa])->assertOk()
                ->assertDontSee('Mesa 3')->assertDontSee('mar-mesa', false)->assertDontSee('data-mesa', false)->assertDontSee('id="pedido-mesa"', false);
            $this->assertSame($base, $this->contenido($respuesta), 'El parámetro ?mesa= cambió el menú.');
        }
        $this->sitio()->assertOk()->assertDontSee('mar-mesa', false);
    }

    public function test_las_otras_plantillas_siguen_usando_las_mismas_vistas(): void
    {
        foreach (['b', 'd', 'e', 'f', 'g', 'h', 'i'] as $clave) {
            $restaurante = $this->completo(['sitio_plantilla' => $clave, 'slug' => 'otro-'.$clave]);

            $sitio = $this->sitio('otro-'.$clave)->assertOk()->assertViewIs('sitio.'.$clave);
            $menu = $this->menu('otro-'.$clave)->assertOk()->assertViewIs(in_array($clave, ['e', 'f', 'g', 'h', 'i'], true) ? 'menu.plantillas.'.$clave : 'menu.publico');
            foreach ([$sitio, $menu] as $respuesta) {
                $this->assertStringNotContainsString('sitio-j', $this->contenido($respuesta));
                $this->assertStringNotContainsString('data-mar-', $this->contenido($respuesta));
                $this->assertStringNotContainsString('class="mar-', $this->contenido($respuesta));
                $this->assertStringNotContainsString('menuonl_cart_j', $this->contenido($respuesta));
            }
            $restaurante->delete();
        }
    }

    public function test_cambiar_de_j_a_otra_plantilla_vuelve_al_diseno_anterior(): void
    {
        $restaurante = $this->completo();
        $this->menu()->assertViewIs('menu.plantillas.j');

        $restaurante->forceFill(['sitio_plantilla' => 'b'])->saveQuietly();

        $this->sitio()->assertViewIs('sitio.b');
        $this->menu()->assertViewIs('menu.publico');
    }

    public function test_sin_datos_opcionales_se_omiten_las_secciones_sin_textos_de_relleno(): void
    {
        $this->restaurante(['eslogan' => null, 'descripcion' => null, 'portada' => null, 'whatsapp_pedidos' => null, 'reservas_activas' => false]);

        $sitio = $this->sitio()->assertOk();
        foreach (['id="visitanos"', 'id="horario"', 'id="ubicacion"', 'id="reservar"', 'id="destacados"', 'id="galeria"', 'mar-portada__foto', 'mar-portada__eslogan', 'mar-portada__descripcion', 'Hoy:', 'mar-estado', 'wa.me', 'tel:', 'Reservar', '>Horario<', '>Galería<'] as $ausente) {
            $sitio->assertDontSee($ausente, false);
        }
        $sitio->assertSee('mar-portada__rejilla mar-portada__rejilla--sin-foto', false)->assertSee('>Ver el menú</a>', false);

        $menu = $this->menu()->assertOk();
        foreach (['id="visitanos"', 'wa.me', 'data-mar-categorias', 'Pedir por WhatsApp', 'data-mar-pedido'] as $ausente) {
            $menu->assertDontSee($ausente, false);
        }
        $menu->assertSee('Este menú se está preparando.');
    }

    public function test_un_platillo_sin_foto_muestra_un_recuadro_neutro_y_con_foto_su_imagen(): void
    {
        $r = $this->restaurante();
        $this->platillo($r, 'Coctel campechano', ['precio' => 155]);
        $conFoto = $this->platillo($r, 'Pescado zarandeado', ['precio' => 120]);
        $conFoto->forceFill(['imagen' => "restaurantes/{$r->id}/productos/kushiage.webp"])->saveQuietly();

        $html = (string) $this->menu()->assertOk()->getContent();

        $this->assertStringContainsString('alt="Pescado zarandeado"', $html);
        $this->assertStringContainsString('aria-label="Coctel campechano (foto pendiente)"', $html);
        $this->assertSame(1, substr_count($html, 'foto-vacia'));
    }

    public function test_el_logo_se_muestra_junto_al_nombre_solo_si_existe_y_con_tamano_fijo(): void
    {
        $r = $this->restaurante(['logo' => null]);
        foreach ([$this->sitio(), $this->menu()] as $respuesta) {
            $respuesta->assertOk()->assertDontSee('mar-marca__logo', false)->assertSee('<span>La Bahía</span>', false);
        }

        $r->forceFill(['logo' => "restaurantes/{$r->id}/logo.webp"])->saveQuietly();
        Cache::flush();

        foreach ([$this->sitio(), $this->menu()] as $respuesta) {
            $respuesta->assertOk()->assertSee('mar-marca__logo', false)->assertSee('<span>La Bahía</span>', false);
        }
        $this->assertStringContainsString('.mar-pagina .mar-marca__logo { flex: none; width: 2.875rem; height: 2.875rem; object-fit: contain; }', (string) file_get_contents(resource_path('css/sitio/j.css')));
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
            $respuesta->assertOk()->assertDontSee($hostilCorto, false)->assertDontSee('--mar-acento:', false);
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

        $this->sitio()->assertOk()->assertSee('--mar-acento: #1f5f3f; --mar-texto-acento: #ffffff;', false);
        $this->menu()->assertOk()->assertSee('--mar-acento: #1f5f3f;', false);

        $r->forceFill(['color_acento' => '#808080'])->saveQuietly();
        Cache::flush();
        $this->sitio()->assertOk()->assertDontSee('--mar-acento:', false);
    }

    public function test_los_textos_y_los_datos_del_restaurante_van_escapados(): void
    {
        $this->completo(['nombre' => 'La Bahía <script>alert(1)</script>', 'eslogan' => '"><img src=x onerror=alert(1)>', 'descripcion' => '<b>hola</b>']);

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
        $inicio = route('sitio.publico', ['slug' => 'la-bahia']);
        $menu = route('menu.publico', ['slug' => 'la-bahia']);

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

    public function test_la_palabra_carta_no_aparece_en_la_interfaz_de_i(): void
    {
        $archivos = [
            resource_path('views/sitio/j.blade.php'),
            resource_path('views/menu/plantillas/j.blade.php'),
            resource_path('js/sitio-mariscos.js'),
            resource_path('js/sitio-mariscos-pedido.js'),
            ...glob(resource_path('views/components/sitio/mariscos/*.blade.php')),
        ];

        foreach ($archivos as $archivo) {
            $this->assertSame(0, preg_match('/\bcarta\b/i', (string) file_get_contents($archivo)), basename($archivo).' dice "carta"');
        }
    }

    public function test_la_hoja_usa_prefijo_mar_sin_chocar_con_menuonl_css_y_colores_solo_en_variables(): void
    {
        $hoja = (string) file_get_contents(resource_path('css/sitio/j.css'));
        $compartida = (string) file_get_contents(public_path('css/menuonl.css'));

        preg_match_all('/\.([a-z][a-z0-9_-]*)/i', (string) preg_replace('/url\([^)]*\)|"[^"]*"|\d+\.\d+/', '', $hoja), $coincidencias);
        $permitidas = ['foto', 'foto-vacia', 'sitio', 'sitio-j'];

        foreach (array_unique($coincidencias[1]) as $clase) {
            if (in_array($clase, $permitidas, true)) {
                continue;
            }
            $this->assertStringStartsWith('mar-', $clase, "La clase .$clase no lleva el prefijo mar-");
            $this->assertStringNotContainsString('.'.$clase, $compartida, "La clase .$clase choca con menuonl.css");
        }

        $sinRaiz = (string) preg_replace('/:root\s*\{.*?\n\}/s', '', $hoja);
        $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b/', $sinRaiz), 'Hay un color hexadecimal fuera de :root');
        $this->assertSame(0, preg_match('/\b(?:rgb|hsl)a?\(/i', $sinRaiz), 'Hay un color rgb/hsl fuera de :root');
    }

    public function test_los_pares_de_texto_de_la_hoja_llegan_a_4_5_de_contraste(): void
    {
        $hoja = (string) file_get_contents(resource_path('css/sitio/j.css'));
        preg_match_all('/--(mar-[a-z-]+): (#[0-9a-f]{6});/', $hoja, $definidos);
        $color = array_combine($definidos[1], $definidos[2]);
        $luz = function (string $hex): float {
            [$r, $g, $b] = array_map(fn (string $par): float => hexdec($par) / 255, str_split(ltrim($hex, '#'), 2));
            $lineal = fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

            return 0.2126 * $lineal($r) + 0.7152 * $lineal($g) + 0.0722 * $lineal($b);
        };
        $contraste = fn (string $a, string $b): float => (max($luz($color[$a]), $luz($color[$b])) + 0.05) / (min($luz($color[$a]), $luz($color[$b])) + 0.05);

        $pares = [
            ['mar-marino', 'mar-papel'], ['mar-marino', 'mar-blanco'], ['mar-papel', 'mar-marino'], ['mar-blanco', 'mar-marino'],
            ['mar-coral-texto', 'mar-papel'], ['mar-coral-texto', 'mar-blanco'], ['mar-blanco', 'mar-coral'],
            ['mar-texto-suave', 'mar-papel'], ['mar-texto-suave', 'mar-blanco'],
            ['mar-claro-sobre-marino', 'mar-marino'], ['mar-claro-sobre-marino', 'mar-marino-medio'], ['mar-papel', 'mar-marino-medio'],
            ['mar-coral-claro', 'mar-marino'], ['mar-error', 'mar-blanco'],
        ];
        foreach ($pares as [$texto, $fondo]) {
            $this->assertGreaterThanOrEqual(4.5, $contraste($texto, $fondo), "{$texto} sobre {$fondo} no llega a 4.5:1");
        }

        $this->assertSame('#b84328', $color['mar-coral-texto']);
        $this->assertSame('#678190', $color['mar-borde-campo']);
        foreach ([['mar-borde-campo', 'mar-blanco'], ['mar-borde-campo', 'mar-papel'], ['mar-blanco', 'mar-marino'], ['mar-borde-sobre-marino', 'mar-marino']] as [$borde, $fondo]) {
            $this->assertGreaterThanOrEqual(3.0, $contraste($borde, $fondo), "{$borde} sobre {$fondo} no llega a 3:1");
        }
    }

    public function test_el_coral_base_solo_se_usa_en_fondos_y_ningun_hover_pone_coral_como_texto(): void
    {
        $hoja = (string) file_get_contents(resource_path('css/sitio/j.css'));
        $sinRaiz = (string) preg_replace('/:root\s*\{.*?\n\}/s', '', $hoja);

        $this->assertSame(0, preg_match('/(?<![-a-z])color:\s*var\(--mar-coral\)/', $sinRaiz), 'El coral base se usa como color de texto (4.47:1 sobre papel).');
        $this->assertSame(0, preg_match('/:hover[^{]*\{[^}]*(?<![-a-z])color:\s*var\(--mar-coral(?:-claro)?\)/', $sinRaiz), 'Un hover pone coral como texto.');
        foreach (['.mar-precio {', '.mar-enlace-fuerte {'] as $regla) {
            $this->assertStringContainsString('color: var(--mar-coral-texto)', substr($sinRaiz, (int) strpos($sinRaiz, $regla), 400), $regla);
        }
    }

    public function test_b_e_i_no_llevan_marcas_de_la_plantilla_j(): void
    {
        $this->travelTo(self::ABIERTO);
        $r = $this->completo(['sitio_plantilla' => 'b']);
        $this->sitio()->assertOk()->assertViewIs('sitio.b')->assertDontSee('class="mar-', false)->assertDontSee('sitio-j', false);
        $this->menu()->assertOk()->assertViewIs('menu.publico')->assertDontSee('class="mar-', false);

        $r->forceFill(['sitio_plantilla' => 'i'])->saveQuietly();
        Cache::flush();
        $this->sitio()->assertOk()->assertViewIs('sitio.i')->assertSee('class="jap-', false)->assertDontSee('class="mar-', false)->assertDontSee('sitio-j', false);
        $this->menu()->assertOk()->assertViewIs('menu.plantillas.i')->assertSee('data-jap-', false)->assertDontSee('data-mar-', false)->assertDontSee('menuonl_cart_j', false);
    }

    public function test_las_vistas_de_la_plantilla_no_llevan_colores_sueltos(): void
    {
        $archivos = [
            resource_path('views/sitio/j.blade.php'),
            resource_path('views/menu/plantillas/j.blade.php'),
            ...glob(resource_path('views/components/sitio/mariscos/*.blade.php')),
        ];

        foreach ($archivos as $archivo) {
            $this->assertSame(0, preg_match('/#[0-9a-fA-F]{6}\b/', (string) file_get_contents($archivo)), basename($archivo).' tiene un color suelto');
        }
    }

    public function test_la_hoja_respeta_movimiento_reducido_y_usa_fuentes_propias_ligeras(): void
    {
        $hoja = (string) file_get_contents(resource_path('css/sitio/j.css'));

        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: no-preference\)\s*\{\s*html \{ scroll-behavior: smooth; \}/', $hoja);
        $this->assertStringNotContainsString('http', $hoja);
        $this->assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)')", (string) file_get_contents(resource_path('js/sitio-mariscos.js')));

        preg_match_all('#url\("(/fonts/sitio/[^"]+)"\)#', $hoja, $fuentes);
        $this->assertSame([
            '/fonts/sitio/dm-serif-display-es-latin-400.woff2',
            '/fonts/sitio/outfit-latin-400.woff2',
            '/fonts/sitio/outfit-latin-600.woff2',
        ], $fuentes[1]);
        $peso = 0;
        $huellas = [];
        foreach ($fuentes[1] as $ruta) {
            $this->assertFileExists(public_path($ruta));
            $peso += filesize(public_path($ruta));
            $huellas[] = hash_file('sha256', public_path($ruta));
        }
        $this->assertLessThan(60 * 1024, $peso, 'Las fuentes de la plantilla deben ser un subconjunto ligero.');
        $this->assertCount(3, array_unique($huellas), 'Cada fuente debe ser un archivo distinto.');
        foreach (['OFL-dm-serif-display-regular.txt', 'OFL-outfit.txt'] as $licencia) {
            $this->assertFileExists(public_path('fonts/sitio/'.$licencia));
            $this->assertStringContainsString('SIL Open Font License', (string) file_get_contents(public_path('fonts/sitio/'.$licencia)));
        }
    }

    public function test_el_selector_de_idioma_del_menu_usa_route_con_lang(): void
    {
        $r = $this->completo();
        RestauranteIdioma::factory()->create(['restaurante_id' => $r->id, 'idioma' => 'en']);

        $this->menu()->assertOk()->assertSee(route('menu.publico', ['slug' => 'la-bahia', 'lang' => 'en']), false);
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
        $layout = (string) file_get_contents(resource_path('views/components/sitio/mariscos/layout.blade.php'));
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
