<?php

namespace Tests\Feature;

use App\Jobs\PurgarCacheRestauranteJob;
use App\Models\Categoria;
use App\Models\HistorialCambio;
use App\Models\Producto;
use App\Models\Restaurante;
use App\Models\User;
use App\Services\MenuCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuditLogAndCachePurgeTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurante $restaurante;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurante = Restaurante::create([
            'nombre' => 'Trattoria Bella',
            'slug' => 'trattoria-bella',
            'idioma_default' => 'es',
        ]);

        $this->user = User::create([
            'name' => 'Luigi Rossi',
            'email' => 'luigi@trattoria.com',
            'password' => 'secret123',
            'activo' => true,
        ]);
        $this->user->restaurantes()->attach($this->restaurante->id, ['rol' => 'propietario']);
    }

    /**
     * Tier 1: Creating an entity records an audit log entry in historial_cambios.
     */
    public function test_entity_creation_records_audit_log_snapshot(): void
    {
        $this->actingAs($this->user);

        $cat = Categoria::create([
            'restaurante_id' => $this->restaurante->id,
            'orden' => 1,
            'visible' => true,
        ]);

        $this->assertDatabaseHas('historial_cambios', [
            'restaurante_id' => $this->restaurante->id,
            'usuario_id' => $this->user->id,
            'entidad' => 'categoria',
            'entidad_id' => $cat->id,
            'accion' => 'crear',
        ]);

        $log = HistorialCambio::where('entidad', 'categoria')
            ->where('entidad_id', $cat->id)
            ->where('accion', 'crear')
            ->first();

        $this->assertNotNull($log);
        $this->assertIsArray($log->cambios);
        $this->assertArrayHasKey('orden', $log->cambios);
        $this->assertEquals(1, $log->cambios['orden']['despues']);
        $this->assertNull($log->cambios['orden']['antes']);
    }

    /**
     * Tier 1: Updating an entity records accurate before/after diff in historial_cambios.
     */
    public function test_entity_update_records_before_and_after_diff(): void
    {
        $cat = Categoria::create([
            'restaurante_id' => $this->restaurante->id,
            'orden' => 1,
            'visible' => true,
        ]);

        $prod = Producto::create([
            'restaurante_id' => $this->restaurante->id,
            'categoria_id' => $cat->id,
            'precio' => '100.00',
            'disponible' => true,
            'visible' => true,
        ]);

        $this->actingAs($this->user);

        // Update price and availability
        $prod->update([
            'precio' => '125.00',
            'disponible' => false,
        ]);

        $log = HistorialCambio::where('entidad', 'producto')
            ->where('entidad_id', $prod->id)
            ->where('accion', 'actualizar')
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'An audit log row must be recorded upon updating a product.');
        $this->assertEquals($this->user->id, $log->usuario_id);
        $this->assertIsArray($log->cambios);

        // Assert before/after diff for precio
        $this->assertArrayHasKey('precio', $log->cambios);
        $this->assertEquals('100.00', $log->cambios['precio']['antes']);
        $this->assertEquals('125.00', $log->cambios['precio']['despues']);

        // Assert before/after diff for disponible
        $this->assertArrayHasKey('disponible', $log->cambios);
        $this->assertEquals(1, (int) $log->cambios['disponible']['antes']);
        $this->assertEquals(0, (int) $log->cambios['disponible']['despues']);
    }

    /**
     * Tier 1: Deleting an entity records an elimination snapshot in historial_cambios.
     */
    public function test_entity_deletion_records_audit_log_snapshot(): void
    {
        $cat = Categoria::create([
            'restaurante_id' => $this->restaurante->id,
            'orden' => 1,
            'visible' => true,
        ]);

        $this->actingAs($this->user);
        $catId = $cat->id;
        $cat->delete();

        $this->assertDatabaseHas('historial_cambios', [
            'restaurante_id' => $this->restaurante->id,
            'usuario_id' => $this->user->id,
            'entidad' => 'categoria',
            'entidad_id' => $catId,
            'accion' => 'eliminar',
        ]);

        $log = HistorialCambio::where('entidad', 'categoria')
            ->where('entidad_id', $catId)
            ->where('accion', 'eliminar')
            ->first();

        $this->assertNotNull($log);
        $this->assertIsArray($log->cambios);
        $this->assertNull($log->cambios['orden']['despues']);
    }

    /**
     * Tier 3: MenuCacheService invalidates L1 cache keys and dispatches PurgarCacheRestauranteJob.
     */
    public function test_menu_cache_service_invalidates_l1_and_dispatches_l2_job(): void
    {
        Queue::fake();

        $cacheService = app(MenuCacheService::class);

        // Seed L1 cache
        Cache::put("menu:{$this->restaurante->id}:es", ['dummy' => 'payload'], 3600);
        Cache::put("restaurante:id:{$this->restaurante->id}", ['info'], 3600);
        Cache::put("restaurante:slug:{$this->restaurante->slug}", ['info'], 3600);

        $this->assertTrue(Cache::has("menu:{$this->restaurante->id}:es"));
        $this->assertTrue(Cache::has("restaurante:id:{$this->restaurante->id}"));

        // Trigger invalidation
        $cacheService->invalidateRestaurante($this->restaurante->id);

        // L1 cache keys must be cleared
        $this->assertFalse(Cache::has("menu:{$this->restaurante->id}:es"));
        $this->assertFalse(Cache::has("restaurante:id:{$this->restaurante->id}"));
        $this->assertFalse(Cache::has("restaurante:slug:{$this->restaurante->slug}"));

        // L2 async Cloudflare job must be dispatched
        Queue::assertPushed(PurgarCacheRestauranteJob::class, function ($job) {
            return $job->restauranteId === $this->restaurante->id
                && $job->slug === $this->restaurante->slug;
        });
    }

    /**
     * Tier 2: PurgarCacheRestauranteJob terminates safely when Cloudflare is unconfigured.
     */
    public function test_purgar_cache_job_runs_safely_when_unconfigured(): void
    {
        config([
            'services.cloudflare.zone_id' => null,
            'services.cloudflare.api_token' => null,
        ]);

        $job = new PurgarCacheRestauranteJob($this->restaurante->id, $this->restaurante->slug);

        // Should complete without throwing any exception
        $job->handle();

        $this->assertTrue(true);
    }
}
