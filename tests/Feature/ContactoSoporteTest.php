<?php

namespace Tests\Feature;

use App\Services\ContactoSoporte;
use Tests\TestCase;

class ContactoSoporteTest extends TestCase
{
    public function test_sin_numero_configurado_no_hay_enlace_de_whatsapp(): void
    {
        foreach ([null, '', '   '] as $vacio) {
            config(['menuonl.soporte_whatsapp' => $vacio]);

            $this->assertNull((new ContactoSoporte)->enlaceWhatsapp());
        }
    }

    public function test_con_numero_configurado_el_enlace_abre_wa_me_con_el_texto_de_ayuda(): void
    {
        config(['menuonl.soporte_whatsapp' => '5215512345678']);

        $this->assertSame(
            'https://wa.me/5215512345678?text='.rawurlencode('Hola, quiero cambiar algo de mi menú'),
            (new ContactoSoporte)->enlaceWhatsapp(),
        );
    }

    public function test_el_mensaje_se_codifica_y_el_numero_debe_tener_entre_10_y_15_digitos(): void
    {
        config(['menuonl.soporte_whatsapp' => '5210000000000']);
        $this->assertSame('https://wa.me/5210000000000?text='.rawurlencode('Hola & ¿qué tal? 100%'), (new ContactoSoporte)->enlaceWhatsapp('Hola & ¿qué tal? 100%'));

        foreach (['123456789', '1234567890123456', 'sin-numero'] as $invalido) {
            config(['menuonl.soporte_whatsapp' => $invalido]);
            $this->assertNull((new ContactoSoporte)->numero(), $invalido);
            $this->assertNull((new ContactoSoporte)->enlaceWhatsapp('Hola'), $invalido);
        }

        config(['menuonl.soporte_whatsapp' => '+52 (10) 0000-0000']);
        $this->assertSame('521000000000', (new ContactoSoporte)->numero());
    }

    public function test_el_numero_viene_de_la_variable_soporte_whatsapp_y_el_env_de_ejemplo_la_trae_vacia(): void
    {
        $this->assertStringContainsString(
            "'soporte_whatsapp' => env('SOPORTE_WHATSAPP')",
            file_get_contents(base_path('config/menuonl.php')),
        );
        $this->assertMatchesRegularExpression('/^SOPORTE_WHATSAPP=
?$/m', file_get_contents(base_path('.env.example')));
    }
}
