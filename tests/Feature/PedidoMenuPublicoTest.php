<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\VarianteTraduccion;
use App\Services\Catalogos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\LeeElPedidoDelMenuPublico;
use Tests\TestCase;

/**
 * Pedido por WhatsApp de menu.publico (plantillas a-d y menú por defecto): qué entrega el servidor al módulo compartido
 * (atributos data-mo-*, sin script en línea) y en qué estados se dibuja. La lógica del navegador (mensaje, límites, migración del
 * carrito, cajón) se prueba en tests/Js/menu-publico-pedido.test.mjs.
 */
class PedidoMenuPublicoTest extends TestCase
{
    use LeeElPedidoDelMenuPublico;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function restaurante(array $atributos = [], bool $abierto = true): Restaurante
    {
        $restaurante = Restaurante::factory()->create($atributos + [
            'estado' => 'activo',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '+52 (55) 1234-5678',
        ]);

        if ($abierto) {
            foreach (range(0, 6) as $dia) {
                Horario::factory()->create(['restaurante_id' => $restaurante->id, 'dia_semana' => $dia, 'abre' => '00:00:00', 'cierra' => '23:59:59', 'cerrado' => false]);
            }
        }

        return $restaurante;
    }

    private function platillo(Restaurante $restaurante, string $nombre, float $precio = 50, bool $disponible = true, ?string $variante = null): Producto
    {
        $categoria = Categoria::query()->where('restaurante_id', $restaurante->id)->first()
            ?? Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::query()->firstOrCreate(['categoria_id' => $categoria->id, 'idioma' => 'es'], ['nombre' => 'Entradas']);

        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id, 'precio' => $precio, 'precio_oferta' => null, 'disponible' => $disponible, 'visible' => true]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => $nombre]);

        if ($variante !== null) {
            $opcion = ProductoVariante::factory()->create(['producto_id' => $producto->id, 'precio' => $precio + 10, 'disponible' => true]);
            VarianteTraduccion::factory()->create(['variante_id' => $opcion->id, 'idioma' => 'es', 'nombre' => $variante]);
        }

        return $producto;
    }

    public function test_abierto_con_pedidos_y_numero_valido_dibuja_el_pedido_con_datos_del_servidor(): void
    {
        $restaurante = $this->restaurante(['nombre' => 'Café Mezquite', 'moneda' => 'mxn']);
        $simple = $this->platillo($restaurante, 'Latte', 55);
        $conVariante = $this->platillo($restaurante, 'Chai', 40, true, 'Grande');

        $respuesta = $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk();
        $html = $respuesta->getContent();

        $atributos = $this->atributosDelContenedorDePedido($html);
        $this->assertSame('525512345678', $atributos['numero'], 'El número llega solo con dígitos, ya validado.');
        $this->assertSame('Café Mezquite', $atributos['restaurante']);
        $this->assertSame('MXN', $atributos['moneda']);
        $this->assertSame('es-MX', $atributos['locale']);
        $this->assertSame('menuonl_cart_m_'.$restaurante->slug, $atributos['clave']);

        $botones = $this->botonesDeAgregar($html);
        $this->assertCount(2, $botones);
        $porProducto = array_column($botones, null, 'producto');
        $this->assertSame('', $porProducto[(string) $simple->id]['variante']);
        $this->assertSame('55.00', $porProducto[(string) $simple->id]['precio']);
        $this->assertSame('Grande', $porProducto[(string) $conVariante->id]['nombre-variante']);
        $this->assertSame('50.00', $porProducto[(string) $conVariante->id]['precio']);

        $respuesta->assertSee('id="floatingCartBar"', false)->assertSee('id="cartModalBackdrop"', false);
        $this->assertSinScriptNiManejadoresDelPedido($html);
        $this->assertStringNotContainsString('innerHTML', $html);
        $this->assertStringNotContainsString('Carta', strip_tags(explode('<main', $html)[1] ?? ''), '"Menú", nunca "Carta".');

        if (file_exists(public_path('build/manifest.json'))) {
            $this->assertMatchesRegularExpression('#<script type="module" src="[^"]*menu-pedido[^"]*\.js"#', $html);
        }
    }

    public function test_el_modulo_del_pedido_solo_se_pide_cuando_se_puede_pedir(): void
    {
        $restaurante = $this->restaurante(abierto: false);
        $this->platillo($restaurante, 'Latte');

        $html = $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('#src="[^"]*menu-pedido[^"]*"#', $html);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: bool, 2: string}>
     */
    public static function estadosSinPedido(): array
    {
        return [
            'cerrado' => [[], false, 'Estamos cerrados.'],
            'pedidos desactivados' => [['pedidos_activos' => false], true, 'Para ordenar, pídele a tu mesero.'],
            'sin número' => [['whatsapp_pedidos' => null], true, 'Por ahora no recibimos pedidos aquí.'],
            'número vacío' => [['whatsapp_pedidos' => '   '], true, 'Por ahora no recibimos pedidos aquí.'],
            'número no vacío pero inválido' => [['whatsapp_pedidos' => '123'], true, 'Por ahora no recibimos pedidos aquí.'],
            'número sin dígitos' => [['whatsapp_pedidos' => 'llámanos'], true, 'Por ahora no recibimos pedidos aquí.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    #[DataProvider('estadosSinPedido')]
    public function test_sin_condiciones_para_pedir_no_hay_carrito_ni_botones_y_se_explica_por_que(array $atributos, bool $abierto, string $aviso): void
    {
        $restaurante = $this->restaurante($atributos, $abierto);
        $this->platillo($restaurante, 'Latte');

        $respuesta = $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk();
        $html = $respuesta->getContent();

        $this->assertSame($abierto, $respuesta->viewData('estaAbierto'));
        $respuesta->assertDontSee('data-mo-pedido', false)
            ->assertDontSee('id="floatingCartBar"', false)
            ->assertDontSee('id="cartModalBackdrop"', false)
            ->assertSee($aviso, false);
        $this->assertSame([], $this->botonesDeAgregar($html));
        $this->assertSinScriptNiManejadoresDelPedido($html);
    }

    public function test_un_platillo_o_variante_agotado_no_tiene_boton_de_agregar(): void
    {
        $restaurante = $this->restaurante(['mostrar_agotados' => true]);
        $this->platillo($restaurante, 'Latte', 55);
        $this->platillo($restaurante, 'Pan agotado', 20, false);

        $respuesta = $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk();
        $html = $respuesta->getContent();

        $this->assertSame(['Latte'], array_column($this->botonesDeAgregar($html), 'nombre'));
        $respuesta->assertSee('Agotado');
    }

    public function test_mesa_de_la_url_llega_filtrada_y_vacia_cuando_no_hay_o_es_hostil(): void
    {
        $restaurante = $this->restaurante();
        $this->platillo($restaurante, 'Latte');
        $ruta = route('menu.publico', ['slug' => $restaurante->slug]);

        $conMesa = $this->get($ruta.'?mesa=Terraza%202')->assertOk();
        $this->assertSame('Terraza 2', $this->atributosDelContenedorDePedido($conMesa->getContent())['mesa']);
        $conMesa->assertSee('value="Terraza 2"', false)->assertSee('Mesa Terraza 2');

        foreach (['', '<script>alert(1)</script>', str_repeat('a', 21), 'Barra/2'] as $hostil) {
            $html = $this->get($ruta.'?mesa='.rawurlencode($hostil))->assertOk()->getContent();

            $this->assertSame('', $this->atributosDelContenedorDePedido($html)['mesa'], "Mesa '$hostil' no debe llegar al módulo.");
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        }

        $this->assertSame('', $this->atributosDelContenedorDePedido($this->get($ruta)->getContent())['mesa'], 'Sin ?mesa= el módulo usa "Para llevar".');
    }

    public function test_los_cuatro_estilos_renderizan_el_mismo_marcado_base_del_pedido(): void
    {
        $this->assertEqualsCanonicalizing(['calido', 'editorial', 'rustico', 'clasico'], array_keys(Catalogos::temas()));

        $marcado = [];
        foreach (array_keys(Catalogos::temas()) as $tema) {
            $restaurante = $this->restaurante(['tema' => $tema, 'slug' => 'estilo-'.$tema, 'nombre' => 'Mismo Nombre', 'moneda' => 'MXN']);
            $this->platillo($restaurante, 'Latte', 55, true, 'Grande');
            $html = $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk()->assertSee('data-tema="'.$tema.'"', false)->getContent();

            $contenedor = $this->nodosDelMenu($html, '//*[@data-mo-pedido]')[0];
            // El slug (y la clave que lo lleva) es lo único que distingue a los restaurantes de la prueba.
            $marcado[$tema] = str_replace('estilo-'.$tema, 'estilo-X', (string) $contenedor->ownerDocument->saveHTML($contenedor));
        }

        $this->assertNotEmpty($marcado['calido']);
        foreach ($marcado as $tema => $html) {
            $this->assertSame($marcado['calido'], $html, "El pedido de '$tema' no tiene el mismo marcado que 'calido'.");
        }
    }

    public function test_el_html_trae_todos_los_ganchos_que_usa_el_controlador(): void
    {
        $restaurante = $this->restaurante();
        $this->platillo($restaurante, 'Latte', 55, true, 'Grande');
        $html = $this->get(route('menu.publico', ['slug' => $restaurante->slug]))->assertOk()->getContent();

        $codigo = (string) file_get_contents(resource_path('js/pedido/controlador.js'));
        preg_match_all("/(?:selector|atributo)\('([a-z-]+)'\)/", $codigo, $usados);
        $ganchos = array_unique(array_merge($usados[1], ['pedido']));

        // 'sin-js' es del fallback sin JavaScript de E-H; 'toast' lo reemplaza la presentación del cajón (#cartToast).
        foreach (array_diff($ganchos, ['sin-js', 'toast']) as $gancho) {
            $this->assertStringContainsString('data-mo-'.$gancho, $html, "Falta data-mo-$gancho en menu.publico.");
        }

        foreach (['cartToast', 'cartToastMessage', 'btnCloseCartModal'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html);
        }
    }
}
