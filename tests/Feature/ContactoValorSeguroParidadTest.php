<?php

namespace Tests\Feature;

use App\Filament\Resources\Contactos\Pages\CreateContacto;
use App\Rules\ValorContactoSeguro;
use App\Services\Catalogos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Fija que la regla de contactos de /admin (ValorContactoSeguro) rechaza todo lo que rechazaba el closure `contactoSeguro`
 * del /panel retirado (caracteres de control y protocolos fuera de http, https, tel y mailto), para cualquier tipo de contacto,
 * y que además rechaza lo peligroso que ese closure dejaba pasar (control en los bordes, enlaces con comillas o espacios).
 */
class ContactoValorSeguroParidadTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    /**
     * La regla del /panel retirado, tal cual estaba en AjustesController::update: true cuando el valor se rechazaba.
     */
    private function loRechazabaElPanelViejo(string $valor): bool
    {
        $limpio = trim($valor);

        if ($limpio === '') {
            return false;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $limpio)) {
            return true;
        }

        if (preg_match('/\A([A-Za-z][A-Za-z0-9+.\-]*):/', $limpio, $coincidencias)) {
            return ! in_array(strtolower($coincidencias[1]), ['http', 'https', 'tel', 'mailto'], true);
        }

        return false;
    }

    private function pasa(string $tipo, string $valor): bool
    {
        return Validator::make(['valor' => $valor], ['valor' => [new ValorContactoSeguro($tipo)]])->passes();
    }

    /**
     * @return list<string>
     */
    private static function tipos(): array
    {
        return array_keys(Catalogos::tiposContacto());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function valoresPeligrosos(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript en mayúsculas' => ['JaVaScRiPt:alert(1)'],
            'javascript con espacios al inicio' => ['   javascript:alert(1)'],
            'data html' => ['data:text/html,<script>alert(1)</script>'],
            'data base64' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
            'vbscript' => ['vbscript:msgbox(1)'],
            'file' => ['file:///etc/passwd'],
            'ftp' => ['ftp://servidor.example/archivo'],
            'blob' => ['blob:https://a.example/uuid'],
            'tabulador dentro del esquema' => ["java\tscript:alert(1)"],
            'salto de línea dentro del esquema' => ["java\nscript:alert(1)"],
            'retorno de carro' => ["https://a.example\r\nSet-Cookie: x=1"],
            'byte nulo' => ["https://a.example\0.evil"],
            'control DEL' => ["https://a.example/\x7F"],
            'control al inicio' => ["\x01https://a.example"],
            'control al final' => ["https://a.example\x1F"],
        ];
    }

    #[DataProvider('valoresPeligrosos')]
    public function test_la_regla_rechaza_lo_peligroso_en_todos_los_tipos(string $valor): void
    {
        foreach (self::tipos() as $tipo) {
            $this->assertFalse($this->pasa($tipo, $valor), "El tipo {$tipo} aceptó ".json_encode($valor));
        }
    }

    #[DataProvider('valoresPeligrosos')]
    public function test_lo_que_rechazaba_el_panel_viejo_lo_rechaza_tambien_admin(string $valor): void
    {
        if (! $this->loRechazabaElPanelViejo($valor)) {
            $this->markTestSkipped('El panel viejo no rechazaba este valor; lo cubre test_la_regla_rechaza_lo_peligroso_en_todos_los_tipos.');
        }

        foreach (self::tipos() as $tipo) {
            $this->assertFalse($this->pasa($tipo, $valor), "El tipo {$tipo} aceptó ".json_encode($valor));
        }
    }

    public function test_el_panel_viejo_dejaba_pasar_control_en_los_bordes_y_admin_no(): void
    {
        // trim() quitaba el control antes de mirarlo: aquí /admin es más estricto.
        foreach (["https://a.example\t", "\nhttps://a.example"] as $valor) {
            $this->assertFalse($this->loRechazabaElPanelViejo($valor));
            $this->assertFalse($this->pasa('web', $valor));
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function enlacesDeMalaForma(): array
    {
        return [
            'comillas que cierran el atributo' => ['web', 'https://a.example/" onmouseover="alert(1)'],
            'comilla simple' => ['instagram', "https://a.example/' onfocus='alert(1)"],
            'espacio dentro de la URL' => ['facebook', 'https://a.example/un enlace'],
            'sin dominio' => ['web', 'https:///ruta'],
            'sin esquema en una red social' => ['instagram', 'instagram.com/local'],
            'teléfono con letras' => ['telefono', 'tel:555-ABCD'],
            'teléfono con esquema ajeno' => ['whatsapp', 'https://wa.me/5215512345678'],
            'correo con esquema ajeno' => ['email', 'https://a.example'],
            'correo sin arroba' => ['email', 'mailto:nadie'],
        ];
    }

    #[DataProvider('enlacesDeMalaForma')]
    public function test_la_regla_rechaza_enlaces_mal_formados_por_tipo(string $tipo, string $valor): void
    {
        $this->assertFalse($this->pasa($tipo, $valor), "El tipo {$tipo} aceptó ".json_encode($valor));
    }

    public function test_acepta_los_valores_legitimos_de_cada_tipo(): void
    {
        $validos = [
            ['telefono', '+52 55 1234 5678'],
            ['telefono', 'tel:+525512345678'],
            ['whatsapp', '5215512345678'],
            ['email', 'hola@local.example'],
            ['email', 'mailto:hola@local.example'],
            ['instagram', 'https://instagram.com/mi_local'],
            ['facebook', 'https://facebook.com/mi.local'],
            ['web', 'http://mi-local.example'],
            ['direccion', 'Calle 5 #12, Col. Centro'],
        ];

        foreach ($validos as [$tipo, $valor]) {
            $this->assertTrue($this->pasa($tipo, $valor), "El tipo {$tipo} rechazó {$valor}");
        }
    }

    public function test_el_formulario_de_admin_limita_el_valor_a_255_caracteres(): void
    {
        $restaurante = $this->crearRestaurante('local-contactos');
        $this->entrarAlPanel($this->crearUsuario('propietario', $restaurante), $restaurante);
        $largo = 'https://a.example/'.str_repeat('a', 240);

        Livewire::test(CreateContacto::class)
            ->fillForm(['tipo' => 'web', 'valor' => $largo, 'orden' => 0, 'visible' => true])
            ->call('create')
            ->assertHasFormErrors(['valor']);

        $this->assertDatabaseMissing('contactos', ['valor' => $largo]);
    }
}
