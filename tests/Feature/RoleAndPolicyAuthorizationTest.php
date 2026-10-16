<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\HistorialCambio;
use App\Models\Invitacion;
use App\Models\Producto;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RoleAndPolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;

    protected User $propietario;

    protected User $editor;

    protected User $usuarioSinRestaurante;

    protected Restaurante $restauranteA;

    protected Restaurante $restauranteB;

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->restauranteA = Restaurante::create([
            'nombre' => 'Restaurante A',
            'slug' => 'restaurante-a',
            'estado' => 'activo',
        ]);

        $this->restauranteB = Restaurante::create([
            'nombre' => 'Restaurante B',
            'slug' => 'restaurante-b',
            'estado' => 'activo',
        ]);

        $this->superadmin = User::create([
            'name' => 'Superadmin User',
            'email' => 'superadmin@example.com',
            'password' => 'secret123',
            'activo' => true,
        ]);
        // es_superadmin is not mass assignable (AsignacionMasivaUsuarioTest)
        $this->superadmin->forceFill(['es_superadmin' => true])->save();

        $this->propietario = User::create([
            'name' => 'Propietario User',
            'email' => 'owner@example.com',
            'password' => 'secret123',
            'es_superadmin' => false,
            'activo' => true,
        ]);
        $this->propietario->restaurantes()->attach($this->restauranteA->id, ['rol' => 'propietario']);

        $this->editor = User::create([
            'name' => 'Editor User',
            'email' => 'editor@example.com',
            'password' => 'secret123',
            'es_superadmin' => false,
            'activo' => true,
        ]);
        $this->editor->restaurantes()->attach($this->restauranteA->id, ['rol' => 'editor']);

        $this->usuarioSinRestaurante = User::create([
            'name' => 'Sin Restaurante',
            'email' => 'ninguno@example.com',
            'password' => 'secret123',
            'es_superadmin' => false,
            'activo' => true,
        ]);
    }

    /**
     * Tier 1: Superadmin can create and suspend restaurants.
     */
    public function test_superadmin_can_create_and_suspend_restaurants(): void
    {
        $this->assertTrue($this->superadmin->isSuperadmin());

        // Policy checks for Restaurante
        $this->assertTrue(Gate::forUser($this->superadmin)->allows('viewAny', Restaurante::class));
        $this->assertTrue(Gate::forUser($this->superadmin)->allows('create', Restaurante::class));
        $this->assertTrue(Gate::forUser($this->superadmin)->allows('update', $this->restauranteA));

        // Superadmin suspends restaurant
        $this->restauranteA->estado = 'suspendido';
        $this->restauranteA->save();

        $this->assertEquals('suspendido', $this->restauranteA->fresh()->estado);
        $this->assertFalse($this->restauranteA->fresh()->isActivo());

        // Superadmin reactivates restaurant
        $this->restauranteA->estado = 'activo';
        $this->restauranteA->save();

        $this->assertEquals('activo', $this->restauranteA->fresh()->estado);
        $this->assertTrue($this->restauranteA->fresh()->isActivo());
    }

    /**
     * Tier 1: Superadmin can manage global dietary tags, but owners and editors cannot.
     */
    public function test_superadmin_can_manage_global_tags_while_others_cannot(): void
    {
        $this->assertTrue(Gate::forUser($this->superadmin)->allows('create', Etiqueta::class));
        $this->assertFalse(Gate::forUser($this->propietario)->allows('create', Etiqueta::class));
        $this->assertFalse(Gate::forUser($this->editor)->allows('create', Etiqueta::class));
    }

    /**
     * Tier 1: Propietario can manage restaurant settings, audit history, and user invitations.
     */
    public function test_propietario_can_manage_settings_audit_and_invitations(): void
    {
        // Propietario can update their own restaurant settings
        $this->assertTrue(Gate::forUser($this->propietario)->allows('update', $this->restauranteA));

        // Abilities without a record are judged against the active restaurant
        RestauranteScope::setRestauranteId($this->restauranteA->id);

        // Propietario can manage invitations
        $this->assertTrue(Gate::forUser($this->propietario)->allows('create', Invitacion::class));

        // Propietario can view audit history
        $this->assertTrue(Gate::forUser($this->propietario)->allows('viewAny', HistorialCambio::class));
    }

    /**
     * Tier 2: Propietario cannot manage another restaurant's settings.
     */
    public function test_propietario_cannot_manage_other_restaurant(): void
    {
        $this->assertFalse(
            Gate::forUser($this->propietario)->allows('update', $this->restauranteB),
            'Propietario of Restaurant A must not be allowed to update Restaurant B.'
        );
    }

    /**
     * Tier 1: Editor can manage catalog (categories, products, prices, availability).
     */
    public function test_editor_can_manage_catalog_items(): void
    {
        RestauranteScope::setRestauranteId($this->restauranteA->id);

        $this->assertTrue(Gate::forUser($this->editor)->allows('create', Categoria::class));
        $this->assertTrue(Gate::forUser($this->editor)->allows('create', Producto::class));

        $cat = Categoria::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteA->id,
            'orden' => 1,
            'visible' => true,
        ]);

        $prod = Producto::withoutGlobalScopes()->create([
            'restaurante_id' => $this->restauranteA->id,
            'categoria_id' => $cat->id,
            'precio' => '50.00',
            'disponible' => true,
            'visible' => true,
        ]);

        $this->assertTrue(Gate::forUser($this->editor)->allows('update', $prod));
        $this->assertTrue(Gate::forUser($this->editor)->allows('delete', $prod));
    }

    /**
     * Tier 2: Editor is strictly denied from modifying restaurant configuration.
     */
    public function test_editor_cannot_modify_restaurant_settings(): void
    {
        $this->assertFalse(
            Gate::forUser($this->editor)->allows('update', $this->restauranteA),
            'Editor must be strictly denied from modifying general restaurant settings.'
        );
    }

    /**
     * Tier 2: Editor is strictly denied from creating or managing invitations.
     */
    public function test_editor_cannot_manage_invitations(): void
    {
        $this->assertFalse(
            Gate::forUser($this->editor)->allows('create', Invitacion::class),
            'Editor must be strictly denied from issuing user invitations.'
        );
    }

    /**
     * Tier 2: Editor cannot perform superadmin platform operations.
     */
    public function test_editor_cannot_perform_superadmin_actions(): void
    {
        $this->assertFalse(Gate::forUser($this->editor)->allows('create', Restaurante::class));
    }

    /**
     * Tier 2: Inactive user cannot access admin panel.
     */
    public function test_inactive_user_cannot_access_panel(): void
    {
        $panel = (new Panel)->id('admin');

        $userInactivo = User::create([
            'name' => 'Usuario Inactivo',
            'email' => 'inactivo@example.com',
            'password' => 'secret123',
            'activo' => false,
        ]);

        $this->assertFalse(
            $userInactivo->canAccessPanel($panel),
            'User with activo = false must not be allowed to access the panel.'
        );
    }
}
