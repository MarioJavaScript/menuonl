<?php

namespace Tests\Feature;

use App\Filament\Pages\ConfiguracionRestaurante;
use App\Filament\Resources\ContactoResource\Pages\CreateContacto;
use App\Filament\Resources\ProductoResource\Pages\ListProductos;
use App\Models\Categoria;
use App\Models\Contacto;
use App\Models\Producto;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentM4ChallengerTest extends TestCase
{
    use RefreshDatabase;

    protected Restaurante $restauranteA;

    protected Restaurante $restauranteB;

    protected User $superadmin;

    protected User $propietarioA;

    protected User $editorA;

    protected User $editorAPropietarioB;

    protected Categoria $categoriaA;

    protected Producto $productoA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restauranteA = Restaurante::create([
            'nombre' => 'Tapas Bar A',
            'slug' => 'tapas-bar-a',
            'estado' => 'activo',
            'idioma_default' => 'es',
            'moneda' => 'EUR',
            'zona_horaria' => 'Europe/Madrid',
        ]);

        $this->restauranteB = Restaurante::create([
            'nombre' => 'Bistro B',
            'slug' => 'bistro-b',
            'estado' => 'activo',
            'idioma_default' => 'es',
            'moneda' => 'EUR',
            'zona_horaria' => 'Europe/Madrid',
        ]);

        $this->superadmin = User::create([
            'name' => 'Superadmin Admin',
            'email' => 'superadmin@menuonl.test',
            'password' => bcrypt('password123'),
            'es_superadmin' => true,
            'activo' => true,
        ]);

        $this->propietarioA = User::create([
            'name' => 'Owner A',
            'email' => 'owner_a@menuonl.test',
            'password' => bcrypt('password123'),
            'es_superadmin' => false,
            'activo' => true,
        ]);
        $this->propietarioA->restaurantes()->attach($this->restauranteA->id, ['rol' => 'propietario']);

        $this->editorA = User::create([
            'name' => 'Editor A',
            'email' => 'editor_a@menuonl.test',
            'password' => bcrypt('password123'),
            'es_superadmin' => false,
            'activo' => true,
        ]);
        $this->editorA->restaurantes()->attach($this->restauranteA->id, ['rol' => 'editor']);

        $this->editorAPropietarioB = User::create([
            'name' => 'Cross Role User',
            'email' => 'cross_role@menuonl.test',
            'password' => bcrypt('password123'),
            'es_superadmin' => false,
            'activo' => true,
        ]);
        $this->editorAPropietarioB->restaurantes()->attach($this->restauranteA->id, ['rol' => 'editor']);
        $this->editorAPropietarioB->restaurantes()->attach($this->restauranteB->id, ['rol' => 'propietario']);

        RestauranteScope::setRestauranteId($this->restauranteA->id);

        $this->categoriaA = Categoria::create([
            'restaurante_id' => $this->restauranteA->id,
            'orden' => 1,
            'visible' => true,
        ]);

        $this->productoA = Producto::create([
            'restaurante_id' => $this->restauranteA->id,
            'categoria_id' => $this->categoriaA->id,
            'precio' => '15.50',
            'precio_oferta' => '12.00',
            'disponible' => true,
            'visible' => true,
            'destacado' => false,
            'orden' => 1,
        ]);
    }

    // =========================================================================
    // 1. ConfiguracionRestaurante Authorization Tests
    // =========================================================================

    /**
     * Adversarial Test: Editor attempting HTTP GET to ConfiguracionRestaurante is denied 403.
     */
    public function test_editor_http_access_to_configuracion_restaurante_returns_403_forbidden(): void
    {
        $response = $this->actingAs($this->editorA)
            ->get("/admin/{$this->restauranteA->slug}/configuracion-restaurante");

        $response->assertStatus(403);
    }

    /**
     * Adversarial Test: Cross-role user (Editor in Rest A, Propietario in Rest B)
     * attempting to access Rest A's ConfiguracionRestaurante is denied 403.
     */
    public function test_cross_role_user_editor_in_tenant_cannot_access_settings_403(): void
    {
        $response = $this->actingAs($this->editorAPropietarioB)
            ->get("/admin/{$this->restauranteA->slug}/configuracion-restaurante");

        $response->assertStatus(403);
    }

    /**
     * Adversarial Test: Editor mounting ConfiguracionRestaurante Livewire component is forbidden (403).
     */
    public function test_editor_mounting_configuracion_restaurante_throws_403_exception(): void
    {
        $this->actingAs($this->editorA);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->restauranteA, isQuiet: true);
        RestauranteScope::setRestauranteId($this->restauranteA->id);

        Livewire::test(ConfiguracionRestaurante::class)
            ->assertForbidden();
    }

    /**
     * Verification Test: Propietario accessing ConfiguracionRestaurante.
     * CHALLENGE: Uncovers fatal Error: Class "Filament\Forms\Components\Section" not found.
     */
    public function test_propietario_can_access_configuracion_restaurante(): void
    {
        $response = $this->actingAs($this->propietarioA)
            ->get("/admin/{$this->restauranteA->slug}/configuracion-restaurante");

        $response->assertStatus(200);
    }

    /**
     * Verification Test: Superadmin accessing ConfiguracionRestaurante.
     * CHALLENGE: Uncovers fatal Error: Class "Filament\Forms\Components\Section" not found.
     */
    public function test_superadmin_can_access_configuracion_restaurante(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get("/admin/{$this->restauranteA->slug}/configuracion-restaurante");

        $response->assertStatus(200);
    }

    /**
     * Adversarial Test: Accessing Contacto create page.
     * CHALLENGE: Uncovers fatal Error: Class "Filament\Forms\Components\Section" not found in ContactoResource.
     */
    public function test_contacto_create_page_renders_without_fatal_error(): void
    {
        $response = $this->actingAs($this->propietarioA)
            ->get("/admin/{$this->restauranteA->slug}/contactos/create");

        $response->assertStatus(200);
    }

    /**
     * Adversarial Test: Accessing Producto create page.
     * CHALLENGE: Uncovers fatal Error: Class "Filament\Forms\Components\Section" not found in ProductoResource.
     */
    public function test_producto_create_page_renders_without_fatal_error(): void
    {
        $response = $this->actingAs($this->propietarioA)
            ->get("/admin/{$this->restauranteA->slug}/productos/create");

        $response->assertStatus(200);
    }

    // =========================================================================
    // 2. Producto Quick-Edit Negative Price Validation Tests
    // =========================================================================

    /**
     * Adversarial Test: Quick-edit negative 'precio' fails validation and does NOT update database.
     */
    public function test_quick_edit_negative_precio_fails_validation(): void
    {
        $this->actingAs($this->propietarioA);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->restauranteA, isQuiet: true);
        RestauranteScope::setRestauranteId($this->restauranteA->id);

        $component = Livewire::test(ListProductos::class);

        $result = $component->instance()->updateTableColumnState('precio', (string) $this->productoA->id, '-10.00');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);

        $this->productoA->refresh();
        $this->assertEquals('15.50', $this->productoA->precio);
    }

    /**
     * Adversarial Test: Quick-edit negative 'precio_oferta' fails validation and does NOT update database.
     */
    public function test_quick_edit_negative_precio_oferta_fails_validation(): void
    {
        $this->actingAs($this->propietarioA);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->restauranteA, isQuiet: true);
        RestauranteScope::setRestauranteId($this->restauranteA->id);

        $component = Livewire::test(ListProductos::class);

        $result = $component->instance()->updateTableColumnState('precio_oferta', (string) $this->productoA->id, '-5.99');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);

        $this->productoA->refresh();
        $this->assertEquals('12.00', $this->productoA->precio_oferta);
    }

    /**
     * Adversarial Test: Quick-edit non-numeric 'precio' fails validation.
     */
    public function test_quick_edit_non_numeric_precio_fails_validation(): void
    {
        $this->actingAs($this->propietarioA);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->restauranteA, isQuiet: true);
        RestauranteScope::setRestauranteId($this->restauranteA->id);

        $component = Livewire::test(ListProductos::class);

        $result = $component->instance()->updateTableColumnState('precio', (string) $this->productoA->id, 'not-a-price');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);

        $this->productoA->refresh();
        $this->assertEquals('15.50', $this->productoA->precio);
    }

    /**
     * Verification Test: Quick-edit valid 'precio' and 'precio_oferta' succeeds and updates database.
     */
    public function test_quick_edit_valid_precio_succeeds(): void
    {
        $this->actingAs($this->propietarioA);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->restauranteA, isQuiet: true);
        RestauranteScope::setRestauranteId($this->restauranteA->id);

        $component = Livewire::test(ListProductos::class);

        $component->instance()->updateTableColumnState('precio', (string) $this->productoA->id, '22.50');

        $this->productoA->refresh();
        $this->assertEquals('22.50', $this->productoA->precio);

        $component->instance()->updateTableColumnState('precio_oferta', (string) $this->productoA->id, '18.00');

        $this->productoA->refresh();
        $this->assertEquals('18.00', $this->productoA->precio_oferta);
    }

    // =========================================================================
    // 3. Contacto URL Validation & Dangerous Scheme Rejection Tests
    // =========================================================================

    /**
     * Adversarial Test: Contacto model static method rejects dangerous protocols.
     */
    public function test_contacto_model_es_url_permitida_rejects_evil_schemes(): void
    {
        $evilUrls = [
            'javascript:alert(1)',
            'JAVASCRIPT:alert(document.cookie)',
            '  javascript:alert(1)  ',
            'data:text/html,<script>alert(1)</script>',
            'DATA:image/svg+xml;base64,PHN2Zw==',
            'vbscript:msgbox(1)',
            'VBSCRIPT:execute(something)',
            'customscheme://payload',
            'file:///etc/passwd',
        ];

        foreach ($evilUrls as $evilUrl) {
            $this->assertFalse(
                Contacto::esUrlPermitida($evilUrl),
                "Expected {$evilUrl} to be rejected by Contacto::esUrlPermitida"
            );
        }
    }

    /**
     * Verification Test: Contacto model static method allows legitimate protocols and contacts.
     */
    public function test_contacto_model_es_url_permitida_allows_safe_schemes(): void
    {
        $safeUrls = [
            'https://instagram.com/myrestaurant',
            'http://restaurant.com/menu',
            'tel:+34600112233',
            'mailto:reservas@restaurant.com',
            '+34 600 11 22 33',
            'Calle Principal 123, Madrid',
        ];

        foreach ($safeUrls as $safeUrl) {
            $this->assertTrue(
                Contacto::esUrlPermitida($safeUrl),
                "Expected {$safeUrl} to be permitted by Contacto::esUrlPermitida"
            );
        }
    }

    /**
     * Adversarial Test: Submitting javascript:alert(1) in Contacto form triggers validation failure.
     */
    public function test_contacto_form_rejects_javascript_url(): void
    {
        $this->actingAs($this->propietarioA);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->restauranteA, isQuiet: true);
        RestauranteScope::setRestauranteId($this->restauranteA->id);

        Livewire::test(CreateContacto::class)
            ->fillForm([
                'tipo' => 'web',
                'valor' => 'javascript:alert(1)',
                'orden' => 0,
                'visible' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['valor']);

        $this->assertDatabaseMissing('contactos', [
            'valor' => 'javascript:alert(1)',
        ]);
    }

    /**
     * Adversarial Test: Submitting data:text/html in Contacto form triggers validation failure.
     */
    public function test_contacto_form_rejects_data_url(): void
    {
        $this->actingAs($this->propietarioA);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->restauranteA, isQuiet: true);
        RestauranteScope::setRestauranteId($this->restauranteA->id);

        Livewire::test(CreateContacto::class)
            ->fillForm([
                'tipo' => 'web',
                'valor' => 'data:text/html,<script>alert(1)</script>',
                'orden' => 0,
                'visible' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['valor']);

        $this->assertDatabaseMissing('contactos', [
            'valor' => 'data:text/html,<script>alert(1)</script>',
        ]);
    }

    /**
     * Verification Test: Submitting a valid HTTPS contact in Contacto form succeeds.
     */
    public function test_contacto_form_accepts_valid_url(): void
    {
        $this->actingAs($this->propietarioA);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->restauranteA, isQuiet: true);
        RestauranteScope::setRestauranteId($this->restauranteA->id);

        Livewire::test(CreateContacto::class)
            ->fillForm([
                'tipo' => 'instagram',
                'valor' => 'https://instagram.com/tapas_bar_a',
                'etiqueta' => 'Instagram Oficial',
                'orden' => 1,
                'visible' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('contactos', [
            'restaurante_id' => $this->restauranteA->id,
            'tipo' => 'instagram',
            'valor' => 'https://instagram.com/tapas_bar_a',
        ]);
    }
}
