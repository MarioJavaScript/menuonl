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

    public function test_el_numero_viene_de_la_variable_soporte_whatsapp_y_el_env_de_ejemplo_la_trae_vacia(): void
    {
        $this->assertStringContainsString(
            "'soporte_whatsapp' => env('SOPORTE_WHATSAPP')",
            file_get_contents(base_path('config/menuonl.php')),
        );
        $this->assertMatchesRegularExpression('/^SOPORTE_WHATSAPP=?$/m', file_get_contents(base_path('.env.example')));
    }
}
