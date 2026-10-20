<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelApiPedidosTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    private function miembro(Restaurante $restaurante, string $rol = 'editor'): User
    {
        $usuario = User::factory()->create();
        $restaurante->usuarios()->attach($usuario, ['rol' => $rol]);

        return $usuario;
    }

    private function url(Restaurante $restaurante, string $ruta = ''): string
    {
        return "/api/v1/panel/{$restaurante->slug}/pedidos".$ruta;
    }

    public function test_guest_gets_401(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->getJson($this->url($restaurante))->assertUnauthorized();
        $this->postJson($this->url($restaurante), [])->assertUnauthorized();
    }

    public function test_lists_active_and_todays_delivered_but_not_old_or_foreign_orders(): void
    {
        $restaurante = Restaurante::factory()->create();
        $otro = Restaurante::factory()->create();
        Pedido::factory()->create(['restaurante_id' => $restaurante->id, 'mesa' => 'activo']);
        Pedido::factory()->enEstado('entregado')->create(['restaurante_id' => $restaurante->id, 'mesa' => 'hoy']);
        Pedido::factory()->enEstado('entregado')->create(['restaurante_id' => $restaurante->id, 'mesa' => 'ayer', 'entregado_at' => now()->subDays(2)]);
        Pedido::factory()->create(['restaurante_id' => $otro->id, 'mesa' => 'ajeno']);

        $mesas = collect($this->actingAs($this->miembro($restaurante))->getJson($this->url($restaurante))->assertOk()->json('data'))->pluck('mesa')->sort()->values()->all();

        $this->assertSame(['activo', 'hoy'], $mesas);
    }

    public function test_creates_order_and_sends_it_to_kitchen(): void
    {
        $restaurante = Restaurante::factory()->create();

        $this->actingAs($this->miembro($restaurante))
            ->postJson($this->url($restaurante), ['mesa' => '4', 'detalle' => '2x Risotto', 'total' => 560.5, 'estado' => 'cocina'])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'cocina')
            ->assertJsonPath('data.total', 560.5);

        $this->assertDatabaseHas('pedidos', ['restaurante_id' => $restaurante->id, 'mesa' => '4']);
    }

    public function test_validation_rejects_bad_totals_and_states(): void
    {
        $restaurante = Restaurante::factory()->create();
        $usuario = $this->miembro($restaurante);

        $this->actingAs($usuario)->postJson($this->url($restaurante), [])->assertJsonValidationErrors(['mesa', 'detalle', 'total']);
        $this->actingAs($usuario)->postJson($this->url($restaurante), ['mesa' => '1', 'detalle' => 'x', 'total' => -1])->assertJsonValidationErrors('total');
        $this->actingAs($usuario)->postJson($this->url($restaurante), ['mesa' => '1', 'detalle' => 'x', 'total' => 100001])->assertJsonValidationErrors('total');
        $this->actingAs($usuario)->postJson($this->url($restaurante), ['mesa' => '1', 'detalle' => 'x', 'total' => 10, 'estado' => 'entregado'])->assertJsonValidationErrors('estado');
    }

    public function test_advances_through_the_whole_flow_and_stamps_delivery(): void
    {
        $restaurante = Restaurante::factory()->create();
        $pedido = Pedido::factory()->create(['restaurante_id' => $restaurante->id]);
        $usuario = $this->miembro($restaurante);

        foreach (['cocina', 'listo', 'entregado'] as $esperado) {
            $this->actingAs($usuario)->postJson($this->url($restaurante, "/{$pedido->id}/avanzar"))
                ->assertOk()->assertJsonPath('data.estado', $esperado);
        }

        $this->assertNotNull($pedido->refresh()->entregado_at);
        $this->actingAs($usuario)->postJson($this->url($restaurante, "/{$pedido->id}/avanzar"))->assertUnprocessable();
    }

    public function test_cannot_advance_order_of_another_restaurant(): void
    {
        $propio = Restaurante::factory()->create();
        $ajeno = Restaurante::factory()->create();
        $pedido = Pedido::factory()->create(['restaurante_id' => $ajeno->id]);

        $this->actingAs($this->miembro($propio))->postJson($this->url($propio, "/{$pedido->id}/avanzar"))->assertNotFound();
        $this->assertSame('nuevo', $pedido->refresh()->estado);
    }
}
