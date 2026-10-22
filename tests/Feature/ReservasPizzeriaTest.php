<?php

namespace Tests\Feature;

use App\Models\Contacto;
use App\Models\Horario;
use App\Models\Reserva;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Reservas públicas en el sitio de la plantilla H. El servidor y sitio.js son los mismos que usan A a D; aquí se prueba que E dibuja el
 * formulario con el mismo contrato (ids, campos, data-*), que lo oculta sin reservas activas y que las reglas del servidor aplican igual.
 */
class ReservasPizzeriaTest extends TestCase
{
    use RefreshDatabase;

    /** Miércoles 2026-10-21, 10:00 en Ciudad de México. */
    private const AHORA = '2026-10-21 10:00:00';

    private Restaurante $restaurante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse(self::AHORA, 'America/Mexico_City'));
        Cache::flush();
        RateLimiter::clear('reserva:min:127.0.0.1');
        $this->restaurante = Restaurante::factory()->create([
            'slug' => 'ocaso', 'nombre' => 'Ocaso', 'sitio_plantilla' => 'h', 'zona_horaria' => 'America/Mexico_City',
            'reservas_activas' => true, 'reservas_cupo' => 10, 'reservas_franja_min' => 60, 'whatsapp_pedidos' => '+52 55 1234 5678',
        ]);
        Horario::factory()->create(['restaurante_id' => $this->restaurante->id, 'dia_semana' => 3, 'abre' => '13:00:00', 'cierra' => '23:00:00', 'cerrado' => false]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private bool $conLimite = false;

    private function enviar(array $extra = []): TestResponse
    {
        if (! $this->conLimite) {
            Cache::flush();
        }

        return $this->postJson('/api/v1/sitio/ocaso/reservas', $this->datos($extra));
    }

    private function datos(array $extra = []): array
    {
        return ['nombre' => 'Lucía Márquez', 'telefono' => '+52 55 9999 0000', 'personas' => 4, 'fecha' => '2026-10-21', 'hora' => '20:00', 'nota' => null, 'ts' => now()->timestamp - 10, ...$extra];
    }

    public function test_el_sitio_de_h_dibuja_el_formulario_con_el_mismo_contrato_que_las_otras_plantillas(): void
    {
        $this->get('/ocaso')->assertOk()->assertViewIs('sitio.h')
            ->assertSee('id="reservar"', false)->assertSee('id="form-reserva"', false)
            ->assertSee('data-url="'.url('/api/v1/sitio/ocaso/reservas').'"', false)
            ->assertSee('data-disp="'.url('/api/v1/sitio/ocaso/disponibilidad').'"', false)
            ->assertSee('name="nombre"', false)->assertSee('name="telefono"', false)->assertSee('name="fecha"', false)
            ->assertSee('name="personas"', false)->assertSee('name="hora"', false)->assertSee('name="nota"', false)
            ->assertSee('name="sitio_web"', false)->assertSee('name="ts"', false)->assertSee('id="reserva-msg"', false)
            ->assertSee('Activa JavaScript para reservar en línea', false)
            ->assertSee('Solicitar reserva');
    }

    public function test_el_formulario_de_e_tiene_los_mismos_campos_que_el_de_las_otras_plantillas(): void
    {
        $campos = function (string $vista): array {
            $html = (string) file_get_contents(resource_path($vista));
            preg_match_all('/\b(?:name|id)="((?:rs-|reserva-|form-reserva|sitio_web|nombre|telefono|fecha|personas|hora|nota|ts)[^"]*)"/', $html, $coincidencias);

            return collect($coincidencias[1])->unique()->sort()->values()->all();
        };

        $this->assertSame($campos('views/sitio/partes/reservar.blade.php'), $campos('views/components/sitio/pizzeria/reservar.blade.php'));
    }

    public function test_la_cabecera_tiene_el_acceso_reservar_en_el_sitio_y_en_el_menu(): void
    {
        $inicio = route('sitio.publico', ['slug' => 'ocaso']);

        $this->get('/ocaso')->assertOk()->assertSee('href="#reservar">Reservar</a>', false);
        $this->get('/ocaso/menu')->assertOk()->assertViewIs('menu.plantillas.h')->assertSee('href="'.$inicio.'#reservar">Reservar</a>', false);
    }

    public function test_sin_reservas_activas_la_seccion_el_acceso_y_el_formulario_no_aparecen(): void
    {
        $this->restaurante->forceFill(['reservas_activas' => false])->saveQuietly();

        foreach (['/ocaso', '/ocaso/menu'] as $ruta) {
            $this->get($ruta)->assertOk()
                ->assertDontSee('id="reservar"', false)->assertDontSee('id="form-reserva"', false)->assertDontSee('#reservar', false)
                ->assertDontSee('name="sitio_web"', false)->assertDontSee('Solicitar reserva')->assertDontSee('>Reservar<', false);
        }
    }

    public function test_el_formulario_ofrece_whatsapp_y_llamada_solo_si_hay_contacto(): void
    {
        Contacto::factory()->create(['restaurante_id' => $this->restaurante->id, 'tipo' => 'telefono', 'valor' => '55 1111 2222']);

        $this->get('/ocaso')->assertOk()
            ->assertSee('https://wa.me/525512345678?text='.rawurlencode('Hola, quiero reservar en Ocaso.'), false)
            ->assertSee('tel:5511112222', false);

        $this->restaurante->forceFill(['whatsapp_pedidos' => null])->saveQuietly();
        Contacto::where('restaurante_id', $this->restaurante->id)->delete();
        $this->get('/ocaso')->assertOk()->assertDontSee('¿Prefieres otro medio?', false)->assertDontSee('wa.me');
    }

    public function test_el_nombre_hostil_no_rompe_el_formulario(): void
    {
        $this->restaurante->forceFill(['nombre' => 'Café "><script>alert(1)</script>'])->saveQuietly();

        $this->get('/ocaso')->assertOk()->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee(rawurlencode('Hola, quiero reservar en Café "><script>alert(1)</script>.'), false);
    }

    public function test_alta_correcta_desde_el_sitio_de_e(): void
    {
        $this->enviar(['nota' => '<b>Aniversario</b>'])->assertCreated()
            ->assertJsonPath('data.hora', '20:00')->assertJsonPath('data.personas', 4)
            ->assertJsonStructure(['data' => ['mensaje', 'whatsapp_url']]);

        $this->assertDatabaseHas('reservas', ['restaurante_id' => $this->restaurante->id, 'nombre' => 'Lucía Márquez', 'estado' => 'pendiente', 'origen' => 'sitio', 'nota' => 'Aniversario']);
    }

    public function test_validacion_del_servidor_en_e(): void
    {
        $this->enviar(['nombre' => 'A<b>', 'telefono' => 'abc', 'personas' => 99, 'hora' => '25:00'])
            ->assertUnprocessable()->assertJsonValidationErrors(['nombre', 'telefono', 'personas', 'hora']);
        $this->enviar(['fecha' => '2026-10-20'])->assertUnprocessable()->assertJsonValidationErrors('fecha');
        $this->enviar(['hora' => '03:00'])->assertUnprocessable()->assertJsonValidationErrors('hora');
        $this->enviar(['ts' => now()->timestamp])->assertUnprocessable();
        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_el_campo_trampa_parece_exito_pero_no_guarda(): void
    {
        $this->enviar(['sitio_web' => 'http://spam.example'])->assertCreated();

        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_limite_de_tasa_de_reservas(): void
    {
        $this->conLimite = true;

        foreach (['Uno', 'Dos', 'Tres'] as $nombre) {
            $this->enviar(['nombre' => 'Persona '.$nombre, 'hora' => '13:00', 'personas' => 1])->assertCreated();
        }

        $this->enviar(['hora' => '14:00'])->assertStatus(429);
        $this->assertSame(3, Reserva::count());
    }

    public function test_sin_reservas_activas_el_servidor_rechaza_aunque_se_forje_la_peticion(): void
    {
        $this->restaurante->forceFill(['reservas_activas' => false])->saveQuietly();

        $this->enviar()->assertUnprocessable();
        $this->assertDatabaseCount('reservas', 0);
    }
}
