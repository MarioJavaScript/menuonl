<?php

namespace Tests\Feature;

use App\Models\CodigoQr;
use App\Models\Restaurante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class QrScanBurstConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_rafaga_de_50_escaneos_secuenciales_en_codigos_qr_y_nfc_redirige_con_precision(): void
    {
        $restaurante = Restaurante::factory()->create([
            'slug' => 'rafaga-escaneos',
            'estado' => 'activo',
        ]);

        $qrMesa = CodigoQr::factory()->deMesa('8')->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'qrmesa08',
            'activo' => true,
        ]);

        $nfcMesa = CodigoQr::factory()->nfc()->deMesa('12')->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'nfcmes12',
            'activo' => true,
        ]);

        $destinoQr = route('menu.publico', ['slug' => 'rafaga-escaneos', 'mesa' => '8']);
        $destinoNfc = route('menu.publico', ['slug' => 'rafaga-escaneos', 'mesa' => '12']);

        for ($i = 0; $i < 25; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
                ->get(route('qr.redirect', ['codigo' => $qrMesa->codigo]))
                ->assertStatus(302)
                ->assertRedirect($destinoQr);

            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
                ->get(route('qr.redirect', ['codigo' => $nfcMesa->codigo]))
                ->assertStatus(302)
                ->assertRedirect($destinoNfc);
        }
    }

    public function test_rafaga_de_305_escaneos_activa_rate_limiting_429_a_partir_de_la_peticion_301(): void
    {
        RateLimiter::clear('menu-publico');

        $restaurante = Restaurante::factory()->create([
            'slug' => 'rate-limit-burst',
            'estado' => 'activo',
        ]);

        $nfc = CodigoQr::factory()->nfc()->deMesa('1')->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'nfcburst',
            'activo' => true,
        ]);

        $url = route('qr.redirect', ['codigo' => $nfc->codigo]);
        $ipCliente = '203.0.113.77';

        for ($i = 1; $i <= 300; $i++) {
            $respuesta = $this->withServerVariables(['REMOTE_ADDR' => $ipCliente])->get($url);
            $this->assertSame(302, $respuesta->getStatusCode(), "La petición #{$i} dentro del límite de 300/min debía devolver 302.");
        }

        for ($i = 301; $i <= 305; $i++) {
            $respuesta = $this->withServerVariables(['REMOTE_ADDR' => $ipCliente])->get($url);
            $this->assertSame(429, $respuesta->getStatusCode(), "La petición #{$i} por encima del límite de 300/min debía devolver 429.");
        }
    }

    public function test_redireccion_qr_y_nfc_es_resiliente_y_mantiene_headers_de_seguridad_ante_codigos_invalidos_en_rafaga(): void
    {
        $restaurante = Restaurante::factory()->create([
            'slug' => 'resiliencia-qr',
            'estado' => 'activo',
        ]);

        $qrValido = CodigoQr::factory()->deMesa('4')->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'qrvalid4',
            'activo' => true,
        ]);

        $codigosInvalidos = [
            'inexistente',
            '../../etc/passwd',
            '<script>alert(1)</script>',
            'codigo_demasiado_largo_123456789',
        ];

        foreach ($codigosInvalidos as $invalido) {
            $res = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.55'])
                ->get('/q/'.$invalido);

            $res->assertNotFound();
            $res->assertHeader('X-Content-Type-Options', 'nosniff');
            $res->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        }

        // Inmediatamente después de la ráfaga de códigos inválidos, el código válido redirige sin degradación
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.55'])
            ->get(route('qr.redirect', ['codigo' => $qrValido->codigo]))
            ->assertStatus(302)
            ->assertRedirect(route('menu.publico', ['slug' => 'resiliencia-qr', 'mesa' => '4']));
    }
}
