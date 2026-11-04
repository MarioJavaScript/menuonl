<?php

namespace Tests\Feature;

use App\Enums\PlanRestaurante;
use App\Filament\Pages\AjustesDeReservasYPedidos;
use App\Filament\Pages\Apariencia;
use App\Filament\Pages\FotosDelRestaurante;
use App\Filament\Pages\Reservas;
use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\Reserva;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\LeeElPedidoDelMenuPublico;
use Tests\TestCase;

/**
 * Los dos planes de punta a punta: Lite (`solo_menu`) tiene panel y pedidos por WhatsApp para domicilio o para recoger,
 * pero no sitio ni reservas (ni en la web ni en la API ni en el panel); Aura (`sitio_menu`) tiene todo; y una fila que
 * aún diga `completo` (el plan retirado) se comporta como Aura en el sitio, en las reservas y en el panel.
 */
class PlanesAuraYLiteTest extends TestCase
{
    use LeeElPedidoDelMenuPublico, RefreshDatabase;

    /** Miércoles 2026-10-21, 10:00 en Ciudad de México. */
    private const AHORA = '2026-10-21 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse(self::AHORA, 'America/Mexico_City'));
        Cache::flush();
        RateLimiter::clear('reserva:min:127.0.0.1');
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function restaurante(PlanRestaurante|string $plan, array $atributos = []): Restaurante
    {
        $restaurante = Restaurante::factory()->create([
            'zona_horaria' => 'America/Mexico_City',
            'pedidos_activos' => true,
            'whatsapp_pedidos' => '+52 (55) 1234-5678',
            'reservas_activas' => true,
            'reservas_cupo' => 10,
            'reservas_franja_min' => 60,
            ...$atributos,
        ]);
        DB::table('restaurantes')->where('id', $restaurante->id)->update(['plan' => $plan instanceof PlanRestaurante ? $plan->value : $plan]);

        foreach (range(0, 6) as $dia) {
            Horario::factory()->create(['restaurante_id' => $restaurante->id, 'dia_semana' => $dia, 'abre' => '00:00:00', 'cierra' => '23:59:59', 'cerrado' => false]);
        }

        $categoria = Categoria::factory()->create(['restaurante_id' => $restaurante->id, 'visible' => true]);
        CategoriaTraduccion::firstOrCreate(['categoria_id' => $categoria->id, 'idioma' => 'es'], ['nombre' => 'Comida']);
        $producto = Producto::factory()->create(['restaurante_id' => $restaurante->id, 'categoria_id' => $categoria->id, 'visible' => true, 'disponible' => true, 'precio' => 120]);
        ProductoTraduccion::factory()->create(['producto_id' => $producto->id, 'idioma' => 'es', 'nombre' => 'Enchiladas']);

        return $restaurante->fresh();
    }

    private function propietario(Restaurante $restaurante): User
    {
        $usuario = User::factory()->create();
        $restaurante->usuarios()->attach($usuario, ['rol' => 'propietario']);

        return $usuario;
    }

    /**
     * @return array<string, mixed>
     */
    private function datosDeReserva(): array
    {
        return [
            'nombre' => 'Lucía Márquez',
            'telefono' => '+52 55 9999 0000',
            'personas' => 4,
            'fecha' => '2026-10-21',
            'hora' => '20:00',
            'nota' => null,
            'ts' => now()->timestamp - 10,
        ];
    }

    /**
     * @return list<string>
     */
    private function modalidadesOfrecidas(string $html): array
    {
        return array_map(
            fn (\DOMElement $opcion): string => $opcion->getAttribute('value'),
            $this->nodosDelMenu($html, '//*[@data-mo-entrega]//input[@type="radio" and @data-mo-modalidad]'),
        );
    }

    public function test_lite_no_tiene_sitio_ni_reservas_en_la_web_ni_en_la_api(): void
    {
        $lite = $this->restaurante(PlanRestaurante::SoloMenu);

        $this->get('/'.$lite->slug)->assertStatus(302)->assertRedirect(url('/'.$lite->slug.'/menu'));
        $this->get('/s/'.$lite->slug)->assertRedirect();
        $this->get('/'.$lite->slug.'/menu')->assertOk()->assertDontSee('id="form-reserva"', false);

        $this->assertSame([], $this->getJson('/api/v1/sitio/'.$lite->slug.'/disponibilidad?fecha=2026-10-21&personas=2')->assertOk()->json('data.franjas'));
        $this->postJson('/api/v1/sitio/'.$lite->slug.'/reservas', $this->datosDeReserva())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha' => 'Por ahora no recibimos reservas en línea.']);
        $this->assertSame(0, Reserva::withoutGlobalScopes()->count());
    }

    public function test_el_propietario_de_lite_entra_al_panel_sin_reservas_ni_fotos(): void
    {
        $lite = $this->restaurante(PlanRestaurante::SoloMenu);
        $propietario = $this->propietario($lite);

        $this->actingAs($propietario)->get('/admin')->assertRedirect(url('/admin/'.$lite->slug));
        $this->actingAs($propietario)->get('/admin/'.$lite->slug)->assertOk();
        $this->actingAs($propietario)->get(Apariencia::getUrl(tenant: $lite))->assertOk();
        $this->actingAs($propietario)->get(AjustesDeReservasYPedidos::getUrl(tenant: $lite))
            ->assertOk()
            ->assertSee(__('panel.pedidos.subtitulo_sin_reservas'))
            ->assertSee(__('panel.pedidos.whatsapp.domicilio'))
            ->assertSee(__('panel.pedidos.whatsapp.recoger'));
        $this->actingAs($propietario)->get(Reservas::getUrl(tenant: $lite))->assertNotFound();
        $this->actingAs($propietario)->get(FotosDelRestaurante::getUrl(tenant: $lite))->assertForbidden();
    }

    public function test_lite_recibe_pedidos_a_domicilio_y_para_recoger_sin_mesa(): void
    {
        $lite = $this->restaurante(PlanRestaurante::SoloMenu);

        $html = $this->get('/'.$lite->slug.'/menu?mesa=5')->assertOk()->getContent();

        $this->assertSame(['domicilio', 'recoger'], $this->modalidadesOfrecidas($html));
        $this->assertFalse($this->nodosDelMenu($html, '//*[@data-mo-pedido]')[0]->hasAttribute('data-mesa'));
        $this->assertStringContainsString('Pedidos para domicilio o para recoger.', $html);
    }

    public function test_lite_con_solo_domicilio_lo_ofrece_ya_marcado(): void
    {
        $lite = $this->restaurante(PlanRestaurante::SoloMenu, ['pedidos_recoger' => false]);

        $html = $this->get('/'.$lite->slug.'/menu')->assertOk()->getContent();

        $this->assertSame(['domicilio'], $this->modalidadesOfrecidas($html));
        $this->assertSame('checked', $this->nodosDelMenu($html, '//input[@data-mo-modalidad]')[0]->getAttribute('checked'));
        $this->assertStringContainsString('Pedidos para domicilio.', $html);
    }

    public function test_lite_con_solo_para_recoger_lo_ofrece_ya_marcado(): void
    {
        $lite = $this->restaurante(PlanRestaurante::SoloMenu, ['pedidos_domicilio' => false]);

        $html = $this->get('/'.$lite->slug.'/menu')->assertOk()->getContent();

        $this->assertSame(['recoger'], $this->modalidadesOfrecidas($html));
        $this->assertSame('checked', $this->nodosDelMenu($html, '//input[@data-mo-modalidad]')[0]->getAttribute('checked'));
        $this->assertStringContainsString('Pedidos para recoger.', $html);
    }

    public function test_sin_ninguna_modalidad_no_se_puede_pedir(): void
    {
        $lite = $this->restaurante(PlanRestaurante::SoloMenu, ['pedidos_domicilio' => false, 'pedidos_recoger' => false]);

        $this->assertFalse($lite->pedidosHabilitados());

        $html = $this->get('/'.$lite->slug.'/menu')->assertOk()->getContent();

        $this->assertSame([], $this->nodosDelMenu($html, '//*[@data-mo-pedido]'));
        $this->assertStringContainsString('Por ahora no recibimos pedidos aquí.', $html);
    }

    public function test_aura_tiene_sitio_reservas_y_pedidos(): void
    {
        $aura = $this->restaurante(PlanRestaurante::SitioMenu);
        $propietario = $this->propietario($aura);

        $this->get('/'.$aura->slug)->assertOk()->assertSee('id="form-reserva"', false);
        $this->postJson('/api/v1/sitio/'.$aura->slug.'/reservas', $this->datosDeReserva())->assertCreated();
        $this->assertSame(['domicilio', 'recoger'], $this->modalidadesOfrecidas($this->get('/'.$aura->slug.'/menu')->getContent()));
        $this->actingAs($propietario)->get(Reservas::getUrl(tenant: $aura))->assertOk();
        $this->actingAs($propietario)->get(FotosDelRestaurante::getUrl(tenant: $aura))->assertOk();
    }

    public function test_un_restaurante_que_aun_dice_completo_se_comporta_como_aura(): void
    {
        $viejo = $this->restaurante('completo');
        $propietario = $this->propietario($viejo);

        $this->assertSame('completo', DB::table('restaurantes')->where('id', $viejo->id)->value('plan'));
        $this->assertSame(PlanRestaurante::SitioMenu, $viejo->plan);
        $this->assertSame('Aura', $viejo->plan->getLabel());

        $this->get('/'.$viejo->slug)->assertOk()->assertSee('id="form-reserva"', false);
        $this->postJson('/api/v1/sitio/'.$viejo->slug.'/reservas', $this->datosDeReserva())->assertCreated();
        $this->assertSame(['domicilio', 'recoger'], $this->modalidadesOfrecidas($this->get('/'.$viejo->slug.'/menu')->getContent()));

        $this->actingAs($propietario)->get('/admin/'.$viejo->slug)->assertOk();
        $this->actingAs($propietario)->get(Reservas::getUrl(tenant: $viejo))->assertOk();
        $this->actingAs($propietario)->get(FotosDelRestaurante::getUrl(tenant: $viejo))->assertOk();
        $this->actingAs($propietario)->get(Apariencia::getUrl(tenant: $viejo))->assertOk();
    }
}
