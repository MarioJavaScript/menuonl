<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CodigoQr;
use App\Models\Contacto;
use App\Models\HistorialCambio;
use App\Models\Horario;
use App\Models\Invitacion;
use App\Models\Producto;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestauranteMultiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurante $restauranteA;

    protected Restaurante $restauranteB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restauranteA = Restaurante::create([
            'nombre' => 'Restaurante A (Taquería)',
            'slug' => 'restaurante-a',
        ]);

        $this->restauranteB = Restaurante::create([
            'nombre' => 'Restaurante B (Pizzería)',
            'slug' => 'restaurante-b',
        ]);
    }

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    /**
     * Tier 1: RestauranteScope isolates Categoria models between tenants.
     */
    public function test_restaurante_scope_isolates_categorias(): void
    {
        Categoria::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteA->id,
            'orden' => 1,
            'visible' => true,
        ]);

        Categoria::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteB->id,
            'orden' => 1,
            'visible' => true,
        ]);

        RestauranteScope::setRestauranteId($this->restauranteA->id);
        $catsA = Categoria::all();
        $this->assertCount(1, $catsA);
        $this->assertEquals($this->restauranteA->id, $catsA->first()->restaurante_id);

        RestauranteScope::setRestauranteId($this->restauranteB->id);
        $catsB = Categoria::all();
        $this->assertCount(1, $catsB);
        $this->assertEquals($this->restauranteB->id, $catsB->first()->restaurante_id);
    }

    /**
     * Tier 1: RestauranteScope isolates Producto models between tenants.
     */
    public function test_restaurante_scope_isolates_productos(): void
    {
        $catA = Categoria::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteA->id,
            'orden' => 1,
            'visible' => true,
        ]);

        $catB = Categoria::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteB->id,
            'orden' => 1,
            'visible' => true,
        ]);

        Producto::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteA->id,
            'categoria_id' => $catA->id,
            'precio' => '45.00',
            'disponible' => true,
            'visible' => true,
        ]);

        Producto::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteB->id,
            'categoria_id' => $catB->id,
            'precio' => '120.00',
            'disponible' => true,
            'visible' => true,
        ]);

        RestauranteScope::setRestauranteId($this->restauranteA->id);
        $prodsA = Producto::all();
        $this->assertCount(1, $prodsA);
        $this->assertEquals($this->restauranteA->id, $prodsA->first()->restaurante_id);
        $this->assertEquals('45.00', $prodsA->first()->precio);

        RestauranteScope::setRestauranteId($this->restauranteB->id);
        $prodsB = Producto::all();
        $this->assertCount(1, $prodsB);
        $this->assertEquals($this->restauranteB->id, $prodsB->first()->restaurante_id);
        $this->assertEquals('120.00', $prodsB->first()->precio);
    }

    /**
     * Tier 1: RestauranteScope isolates Contacto models between tenants.
     */
    public function test_restaurante_scope_isolates_contactos(): void
    {
        Contacto::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteA->id,
            'tipo' => 'telefono',
            'valor' => 'tel:5512345678',
            'visible' => true,
        ]);

        Contacto::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteB->id,
            'tipo' => 'telefono',
            'valor' => 'tel:5587654321',
            'visible' => true,
        ]);

        RestauranteScope::setRestauranteId($this->restauranteA->id);
        $this->assertCount(1, Contacto::all());
        $this->assertEquals('tel:5512345678', Contacto::first()->valor);

        RestauranteScope::setRestauranteId($this->restauranteB->id);
        $this->assertCount(1, Contacto::all());
        $this->assertEquals('tel:5587654321', Contacto::first()->valor);
    }

    /**
     * Tier 1: RestauranteScope isolates Horario models between tenants.
     */
    public function test_restaurante_scope_isolates_horarios(): void
    {
        Horario::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteA->id,
            'dia_semana' => 1,
            'abre' => '09:00',
            'cierra' => '17:00',
            'cerrado' => false,
        ]);

        Horario::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteB->id,
            'dia_semana' => 1,
            'abre' => '13:00',
            'cierra' => '23:00',
            'cerrado' => false,
        ]);

        RestauranteScope::setRestauranteId($this->restauranteA->id);
        $this->assertCount(1, Horario::all());
        $this->assertEquals('09:00', substr(Horario::first()->abre, 0, 5));

        RestauranteScope::setRestauranteId($this->restauranteB->id);
        $this->assertCount(1, Horario::all());
        $this->assertEquals('13:00', substr(Horario::first()->abre, 0, 5));
    }

    /**
     * Tier 1: RestauranteScope isolates CodigoQr models between tenants.
     */
    public function test_restaurante_scope_isolates_codigos_qr(): void
    {
        CodigoQr::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteA->id,
            'nombre' => 'QR General A',
            'codigo' => 'QR-TACOS-01',
            'activo' => true,
        ]);

        CodigoQr::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteB->id,
            'nombre' => 'QR General B',
            'codigo' => 'QR-PIZZA-01',
            'activo' => true,
        ]);

        RestauranteScope::setRestauranteId($this->restauranteA->id);
        $this->assertCount(1, CodigoQr::all());
        $this->assertEquals('QR-TACOS-01', CodigoQr::first()->codigo);

        RestauranteScope::setRestauranteId($this->restauranteB->id);
        $this->assertCount(1, CodigoQr::all());
        $this->assertEquals('QR-PIZZA-01', CodigoQr::first()->codigo);
    }

    /**
     * Tier 1: RestauranteScope isolates Invitacion and HistorialCambio records.
     */
    public function test_restaurante_scope_isolates_invitaciones_and_historial_cambios(): void
    {
        Invitacion::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteA->id,
            'email' => 'waiterA@example.com',
            'rol' => 'editor',
            'token' => str_repeat('a', 64),
            'expira_en' => now()->addDays(7),
        ]);

        Invitacion::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteB->id,
            'email' => 'waiterB@example.com',
            'rol' => 'editor',
            'token' => str_repeat('b', 64),
            'expira_en' => now()->addDays(7),
        ]);

        HistorialCambio::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteA->id,
            'entidad' => 'producto',
            'entidad_id' => 10,
            'accion' => 'crear',
            'cambios' => ['nombre' => ['antes' => null, 'despues' => 'Taco']],
        ]);

        HistorialCambio::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteB->id,
            'entidad' => 'producto',
            'entidad_id' => 20,
            'accion' => 'crear',
            'cambios' => ['nombre' => ['antes' => null, 'despues' => 'Pizza']],
        ]);

        RestauranteScope::setRestauranteId($this->restauranteA->id);
        $this->assertCount(1, Invitacion::all());
        $this->assertEquals('waiterA@example.com', Invitacion::first()->email);
        $this->assertCount(1, HistorialCambio::all());
        $this->assertEquals(10, HistorialCambio::first()->entidad_id);

        RestauranteScope::setRestauranteId($this->restauranteB->id);
        $this->assertCount(1, Invitacion::all());
        $this->assertEquals('waiterB@example.com', Invitacion::first()->email);
        $this->assertCount(1, HistorialCambio::all());
        $this->assertEquals(20, HistorialCambio::first()->entidad_id);
    }

    /**
     * Tier 2: Automatic assignment of restaurante_id on create via BelongsToRestaurante trait.
     */
    public function test_automatic_assignment_of_restaurante_id_on_create(): void
    {
        RestauranteScope::setRestauranteId($this->restauranteA->id);

        $categoria = Categoria::create([
            'orden' => 5,
            'visible' => true,
        ]);

        $this->assertEquals($this->restauranteA->id, $categoria->restaurante_id);
        $this->assertDatabaseHas('categorias', [
            'id' => $categoria->id,
            'restaurante_id' => $this->restauranteA->id,
        ]);
    }

    /**
     * Tier 2: Direct lookup by ID across tenant boundaries returns null.
     */
    public function test_direct_lookup_of_other_tenant_record_returns_null(): void
    {
        $catB = Categoria::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteB->id,
            'orden' => 1,
            'visible' => true,
        ]);

        // Scoped to Restaurant A, searching for Restaurant B's category ID
        RestauranteScope::setRestauranteId($this->restauranteA->id);
        $found = Categoria::find($catB->id);

        $this->assertNull($found, 'Cross-tenant entity lookup by primary key must return null.');
    }

    /**
     * Tier 3: Context switching changes active query isolation dynamically.
     */
    public function test_context_switching_switches_active_scope_synchronously(): void
    {
        Categoria::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteA->id,
            'orden' => 1,
            'visible' => true,
        ]);
        Categoria::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteB->id,
            'orden' => 2,
            'visible' => true,
        ]);

        RestauranteScope::setRestauranteId($this->restauranteA->id);
        $this->assertEquals(1, Categoria::count());

        RestauranteScope::setRestauranteId($this->restauranteB->id);
        $this->assertEquals(1, Categoria::count());

        RestauranteScope::setRestauranteId(null);
        $this->assertEquals(2, Categoria::count());
    }
}
