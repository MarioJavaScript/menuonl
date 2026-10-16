<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CodigoQr;
use App\Models\Horario;
use App\Models\Invitacion;
use App\Models\Producto;
use App\Models\ProductoVariante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AyudantesDeModeloTest extends TestCase
{
    use RefreshDatabase;

    public function test_precio_vigente_usa_la_oferta_solo_si_es_menor(): void
    {
        $this->assertSame('80.00', Producto::factory()->make(['precio' => 100, 'precio_oferta' => 80])->precioVigente());
        $this->assertSame('100.00', Producto::factory()->make(['precio' => 100, 'precio_oferta' => null])->precioVigente());
        $this->assertSame('100.00', Producto::factory()->make(['precio' => 100, 'precio_oferta' => 100])->precioVigente());
    }

    public function test_tiene_variantes(): void
    {
        $producto = Producto::factory()->create();
        $this->assertFalse($producto->tieneVariantes());

        ProductoVariante::factory()->create(['producto_id' => $producto->id]);
        $this->assertTrue($producto->tieneVariantes());
        $this->assertTrue($producto->fresh()->load('variantes')->tieneVariantes());
    }

    public function test_scopes_de_catalogo_filtran_y_ordenan(): void
    {
        $producto = Producto::factory()->create(['visible' => true, 'disponible' => true, 'destacado' => true, 'orden' => 2]);
        Producto::factory()->create(['restaurante_id' => $producto->restaurante_id, 'visible' => false, 'disponible' => false, 'destacado' => false, 'orden' => 1]);

        $this->assertSame([$producto->id], Producto::visible()->pluck('id')->all());
        $this->assertSame([$producto->id], Producto::disponible()->pluck('id')->all());
        $this->assertSame([$producto->id], Producto::destacado()->pluck('id')->all());
        $this->assertSame([2, 1], Producto::ordenado()->pluck('orden')->sort()->reverse()->values()->all());
        $this->assertSame(1, (int) Producto::ordenado()->first()->orden);

        $categoria = Categoria::factory()->create(['visible' => false]);
        $this->assertFalse(Categoria::visible()->whereKey($categoria->id)->exists());
        $this->assertSame(1, Categoria::ordenado()->where('id', $categoria->id)->count());
    }

    public function test_codigo_qr_activo_y_es_mesa(): void
    {
        $general = CodigoQr::factory()->create(['mesa' => null, 'activo' => true]);
        $mesa = CodigoQr::factory()->create(['restaurante_id' => $general->restaurante_id, 'mesa' => '5', 'activo' => false]);

        $this->assertFalse($general->esMesa());
        $this->assertTrue($mesa->esMesa());
        $this->assertSame([$general->id], CodigoQr::activo()->pluck('id')->all());
    }

    public function test_horario_del_dia(): void
    {
        $lunes = Horario::factory()->create(['dia_semana' => 1]);
        Horario::factory()->create(['restaurante_id' => $lunes->restaurante_id, 'dia_semana' => 2]);

        $this->assertSame([$lunes->id], Horario::delDia(1)->pluck('id')->all());
    }

    public function test_invitacion_estado(): void
    {
        $vigente = Invitacion::factory()->create(['expira_en' => now()->addDay(), 'aceptada_en' => null]);
        $caducada = Invitacion::factory()->create(['restaurante_id' => $vigente->restaurante_id, 'expira_en' => now()->subMinute(), 'aceptada_en' => null]);
        $aceptada = Invitacion::factory()->create(['restaurante_id' => $vigente->restaurante_id, 'expira_en' => now()->addDay(), 'aceptada_en' => now()]);

        $this->assertFalse($vigente->isExpired());
        $this->assertTrue($caducada->isExpired());
        $this->assertTrue($aceptada->isAccepted());
        $this->assertFalse($vigente->isAccepted());
        $this->assertSame([$vigente->id], Invitacion::pendiente()->pluck('id')->all());
    }
}
