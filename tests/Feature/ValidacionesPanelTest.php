<?php

namespace Tests\Feature;

use App\Filament\Resources\CodigosQr\Pages\CreateCodigoQr;
use App\Filament\Resources\Contactos\Pages\CreateContacto;
use App\Filament\Resources\Contactos\Pages\EditContacto;
use App\Filament\Resources\Horarios\Pages\CreateHorario;
use App\Filament\Resources\Productos\Pages\CreateProducto;
use App\Filament\Resources\Productos\Pages\ListProductos;
use App\Filament\Resources\Restaurantes\Pages\CreateRestaurante;
use App\Models\CodigoQr;
use App\Models\Contacto;
use App\Models\Producto;
use App\Models\Restaurante;
use App\Rules\CodigoQrValido;
use App\Rules\ValorContactoSeguro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

class ValidacionesPanelTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private Restaurante $a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
        $this->a = $this->crearRestaurante('a');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function valoresPeligrosos(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript mayusculas' => ['JavaScript:alert(1)'],
            'javascript con espacios' => ['  javascript:alert(1)'],
            'javascript con tabulador' => ["java\tscript:alert(1)"],
            'javascript con salto de linea' => ["java\nscript:alert(1)"],
            'data' => ['data:text/html;base64,PHNjcmlwdD4='],
            'vbscript' => ['vbscript:msgbox(1)'],
            'file' => ['file:///etc/passwd'],
            'ftp' => ['ftp://ejemplo.com/archivo'],
            'intent' => ['intent://scan/#Intent;end'],
        ];
    }

    #[DataProvider('valoresPeligrosos')]
    public function test_la_regla_rechaza_esquemas_peligrosos_en_cualquier_tipo(string $valor): void
    {
        foreach (['telefono', 'whatsapp', 'email', 'direccion', 'instagram', 'facebook', 'tiktok', 'web', 'otro'] as $tipo) {
            $this->assertTrue(
                Validator::make(['valor' => $valor], ['valor' => [new ValorContactoSeguro($tipo)]])->fails(),
                "{$tipo} aceptó {$valor}",
            );
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function valoresValidos(): array
    {
        return [
            'telefono plano' => ['telefono', '+52 55 1234 5678'],
            'telefono tel' => ['telefono', 'tel:+525512345678'],
            'whatsapp' => ['whatsapp', '5215512345678'],
            'correo' => ['email', 'hola@ejemplo.com'],
            'correo mailto' => ['email', 'mailto:hola@ejemplo.com'],
            'web https' => ['web', 'https://ejemplo.com/menu?x=1'],
            'web http' => ['web', 'http://ejemplo.com'],
            'instagram' => ['instagram', 'https://instagram.com/mi_restaurante'],
            'direccion' => ['direccion', 'Calle 5 de Mayo 12, Centro, CDMX'],
            'direccion con enlace de mapa' => ['direccion', 'https://maps.google.com/?q=19.43,-99.13'],
            'otro' => ['otro', 'Pregunta por Juan'],
        ];
    }

    #[DataProvider('valoresValidos')]
    public function test_la_regla_acepta_valores_legitimos(string $tipo, string $valor): void
    {
        $this->assertTrue(Validator::make(['valor' => $valor], ['valor' => [new ValorContactoSeguro($tipo)]])->passes(), "{$tipo}: {$valor}");
    }

    public function test_los_tipos_con_formato_propio_rechazan_valores_que_no_encajan(): void
    {
        $casos = [
            ['web', 'ejemplo.com'],
            ['web', 'mailto:a@b.com'],
            ['telefono', 'llamame ya'],
            ['telefono', 'mailto:a@b.com'],
            ['email', 'no-es-correo'],
            ['email', 'https://ejemplo.com'],
        ];

        foreach ($casos as [$tipo, $valor]) {
            $this->assertTrue(Validator::make(['valor' => $valor], ['valor' => [new ValorContactoSeguro($tipo)]])->fails(), "{$tipo}: {$valor}");
        }
    }

    #[DataProvider('valoresPeligrosos')]
    public function test_el_formulario_de_contactos_rechaza_javascript_y_similares_al_crear(string $valor): void
    {
        $this->entrarAlPanel($this->crearUsuario('editor', $this->a), $this->a);

        Livewire::test(CreateContacto::class)
            ->fillForm(['tipo' => 'web', 'valor' => $valor, 'orden' => 0])
            ->call('create')
            ->assertHasFormErrors(['valor']);

        $this->assertSame(0, Contacto::withoutGlobalScopes()->count());
    }

    public function test_el_formulario_de_contactos_rechaza_javascript_al_editar(): void
    {
        $contacto = Contacto::factory()->create(['restaurante_id' => $this->a->id, 'tipo' => 'web', 'valor' => 'https://ejemplo.com']);
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(EditContacto::class, ['record' => $contacto->getRouteKey()])
            ->fillForm(['valor' => 'javascript:alert(document.cookie)'])
            ->call('save')
            ->assertHasFormErrors(['valor']);

        $this->assertSame('https://ejemplo.com', $contacto->fresh()->valor);
    }

    public function test_el_formulario_de_contactos_acepta_un_enlace_https(): void
    {
        $this->entrarAlPanel($this->crearUsuario('editor', $this->a), $this->a);

        Livewire::test(CreateContacto::class)
            ->fillForm(['tipo' => 'web', 'valor' => 'https://ejemplo.com', 'orden' => 0])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('contactos', ['valor' => 'https://ejemplo.com', 'restaurante_id' => $this->a->id]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function codigosInvalidos(): array
    {
        return [
            'con espacio' => ['ab cd'],
            'ruta' => ['../admin'],
            'comilla' => ["a'b"],
            'etiqueta' => ['<script>'],
            'diecisiete caracteres' => [str_repeat('a', 17)],
            'salto de linea final' => ["abc\n"],
            'unicode' => ['códig0'],
            'barra' => ['a/b'],
            'punto y coma' => ['a;DROP'],
        ];
    }

    #[DataProvider('codigosInvalidos')]
    public function test_la_regla_de_codigo_qr_rechaza_caracteres_raros(string $codigo): void
    {
        $this->assertTrue(Validator::make(['codigo' => $codigo], ['codigo' => [new CodigoQrValido]])->fails());
    }

    public function test_la_regla_de_codigo_qr_acepta_los_caracteres_permitidos(): void
    {
        foreach (['abc', 'Mesa_5-A', 'A', str_repeat('z', 16), '0123456789abcdef'] as $codigo) {
            $this->assertTrue(Validator::make(['codigo' => $codigo], ['codigo' => [new CodigoQrValido]])->passes(), $codigo);
        }
    }

    #[DataProvider('codigosInvalidos')]
    public function test_el_formulario_de_qr_rechaza_codigos_con_caracteres_raros(string $codigo): void
    {
        $this->entrarAlPanel($this->crearUsuario('propietario', $this->a), $this->a);

        Livewire::test(CreateCodigoQr::class)
            ->fillForm(['nombre' => 'Mesa 1', 'codigo' => $codigo])
            ->call('create')
            ->assertHasFormErrors(['codigo']);

        $this->assertSame(0, CodigoQr::withoutGlobalScopes()->count());
    }

    public function test_el_codigo_de_qr_es_unico_entre_todos_los_restaurantes(): void
    {
        $b = $this->crearRestaurante('b');
        CodigoQr::factory()->create(['restaurante_id' => $b->id, 'codigo' => 'repetido']);
        $this->entrarAlPanel($this->crearUsuario('editor', $this->a), $this->a);

        Livewire::test(CreateCodigoQr::class)
            ->fillForm(['nombre' => 'Mesa 1', 'codigo' => 'repetido'])
            ->call('create')
            ->assertHasFormErrors(['codigo']);
    }

    public function test_el_formulario_de_qr_crea_el_codigo_para_el_restaurante_activo(): void
    {
        $this->entrarAlPanel($this->crearUsuario('editor', $this->a), $this->a);

        Livewire::test(CreateCodigoQr::class)
            ->fillForm(['nombre' => 'Terraza', 'codigo' => 'terraza-1'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('codigos_qr', ['codigo' => 'terraza-1', 'restaurante_id' => $this->a->id]);
    }

    public function test_los_precios_negativos_se_rechazan(): void
    {
        $categoria = $this->crearCategoria($this->a);
        $this->entrarAlPanel($this->crearUsuario('editor', $this->a), $this->a);

        Livewire::test(CreateProducto::class)
            ->fillForm([
                'categoria_id' => $categoria->id,
                'precio' => -5,
                'orden' => 0,
                'traducciones' => [['idioma' => 'es', 'nombre' => 'Gratis y de regalo']],
            ])
            ->call('create')
            ->assertHasFormErrors(['precio']);

        Livewire::test(CreateProducto::class)
            ->fillForm([
                'categoria_id' => $categoria->id,
                'precio' => 50,
                'precio_oferta' => -1,
                'orden' => 0,
                'traducciones' => [['idioma' => 'es', 'nombre' => 'Oferta rara']],
            ])
            ->call('create')
            ->assertHasFormErrors(['precio_oferta']);

        $this->assertSame(0, Producto::withoutGlobalScopes()->count());
    }

    public function test_el_precio_de_oferta_no_puede_superar_al_precio(): void
    {
        $categoria = $this->crearCategoria($this->a);
        $this->entrarAlPanel($this->crearUsuario('editor', $this->a), $this->a);

        Livewire::test(CreateProducto::class)
            ->fillForm([
                'categoria_id' => $categoria->id,
                'precio' => 50,
                'precio_oferta' => 80,
                'orden' => 0,
                'traducciones' => [['idioma' => 'es', 'nombre' => 'Mal precio']],
            ])
            ->call('create')
            ->assertHasFormErrors(['precio_oferta']);
    }

    public function test_el_precio_de_una_variante_no_puede_ser_negativo(): void
    {
        $categoria = $this->crearCategoria($this->a);
        $this->entrarAlPanel($this->crearUsuario('editor', $this->a), $this->a);

        Livewire::test(CreateProducto::class)
            ->fillForm([
                'categoria_id' => $categoria->id,
                'precio' => 50,
                'orden' => 0,
                'traducciones' => [['idioma' => 'es', 'nombre' => 'Con variante']],
                'variantes' => [['precio' => -10, 'orden' => 0, 'traducciones' => [['idioma' => 'es', 'nombre' => 'Grande']]]],
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertSame(0, Producto::withoutGlobalScopes()->count());
    }

    public function test_el_producto_exige_la_traduccion_del_idioma_principal(): void
    {
        $categoria = $this->crearCategoria($this->a);
        $this->entrarAlPanel($this->crearUsuario('editor', $this->a), $this->a);

        Livewire::test(CreateProducto::class)
            ->fillForm([
                'categoria_id' => $categoria->id,
                'precio' => 50,
                'orden' => 0,
                'traducciones' => [['idioma' => 'en', 'nombre' => 'Only English']],
            ])
            ->call('create')
            ->assertHasFormErrors();
    }

    public function test_un_idioma_no_activo_en_el_restaurante_se_rechaza(): void
    {
        $categoria = $this->crearCategoria($this->a);
        $this->entrarAlPanel($this->crearUsuario('editor', $this->a), $this->a);

        Livewire::test(CreateProducto::class)
            ->fillForm([
                'categoria_id' => $categoria->id,
                'precio' => 50,
                'orden' => 0,
                'traducciones' => [
                    ['idioma' => 'es', 'nombre' => 'Hola'],
                    ['idioma' => 'xx', 'nombre' => 'Idioma inventado'],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors();
    }

    public function test_el_dia_de_la_semana_fuera_de_rango_se_rechaza(): void
    {
        $this->entrarAlPanel($this->crearUsuario('editor', $this->a), $this->a);

        Livewire::test(CreateHorario::class)
            ->fillForm(['dia_semana' => 9, 'abre' => '09:00', 'cierra' => '18:00'])
            ->call('create')
            ->assertHasFormErrors(['dia_semana']);
    }

    public function test_ordenar_por_una_columna_fuera_de_la_lista_blanca_no_altera_la_tabla(): void
    {
        $producto = Producto::factory()->create(['restaurante_id' => $this->a->id]);
        $this->entrarAlPanel($this->crearUsuario('editor', $this->a), $this->a);

        Livewire::test(ListProductos::class)
            ->set('tableSort', 'restaurante_id; DROP TABLE productos;--')
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$producto]);

        $this->assertDatabaseHas('productos', ['id' => $producto->id]);
    }

    public function test_el_slug_de_un_restaurante_nuevo_solo_admite_minusculas_numeros_y_guiones(): void
    {
        $this->entrarAlPanel($this->crearUsuario('superadmin'), $this->a);

        foreach (['Mi Restaurante', 'a/b', 'a_b', '-a', 'a--b', "a'b"] as $slug) {
            Livewire::test(CreateRestaurante::class)
                ->fillForm([
                    'nombre' => 'Nuevo',
                    'slug' => $slug,
                    'moneda' => 'MXN',
                    'idioma_default' => 'es',
                    'zona_horaria' => 'America/Mexico_City',
                    'estado' => 'activo',
                    'email_propietario' => 'dueno@ejemplo.com',
                ])
                ->call('create')
                ->assertHasFormErrors(['slug']);
        }
    }
}
