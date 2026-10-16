<?php

namespace Tests\Feature;

use App\Filament\Pages\Tenancy\EditRestauranteProfile;
use App\Filament\Resources\Contactos\Pages\CreateContacto;
use App\Filament\Resources\Productos\Pages\CreateProducto;
use App\Models\Categoria;
use App\Models\Contacto;
use App\Models\Producto;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use App\Rules\ValorContactoSeguro;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
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
            'password' => Hash::make('password123'),
            'activo' => true,
        ]);
        // es_superadmin is not mass assignable (AsignacionMasivaUsuarioTest)
        $this->superadmin->forceFill(['es_superadmin' => true])->save();

        $this->propietarioA = User::create([
            'name' => 'Owner A',
            'email' => 'owner_a@menuonl.test',
            'password' => Hash::make('password123'),
            'es_superadmin' => false,
            'activo' => true,
        ]);
        $this->propietarioA->restaurantes()->attach($this->restauranteA->id, ['rol' => 'propietario']);

        $this->editorA = User::create([
            'name' => 'Editor A',
            'email' => 'editor_a@menuonl.test',
            'password' => Hash::make('password123'),
            'es_superadmin' => false,
            'activo' => true,
        ]);
        $this->editorA->restaurantes()->attach($this->restauranteA->id, ['rol' => 'editor']);

        $this->editorAPropietarioB = User::create([
            'name' => 'Cross Role User',
            'email' => 'cross_role@menuonl.test',
            'password' => Hash::make('password123'),
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

    /**
     * Context shared by the Livewire tests: the user inside the panel with restaurant A as active tenant.
     */
    private function entrarComo(User $usuario): void
    {
        $this->actingAs($usuario);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($this->restauranteA, isQuiet: true);
        Filament::bootCurrentPanel();
        Filament::setServingStatus();
        RestauranteScope::setRestauranteId($this->restauranteA->id);
    }

    // =========================================================================
    // 1. Restaurant settings (EditRestauranteProfile) authorization
    // =========================================================================

    public function test_editor_http_access_to_restaurant_settings_is_hidden_with_404(): void
    {
        $this->actingAs($this->editorA)
            ->get("/admin/{$this->restauranteA->slug}/profile")
            ->assertNotFound();
    }

    public function test_cross_role_user_editor_in_tenant_cannot_access_its_settings(): void
    {
        $this->actingAs($this->editorAPropietarioB)
            ->get("/admin/{$this->restauranteA->slug}/profile")
            ->assertNotFound();

        $this->actingAs($this->editorAPropietarioB)
            ->get("/admin/{$this->restauranteB->slug}/profile")
            ->assertOk();
    }

    public function test_editor_mounting_the_settings_page_is_denied(): void
    {
        $this->entrarComo($this->editorA);

        Livewire::test(EditRestauranteProfile::class)->assertNotFound();
    }

    public function test_propietario_can_access_restaurant_settings(): void
    {
        $this->actingAs($this->propietarioA)
            ->get("/admin/{$this->restauranteA->slug}/profile")
            ->assertOk();
    }

    public function test_superadmin_can_access_restaurant_settings(): void
    {
        $this->actingAs($this->superadmin)
            ->get("/admin/{$this->restauranteA->slug}/profile")
            ->assertOk();
    }

    public function test_contacto_create_page_renders_without_fatal_error(): void
    {
        $this->actingAs($this->propietarioA)
            ->get("/admin/{$this->restauranteA->slug}/contactos/create")
            ->assertOk();
    }

    public function test_producto_create_page_renders_without_fatal_error(): void
    {
        $this->actingAs($this->propietarioA)
            ->get("/admin/{$this->restauranteA->slug}/productos/create")
            ->assertOk();
    }

    // =========================================================================
    // 2. Producto price validation in the panel form
    // =========================================================================

    /**
     * @param  array<string, mixed>  $precios
     * @return array<string, mixed>
     */
    private function datosDeProducto(array $precios): array
    {
        return [
            'categoria_id' => $this->categoriaA->id,
            'orden' => 0,
            'traducciones' => [['idioma' => 'es', 'nombre' => 'Platillo de prueba']],
            ...$precios,
        ];
    }

    public function test_product_form_rejects_negative_precio(): void
    {
        $this->entrarComo($this->propietarioA);

        Livewire::test(CreateProducto::class)
            ->fillForm($this->datosDeProducto(['precio' => '-10.00']))
            ->call('create')
            ->assertHasFormErrors(['precio']);

        $this->assertSame(1, Producto::withoutGlobalScopes()->count());
    }

    public function test_product_form_rejects_negative_precio_oferta(): void
    {
        $this->entrarComo($this->propietarioA);

        Livewire::test(CreateProducto::class)
            ->fillForm($this->datosDeProducto(['precio' => '20.00', 'precio_oferta' => '-5.99']))
            ->call('create')
            ->assertHasFormErrors(['precio_oferta']);

        $this->assertSame(1, Producto::withoutGlobalScopes()->count());
    }

    public function test_product_form_rejects_non_numeric_precio(): void
    {
        $this->entrarComo($this->propietarioA);

        Livewire::test(CreateProducto::class)
            ->fillForm($this->datosDeProducto(['precio' => 'not-a-price']))
            ->call('create')
            ->assertHasFormErrors(['precio']);

        $this->assertSame(1, Producto::withoutGlobalScopes()->count());
    }

    public function test_product_form_accepts_valid_precio_and_oferta(): void
    {
        $this->entrarComo($this->propietarioA);

        Livewire::test(CreateProducto::class)
            ->fillForm($this->datosDeProducto(['precio' => '22.50', 'precio_oferta' => '18.00']))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('productos', [
            'restaurante_id' => $this->restauranteA->id,
            'precio' => '22.50',
            'precio_oferta' => '18.00',
        ]);
    }

    // =========================================================================
    // 3. Contacto value validation (ValorContactoSeguro) and form
    // =========================================================================

    private function pasaLaRegla(string $tipo, string $valor): bool
    {
        return Validator::make(['valor' => $valor], ['valor' => [new ValorContactoSeguro($tipo)]])->passes();
    }

    public function test_contacto_rule_rejects_evil_schemes_for_every_type(): void
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

        foreach (['telefono', 'whatsapp', 'email', 'direccion', 'instagram', 'facebook', 'tiktok', 'web', 'otro'] as $tipo) {
            foreach ($evilUrls as $evilUrl) {
                $this->assertFalse($this->pasaLaRegla($tipo, $evilUrl), "{$tipo} accepted {$evilUrl}");
            }
        }
    }

    public function test_contacto_rule_allows_legitimate_values(): void
    {
        $safe = [
            ['instagram', 'https://instagram.com/myrestaurant'],
            ['web', 'http://restaurant.com/menu'],
            ['telefono', 'tel:+34600112233'],
            ['email', 'mailto:reservas@restaurant.com'],
            ['telefono', '+34 600 11 22 33'],
            ['direccion', 'Calle Principal 123, Madrid'],
            ['direccion', 'https://maps.google.com/?q=bistro'],
            ['whatsapp', '5215512345678'],
        ];

        foreach ($safe as [$tipo, $valor]) {
            $this->assertTrue($this->pasaLaRegla($tipo, $valor), "{$tipo} rejected {$valor}");
        }
    }

    public function test_contacto_form_rejects_javascript_url(): void
    {
        $this->entrarComo($this->propietarioA);

        Livewire::test(CreateContacto::class)
            ->fillForm(['tipo' => 'web', 'valor' => 'javascript:alert(1)', 'orden' => 0, 'visible' => true])
            ->call('create')
            ->assertHasFormErrors(['valor']);

        $this->assertDatabaseMissing('contactos', ['valor' => 'javascript:alert(1)']);
    }

    public function test_contacto_form_rejects_data_url(): void
    {
        $this->entrarComo($this->propietarioA);

        Livewire::test(CreateContacto::class)
            ->fillForm(['tipo' => 'web', 'valor' => 'data:text/html,<script>alert(1)</script>', 'orden' => 0, 'visible' => true])
            ->call('create')
            ->assertHasFormErrors(['valor']);

        $this->assertDatabaseMissing('contactos', ['valor' => 'data:text/html,<script>alert(1)</script>']);
    }

    public function test_contacto_form_accepts_valid_url(): void
    {
        $this->entrarComo($this->propietarioA);

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
