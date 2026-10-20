<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\Contacto;
use App\Models\Etiqueta;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Services\Catalogos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Plantilla D "Editorial oscuro" de /s/{slug}: contenido solo de los datos del restaurante, acento seguro,
 * escapado, sin recursos externos ni emojis. Usa withoutVite() para no depender de un build previo de los assets.
 */
class SitioEditorialOscuroTest extends TestCase
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
        Carbon::setTestNow();
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    private function restaurante(array $atributos = []): Restaurante
    {
        return Restaurante::factory()->create([
            'estado' => 'activo',
            'nombre' => 'La Casa Negra',
            'slug' => 'la-casa-negra',
            'sitio_plantilla' => 'd',
            'zona_horaria' => 'America/Mexico_City',
            ...$atributos,
        ]);
    }

    private function platillo(Restaurante $restaurante, string $nombre, array $atributos = []): Producto
    {
        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Fuertes de '.$restaurante->slug]);
        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id, 'destacado' => true, ...$atributos]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => $nombre, 'descripcion' => 'Descripción de '.$nombre]);

        return $producto;
    }

    private function restauranteCompleto(): Restaurante
    {
        $r = $this->restaurante([
            'eslogan' => 'Fuego y temporada',
            'descripcion' => 'Una cocina pequeña con brasas.',
            'whatsapp_pedidos' => '+52 55 1234 5678',
            'reservas_activas' => true,
        ]);
        $r->forceFill(['portada' => "restaurantes/{$r->id}/portadas/portada.webp"])->saveQuietly();
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'direccion', 'valor' => 'Calle Real 45']);
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'telefono', 'valor' => '55 1111 2222']);
        Horario::factory()->create(['restaurante_id' => $r->id, 'dia_semana' => 3, 'abre' => '13:00:00', 'cierra' => '23:00:00', 'cerrado' => false]);
        $risotto = $this->platillo($r, 'Risotto de hongos', ['precio' => 280]);
        $risotto->forceFill(['imagen' => "restaurantes/{$r->id}/productos/risotto.webp"])->saveQuietly();

        return $r;
    }

    private function sitio(string $slug = 'la-casa-negra'): TestResponse
    {
        return $this->get('/s/'.$slug);
    }

    public function test_el_catalogo_acepta_d_y_rechaza_otros_valores(): void
    {
        $this->assertSame(['a', 'b', 'c', 'd'], array_keys(Catalogos::plantillasSitio()));
        $this->assertSame('Editorial oscuro', Catalogos::plantillasSitio()['d']['nombre']);
        $this->assertArrayNotHasKey('e', Catalogos::plantillasSitio());
    }

    public function test_un_valor_de_plantilla_desconocido_sigue_cayendo_a_la_plantilla_a(): void
    {
        $this->restaurante()->forceFill(['sitio_plantilla' => 'e'])->saveQuietly();

        $this->sitio()->assertOk()->assertSee('data-plantilla="a"', false);
    }

    public function test_la_plantilla_d_se_renderiza_con_un_restaurante_completo(): void
    {
        $this->restauranteCompleto();

        $respuesta = $this->sitio()->assertOk();

        $respuesta->assertSee('data-plantilla="d"', false)->assertSee('sitio-d', false)
            ->assertSee('La Casa Negra')->assertSee('<em>Negra</em>', false)
            ->assertSee('Fuego y temporada')->assertSee('Una cocina pequeña con brasas.')
            ->assertSee('Risotto de hongos')->assertSee('Fuertes de la-casa-negra')->assertSee('$280.00')
            ->assertSee('Calle Real 45')->assertSee('tel:5511112222', false)->assertSee('https://wa.me/525512345678', false)
            ->assertSee('13:00 – 23:00')->assertSee('id="form-reserva"', false)
            ->assertSee('alt="Portada de La Casa Negra"', false)->assertSee('alt="Risotto de hongos"', false)
            ->assertSee('Sitio creado con Menuonl')
            ->assertSee('--acento: #c9a24a;', false);
        $this->assertSame(1, substr_count($respuesta->getContent(), '<h1'));
    }

    public function test_sin_ningun_dato_opcional_se_omiten_las_secciones_y_no_hay_errores(): void
    {
        $this->restaurante(['reservas_activas' => false, 'whatsapp_pedidos' => null, 'eslogan' => null, 'descripcion' => null, 'portada' => null]);

        $respuesta = $this->sitio()->assertOk();

        $respuesta->assertSee('La Casa Negra')->assertSee('Ver carta');
        foreach (['id="historia"', 'id="recomendados"', 'id="reservar"', 'id="horario"', 'id="contacto"', 'd-eslogan', 'd-estado', 'href="#reservar"'] as $ausente) {
            $respuesta->assertDontSee($ausente, false);
        }
        $respuesta->assertSee('d-vacio', false);
        $this->assertSame(1, substr_count($respuesta->getContent(), '<h1'));
    }

    public function test_sin_reservas_en_linea_pero_con_telefono_se_muestran_los_medios_alternos(): void
    {
        $r = $this->restaurante(['reservas_activas' => false, 'whatsapp_pedidos' => null]);
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'telefono', 'valor' => '55 1111 2222']);

        $this->sitio()->assertOk()->assertSee('id="reservar"', false)->assertSee('Por ahora no recibimos reservas en línea.')
            ->assertSee('tel:5511112222', false)->assertDontSee('id="form-reserva"', false)->assertDontSee('href="#reservar"', false);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function acentosInvalidos(): array
    {
        return [
            'texto libre' => ['red;}x'],
            'sin almohadilla' => ['c9a24a1'],
            'caracteres no hex' => ['#GGGGGG'],
            'etiqueta' => ['"><b>'],
            'url' => ['url(x)'],
            'corto' => ['#fff'],
            'script' => ['<svg/o>'],
        ];
    }

    #[DataProvider('acentosInvalidos')]
    public function test_un_acento_invalido_o_malicioso_cae_al_dorado_y_no_se_inyecta(string $valor): void
    {
        $this->restaurante()->forceFill(['color_acento' => $valor])->saveQuietly();

        $html = $this->sitio()->assertOk()->getContent();

        $this->assertStringContainsString('--acento: #c9a24a;', $html);
        $this->assertStringNotContainsString($valor, $html);
        $this->assertStringNotContainsString(e($valor), $html);
    }

    public function test_un_acento_valido_y_legible_se_usa_con_un_texto_de_boton_legible(): void
    {
        $this->restaurante(['color_acento' => '#e07b39']);

        $this->sitio()->assertOk()->assertSee('--acento: #e07b39;', false)->assertSee('--acento-texto: #', false);
    }

    public function test_un_acento_valido_pero_ilegible_sobre_fondo_oscuro_cae_al_dorado(): void
    {
        $this->restaurante(['color_acento' => '#1a1a1a']);

        $this->sitio()->assertOk()->assertSee('--acento: #c9a24a;', false)->assertDontSee('#1a1a1a', false);
    }

    public function test_el_texto_del_boton_sobre_el_dorado_por_defecto_tiene_contraste_aa(): void
    {
        $this->restaurante();

        $this->sitio()->assertOk()->assertSee('--acento-texto: #0c0b0a;', false);
    }

    public function test_los_textos_con_html_o_script_aparecen_escapados(): void
    {
        $malo = '<script>alert(1)</script><img src=x onerror=alert(1)>';
        $r = $this->restaurante(['nombre' => $malo.' Final', 'eslogan' => $malo, 'descripcion' => $malo]);
        $this->platillo($r, $malo);
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'direccion', 'valor' => $malo]);

        $html = $this->sitio()->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString(e($malo), $html);
    }

    public function test_el_html_no_trae_recursos_ni_urls_externas_ni_emojis(): void
    {
        $this->restaurante(['eslogan' => 'Fuego', 'descripcion' => 'Brasas', 'whatsapp_pedidos' => null]);
        $this->platillo($this->restaurante(['slug' => 'otra', 'nombre' => 'Otra', 'sitio_plantilla' => 'd']), 'Plato');

        $html = $this->sitio()->assertOk()->getContent();
        $propio = parse_url(url('/'), PHP_URL_HOST);

        preg_match_all('#https?://([^/:"\'\s)]+)#i', $html, $hosts);
        foreach ($hosts[1] as $host) {
            $this->assertSame($propio, $host, "URL externa en el HTML: {$host}");
        }
        $this->assertSame(0, preg_match('/[\x{1F000}-\x{1FFFF}\x{2190}-\x{2BFF}\x{2705}\x{2713}\x{2714}\x{2728}\x{FE0F}]/u', $html));
        $this->assertStringNotContainsString('fonts.googleapis', $html);
    }

    public function test_con_datos_completos_solo_los_enlaces_de_contacto_apuntan_fuera_y_ningun_recurso(): void
    {
        $r = $this->restauranteCompleto();
        Contacto::factory()->create(['restaurante_id' => $r->id, 'tipo' => 'instagram', 'valor' => 'casanegra']);

        $html = $this->sitio()->assertOk()->getContent();

        $this->assertSame(0, preg_match('#<(?:script|link|img|iframe|source)\b[^>]*\s(?:src|href|srcset)="https?://(?!'.preg_quote((string) parse_url(url('/'), PHP_URL_HOST), '#').')#i', $html));
        $this->assertSame(0, preg_match('#<form\b[^>]*\saction="https?://#i', $html));
        $this->assertSame(0, preg_match('#<script(?![^>]*\ssrc=)#i', $html), 'Sin JavaScript en línea.');
        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html), 'Sin atributos de evento.');
        $this->assertStringContainsString('https://instagram.com/casanegra', $html);
    }

    public function test_muestra_precio_con_descuento_y_etiquetas_del_platillo(): void
    {
        $r = $this->restaurante();
        $producto = $this->platillo($r, 'Pulpo', ['precio' => 300, 'precio_oferta' => 240]);
        $producto->etiquetas()->attach([
            Etiqueta::factory()->create(['codigo' => 'sin_gluten'])->id,
            Etiqueta::factory()->create(['codigo' => 'picante'])->id,
        ]);

        $this->sitio()->assertOk()->assertSee('$240.00')->assertSee('$300.00')->assertSee('Precio anterior')
            ->assertSee('Sin gluten')->assertSee('Picante');
    }

    public function test_un_platillo_sin_foto_muestra_un_bloque_plano_sin_texto_de_relleno(): void
    {
        $r = $this->restaurante();
        $this->platillo($r, 'Sin foto', ['imagen' => null]);

        $respuesta = $this->sitio()->assertOk();

        $respuesta->assertSee('Sin foto')->assertSee('d-vacio', false)->assertDontSee('foto pendiente');
    }

    public function test_el_estado_abierto_o_cerrado_sale_del_horario_del_restaurante(): void
    {
        $r = $this->restaurante();
        Horario::factory()->create(['restaurante_id' => $r->id, 'dia_semana' => 3, 'abre' => '13:00:00', 'cierra' => '23:00:00', 'cerrado' => false]);

        Carbon::setTestNow('2026-10-21 20:00:00');
        $this->sitio()->assertOk()->assertSee('Abierto ahora')->assertDontSee('Cerrado ahora');

        Carbon::setTestNow('2026-10-21 12:00:00');
        $this->sitio()->assertOk()->assertSee('Cerrado ahora')->assertDontSee('Abierto ahora');
    }

    public function test_sin_horario_no_se_afirma_que_el_restaurante_este_cerrado(): void
    {
        $this->restaurante();

        $this->sitio()->assertOk()->assertDontSee('Cerrado ahora')->assertDontSee('Abierto ahora');
    }

    public function test_no_mezcla_datos_de_otro_restaurante(): void
    {
        $this->restauranteCompleto();
        $otro = $this->restaurante(['slug' => 'ajeno', 'nombre' => 'Ajeno', 'eslogan' => 'Eslogan ajeno', 'descripcion' => 'Descripción ajena']);
        $this->platillo($otro, 'Plato ajeno');
        Contacto::factory()->create(['restaurante_id' => $otro->id, 'tipo' => 'direccion', 'valor' => 'Calle Ajena 9']);

        $html = $this->sitio()->assertOk()->getContent();

        foreach (['Ajeno', 'Plato ajeno', 'Calle Ajena 9', 'Eslogan ajeno', 'Descripción ajena'] as $ajeno) {
            $this->assertStringNotContainsString($ajeno, $html);
        }
        $this->assertNull(RestauranteScope::getRestauranteId());
    }

    public function test_la_plantilla_d_conserva_cache_y_cabeceras_del_controlador_y_el_404(): void
    {
        $this->restaurante();

        $d = $this->sitio();
        $d->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertStringContainsString('max-age=30', (string) $d->headers->get('Cache-Control'));
        $this->sitio('no-existe')->assertNotFound();
    }

    public function test_vite_compila_la_hoja_de_la_plantilla_d(): void
    {
        $this->assertFileExists(resource_path('css/sitio/d.css'));
        $this->assertStringContainsString("'resources/css/sitio/d.css'", (string) file_get_contents(base_path('vite.config.js')));
    }

    public function test_las_plantillas_a_b_y_c_siguen_funcionando(): void
    {
        $r = $this->restaurante();

        foreach (['a', 'b', 'c'] as $clave) {
            $r->forceFill(['sitio_plantilla' => $clave])->saveQuietly();
            $this->sitio()->assertOk()->assertSee('data-plantilla="'.$clave.'"', false)->assertSee('La Casa Negra');
        }
    }
}
