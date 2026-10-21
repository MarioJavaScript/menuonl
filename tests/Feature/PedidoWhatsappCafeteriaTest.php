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
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use App\Models\VarianteTraduccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pedido por WhatsApp en el menú de la plantilla E: quién puede pedir, qué datos entrega el servidor al script y qué no se dibuja.
 * La lógica del navegador (mensaje, límites, recorte del enlace) se prueba en tests/Js/pedido-mensaje.test.mjs.
 */
class PedidoWhatsappCafeteriaTest extends TestCase
{
    use RefreshDatabase;

    /** Miércoles 12:00 en Ciudad de México (18:00 UTC): dentro del horario de los restaurantes de estas pruebas. */
    private const ABIERTO = '2026-10-21 18:00:00';

    private const CERRADO = '2026-10-21 04:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->withoutVite();
        $this->travelTo(self::ABIERTO);
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
        $restaurante = Restaurante::factory()->create([
            'nombre' => 'Café Mezquite',
            'slug' => 'cafe-mezquite',
            'sitio_plantilla' => 'e',
            'zona_horaria' => 'America/Mexico_City',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '+52 (55) 1234-5678',
            'mostrar_agotados' => true,
            ...$atributos,
        ]);
        Horario::factory()->create(['restaurante_id' => $restaurante->id, 'dia_semana' => 3, 'abre' => '07:00:00', 'cierra' => '21:00:00', 'cerrado' => false]);

        return $restaurante;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function platillo(Restaurante $restaurante, string $nombre, array $atributos = []): Producto
    {
        $categoria = Categoria::where('restaurante_id', $restaurante->id)->first()
            ?? Categoria::factory()->create(['restaurante_id' => $restaurante->id, 'visible' => true]);
        CategoriaTraduccion::firstOrCreate(['categoria_id' => $categoria->id, 'idioma' => 'es'], ['nombre' => 'Bebidas']);
        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id, 'visible' => true, 'disponible' => true, ...$atributos]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => $nombre]);

        return $producto;
    }

    private function variante(Producto $producto, string $nombre, float $precio, bool $disponible = true): ProductoVariante
    {
        $variante = ProductoVariante::create(['producto_id' => $producto->id, 'precio' => $precio, 'disponible' => $disponible, 'orden' => 1]);
        VarianteTraduccion::create(['variante_id' => $variante->id, 'idioma' => 'es', 'nombre' => $nombre]);

        return $variante;
    }

    private function menu(string $consulta = ''): TestResponse
    {
        return $this->get('/cafe-mezquite/menu'.$consulta);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function condiciones(): array
    {
        return [
            'activo, abierto y con número válido' => [[], true],
            'pedidos desactivados' => [['pedidos_activos' => false], false],
            'sin número' => [['whatsapp_pedidos' => null], false],
            'número vacío' => [['whatsapp_pedidos' => '   '], false],
            'número de 7 dígitos' => [['whatsapp_pedidos' => '555-1234'], false],
            'número con letras' => [['whatsapp_pedidos' => 'llámanos'], false],
            'número de 8 dígitos (mínimo)' => [['whatsapp_pedidos' => '12345678'], true],
            'número de 20 dígitos (máximo)' => [['whatsapp_pedidos' => '12345678901234567890'], true],
        ];
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    #[DataProvider('condiciones')]
    public function test_el_pedido_solo_aparece_con_pedidos_activos_y_un_numero_valido(array $atributos, bool $aparece): void
    {
        $restaurante = $this->restaurante($atributos);
        $this->platillo($restaurante, 'Latte', ['precio' => 55]);

        $respuesta = $this->menu()->assertOk();

        foreach (['data-cal-pedido', 'data-cal-agregar', 'data-cal-sin-js', 'Pedir por WhatsApp', 'Enviar por WhatsApp'] as $marca) {
            $aparece ? $respuesta->assertSee($marca, false) : $respuesta->assertDontSee($marca, false);
        }
    }

    public function test_el_numero_se_limpia_a_digitos_en_el_servidor(): void
    {
        $restaurante = $this->restaurante(['whatsapp_pedidos' => '+52 (55) 1234-5678']);
        $this->platillo($restaurante, 'Latte', ['precio' => 55]);

        $this->menu()->assertOk()
            ->assertSee('data-numero="525512345678"', false)
            ->assertSee('href="https://wa.me/525512345678"', false)
            ->assertDontSee('+52 (55)', false);
    }

    public function test_el_modelo_valida_de_8_a_20_digitos(): void
    {
        $this->assertSame('525512345678', (new Restaurante(['whatsapp_pedidos' => '+52 55 1234 5678']))->whatsappPedidosDigitos());
        $this->assertSame('12345678', (new Restaurante(['whatsapp_pedidos' => '12-34-56-78']))->whatsappPedidosDigitos());

        foreach ([null, '', 'abc', '1234567', '123456789012345678901'] as $malo) {
            $this->assertNull((new Restaurante(['whatsapp_pedidos' => $malo]))->whatsappPedidosDigitos(), (string) $malo);
        }
    }

    public function test_el_contacto_whatsapp_no_habilita_pedidos_pero_sus_botones_siguen(): void
    {
        $restaurante = $this->restaurante(['whatsapp_pedidos' => null]);
        Contacto::factory()->create(['restaurante_id' => $restaurante->id, 'tipo' => 'whatsapp', 'valor' => '+52 55 7777 8888']);
        $this->platillo($restaurante, 'Latte', ['precio' => 55]);

        $this->menu()->assertOk()
            ->assertDontSee('data-cal-pedido', false)->assertDontSee('data-cal-agregar', false)->assertDontSee('Pedir por WhatsApp')
            ->assertSee('https://wa.me/525577778888', false);
    }

    public function test_cerrado_muestra_un_aviso_en_lugar_del_boton(): void
    {
        $restaurante = $this->restaurante();
        $this->platillo($restaurante, 'Latte', ['precio' => 55]);
        $this->travelTo(self::CERRADO);

        $this->menu()->assertOk()
            ->assertSee('Estamos cerrados ahora', false)
            ->assertDontSee('data-cal-pedido', false)->assertDontSee('data-cal-agregar', false)->assertDontSee('Pedir por WhatsApp')
            ->assertSee('Latte');
    }

    public function test_cerrado_sin_pedidos_activos_no_dice_nada_del_pedido(): void
    {
        $restaurante = $this->restaurante(['pedidos_activos' => false]);
        $this->platillo($restaurante, 'Latte', ['precio' => 55]);
        $this->travelTo(self::CERRADO);

        $this->menu()->assertOk()->assertDontSee('Estamos cerrados ahora', false);
    }

    public function test_los_precios_y_nombres_salen_del_servidor_y_no_de_la_consulta(): void
    {
        $restaurante = $this->restaurante();
        $this->platillo($restaurante, 'Cold brew', ['precio' => 92, 'precio_oferta' => 78]);
        $chai = $this->platillo($restaurante, 'Chai', ['precio' => 50]);
        $this->variante($chai, 'Grande', 60);

        $respuesta = $this->menu('?precio=1&nombre=Gratis&total=0&mesa=4')->assertOk();

        $respuesta->assertSee('data-nombre="Cold brew"', false)->assertSee('data-precio="78.00"', false)
            ->assertSee('data-nombre="Chai"', false)->assertSee('data-nombre-variante="Grande"', false)->assertSee('data-precio="60.00"', false)
            ->assertDontSee('Gratis')->assertDontSee('data-precio="1"', false);
        $this->assertSame(0, preg_match('/data-precio="(?!\d+\.\d{2}")/', $respuesta->getContent()), 'Un precio no sale del servidor con dos decimales');
    }

    public function test_un_nombre_de_platillo_de_variante_o_de_restaurante_hostil_no_rompe_el_html(): void
    {
        $hostil = '</script><img src=x onerror=alert(1)>"\'&`${alert(1)}';
        $restaurante = $this->restaurante(['nombre' => 'Café '.$hostil]);
        $latte = $this->platillo($restaurante, 'Latte '.$hostil, ['precio' => 55]);
        $this->variante($latte, 'Grande '.$hostil, 60);

        $respuesta = $this->menu()->assertOk();
        $html = $respuesta->getContent();

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('</script><img', $html);
        $this->assertStringContainsString('data-restaurante="Café &lt;/script&gt;&lt;img src=x onerror=alert(1)&gt;&quot;&#039;&amp;`${alert(1)}"', $html);
        $this->assertStringContainsString('data-nombre-variante="Grande &lt;/script&gt;', $html);
        $this->assertSame(0, preg_match('/<script\b(?![^>]*\bsrc=)/i', $html), 'Hay un script en línea');

        $documento = new \DOMDocument;
        @$documento->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        foreach ((new \DOMXPath($documento))->query('//@*') as $atributo) {
            $this->assertStringStartsNotWith('on', $atributo->nodeName, 'Hay un manejador de eventos en línea');
        }
        $this->assertStringNotContainsString('innerHTML', $html);
    }

    public function test_un_platillo_agotado_no_se_puede_agregar(): void
    {
        $restaurante = $this->restaurante();
        $this->platillo($restaurante, 'Capuchino agotado', ['precio' => 54, 'disponible' => false]);
        $chai = $this->platillo($restaurante, 'Chai', ['precio' => 50]);
        $this->variante($chai, 'Grande', 60);
        $this->variante($chai, 'Jumbo', 80, disponible: false);
        $agotadoConVariante = $this->platillo($restaurante, 'Matcha agotado', ['precio' => 70, 'disponible' => false]);
        $this->variante($agotadoConVariante, 'Chico', 65);

        $respuesta = $this->menu()->assertOk();

        $respuesta->assertSee('Capuchino agotado')->assertDontSee('data-nombre="Capuchino agotado"', false)
            ->assertSee('data-nombre-variante="Grande"', false)->assertDontSee('data-nombre-variante="Jumbo"', false)
            ->assertSee('cal-variantes__agotado', false)
            ->assertDontSee('data-nombre="Matcha agotado"', false);
    }

    public function test_con_mostrar_agotados_desactivado_el_platillo_ni_aparece(): void
    {
        $restaurante = $this->restaurante(['mostrar_agotados' => false]);
        $this->platillo($restaurante, 'Capuchino agotado', ['precio' => 54, 'disponible' => false]);
        $this->platillo($restaurante, 'Latte', ['precio' => 55]);

        $this->menu()->assertOk()->assertDontSee('Capuchino agotado')->assertSee('data-nombre="Latte"', false);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function mesas(): array
    {
        return [
            'número' => ['3', '3'],
            'con espacios alrededor' => ['%203%20', '3'],
            'letras, guion y guion bajo' => ['Terraza_2-A', 'Terraza_2-A'],
            'veinte caracteres' => [str_repeat('a', 20), str_repeat('a', 20)],
            'veintiún caracteres' => [str_repeat('a', 21), ''],
            'con etiquetas' => ['%3Cb%3E3%3C%2Fb%3E', ''],
            'con comillas' => ['3%22%20onfocus%3D%22x', ''],
            'vacía' => ['', ''],
        ];
    }

    #[DataProvider('mesas')]
    public function test_la_mesa_valida_llega_al_script_y_la_invalida_se_ignora(string $consulta, string $esperada): void
    {
        $restaurante = $this->restaurante();
        $this->platillo($restaurante, 'Latte', ['precio' => 55]);

        $this->menu('?mesa='.$consulta)->assertOk()->assertSee('data-mesa="'.$esperada.'"', false);
    }

    public function test_una_mesa_en_forma_de_arreglo_se_ignora(): void
    {
        $restaurante = $this->restaurante();
        $this->platillo($restaurante, 'Latte', ['precio' => 55]);

        $this->menu('?mesa[]=3')->assertOk()->assertSee('data-mesa=""', false);
    }

    public function test_sin_javascript_el_menu_esta_completo_y_queda_el_enlace_a_whatsapp(): void
    {
        $restaurante = $this->restaurante();
        $this->platillo($restaurante, 'Latte', ['precio' => 55]);
        $this->platillo($restaurante, 'Chai', ['precio' => 50]);

        $respuesta = $this->menu()->assertOk();

        $respuesta->assertSee('Latte')->assertSee('Chai')->assertSee('$55.00')->assertSee('$50.00')
            ->assertSee('<a class="cal-boton cal-boton--arcilla" href="https://wa.me/525512345678" rel="noopener" data-cal-sin-js>Pedir por WhatsApp</a>', false);
        $this->assertSame(2, preg_match_all('/<button[^>]*\bhidden\b[^>]*data-cal-agregar|<button[^>]*data-cal-agregar[^>]*\bhidden\b/', $respuesta->getContent()));
    }

    public function test_el_cajon_avisa_que_el_total_es_informativo_y_usa_route_con_nombres(): void
    {
        $restaurante = $this->restaurante();
        $this->platillo($restaurante, 'Latte', ['precio' => 55]);

        $this->menu()->assertOk()
            ->assertSee('El total es informativo', false)
            ->assertSee('Enviar por WhatsApp')
            ->assertSee('Nota para la cocina', false)
            ->assertSee('data-clave="menuonl_cart_e_cafe-mezquite"', false)
            ->assertDontSee('Carta');
    }

    public function test_el_script_no_usa_innerhtml_ni_manejadores_ni_guarda_precios(): void
    {
        $principal = (string) file_get_contents(resource_path('js/sitio-cafeteria-pedido.js'));
        $mensaje = (string) file_get_contents(resource_path('js/sitio-cafeteria-pedido-mensaje.js'));

        foreach ([$principal, $mensaje] as $codigo) {
            $this->assertStringNotContainsString('innerHTML', $codigo);
            $this->assertStringNotContainsString('outerHTML', $codigo);
            $this->assertStringNotContainsString('insertAdjacentHTML', $codigo);
            $this->assertStringNotContainsString('document.write', $codigo);
            $this->assertStringNotContainsString('eval(', $codigo);
            $this->assertStringNotContainsString('onclick', $codigo);
            $this->assertStringContainsString('encodeURIComponent', $mensaje);
        }

        $this->assertStringContainsString('{ p: producto, v: variante || null, c: linea.cantidad, n: linea.nota }', $principal, 'localStorage debe guardar solo ids, cantidad y nota');
        $this->assertStringContainsString('menu.publico', $mensaje, 'Falta el aviso de mantener el formato igual al de menu.publico');
        $this->assertStringContainsString('menu.publico', $principal);
    }

    public function test_el_menu_de_e_respeta_la_condicion_de_horario_igual_que_menu_publico(): void
    {
        $restaurante = $this->restaurante();
        $this->platillo($restaurante, 'Latte', ['precio' => 55]);
        $restaurante->forceFill(['sitio_plantilla' => 'a'])->saveQuietly();
        $this->travelTo(self::CERRADO);

        $this->menu()->assertOk()->assertViewIs('menu.publico')->assertDontSee('id="floatingCartBar"', false);

        $this->travelTo(self::ABIERTO);
        $this->menu()->assertOk()->assertSee('id="floatingCartBar"', false);
    }

    public function test_las_otras_plantillas_no_cargan_nada_de_e(): void
    {
        foreach (['a', 'b', 'c', 'd'] as $clave) {
            $restaurante = $this->restaurante(['sitio_plantilla' => $clave, 'slug' => 'otro-'.$clave]);
            $this->platillo($restaurante, 'Latte', ['precio' => 55]);

            $menu = $this->get('/otro-'.$clave.'/menu')->assertOk()->assertViewIs('menu.publico');
            $sitio = $this->get('/otro-'.$clave)->assertOk()->assertViewIs('sitio.'.$clave);

            foreach ([$menu, $sitio] as $respuesta) {
                $respuesta->assertDontSee('data-cal-', false)->assertDontSee('cal-pedido', false)->assertDontSee('cal-reserva', false)->assertDontSee('sitio-e', false);
            }
            $menu->assertSee('cartAddItem', false)->assertSee('menuonl_cart_otro-'.$clave, false);
            $restaurante->delete();
        }
    }

    public function test_cafeteria_aparece_como_opcion_en_el_selector_de_plantilla_del_panel(): void
    {
        $restaurante = Restaurante::factory()->create(['slug' => 'panel-e']);
        $usuario = User::factory()->create();
        $restaurante->usuarios()->attach($usuario->id, ['rol' => 'gerente']);

        $respuesta = $this->actingAs($usuario)->getJson('/api/v1/panel/panel-e/ajustes')->assertOk();

        $this->assertContains('Cafetería', collect($respuesta->json('data.plantillas'))->where('clave', 'e')->pluck('nombre')->all());
        $this->assertStringContainsString('A.plantillas.map', (string) file_get_contents(resource_path('js/panel.js')));
    }
}
