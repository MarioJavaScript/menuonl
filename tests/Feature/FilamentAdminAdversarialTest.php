<?php

namespace Tests\Feature;

use App\Filament\Resources\Categorias\Pages\ListCategorias;
use App\Filament\Resources\CodigosQr\Pages\ListCodigosQr;
use App\Filament\Resources\Contactos\Pages\ListContactos;
use App\Filament\Resources\HistorialCambioResource\Pages\ListHistorialCambios;
use App\Filament\Resources\Horarios\Pages\ListHorarios;
use App\Filament\Resources\Invitaciones\Pages\ListInvitaciones;
use App\Filament\Resources\Productos\Pages\ListProductos;
use App\Filament\Resources\Restaurantes\Pages\ListRestaurantes;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\CodigoQr;
use App\Models\Contacto;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

class FilamentAdminAdversarialTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        RestauranteScope::setRestauranteId(null);
        parent::tearDown();
    }

    // =========================================================================
    // Area 1: Multi-Tenant Isolation & Null Safety in 'Ver menú público' Hook
    // =========================================================================

    public function test_render_hook_returns_empty_string_when_tenant_is_null(): void
    {
        // Explicitly clear any active tenant
        Filament::setCurrentPanel('admin');
        Filament::setTenant(null);

        $rendered = (string) FilamentView::renderHook(PanelsRenderHook::USER_MENU_BEFORE);

        $this->assertSame('', trim($rendered), 'When tenant is null, render hook must return an empty string.');
    }

    public function test_tenant_menu_items_closure_safely_handles_null_tenant(): void
    {
        $tenant = $this->crearRestaurante('tenant-menu-test');
        $owner = $this->crearUsuario('propietario', $tenant);
        $this->entrarAlPanel($owner, $tenant);

        $hook = (string) FilamentView::renderHook(PanelsRenderHook::USER_MENU_BEFORE);

        $this->assertNotEmpty($hook);
        $this->assertStringContainsString('Ver mi menú', $hook);
        $this->assertStringContainsString(route('menu.publico', ['slug' => 'tenant-menu-test']), $hook);

        // Now test when tenant is set to null
        Filament::setTenant(null);
        $renderedNull = (string) FilamentView::renderHook(PanelsRenderHook::USER_MENU_BEFORE);
        $this->assertSame('', trim($renderedNull), 'Render hook must evaluate to empty string when tenant is null.');
    }

    public function test_superadmin_viewing_admin_profile_does_not_leak_public_menu_hook(): void
    {
        $tenant = $this->crearRestaurante('restaurante-secreto');
        $superadmin = $this->crearUsuario('superadmin');

        // /admin/profile is a panel route that operates without an active tenant
        $response = $this->actingAs($superadmin)->get('/admin/profile');

        $response->assertStatus(200);
        // Should NOT render the public menu button because tenant is null
        $response->assertDontSee('/m/'.$tenant->slug);
        $response->assertDontSee('Ver mi menú');
    }

    public function test_render_hook_is_strictly_isolated_between_different_tenants(): void
    {
        $tenantA = $this->crearRestaurante('tacos-alpha');
        $tenantB = $this->crearRestaurante('pizzas-beta');

        $userA = $this->crearUsuario('propietario', $tenantA);
        $userB = $this->crearUsuario('propietario', $tenantB);

        // When logged into Tenant A
        $this->entrarAlPanel($userA, $tenantA);
        $hookA = (string) FilamentView::renderHook(PanelsRenderHook::USER_MENU_BEFORE);
        $this->assertStringContainsString('Ver mi menú', $hookA);
        $this->assertStringContainsString(route('menu.publico', ['slug' => 'tacos-alpha']), $hookA);
        $this->assertStringNotContainsString('pizzas-beta', $hookA);

        // When logged into Tenant B
        $this->entrarAlPanel($userB, $tenantB);
        $hookB = (string) FilamentView::renderHook(PanelsRenderHook::USER_MENU_BEFORE);
        $this->assertStringContainsString('Ver mi menú', $hookB);
        $this->assertStringContainsString(route('menu.publico', ['slug' => 'pizzas-beta']), $hookB);
        $this->assertStringNotContainsString('tacos-alpha', $hookB);
    }

    public function test_user_with_multi_tenant_membership_switches_hook_cleanly(): void
    {
        $tenant1 = $this->crearRestaurante('sucursal-norte');
        $tenant2 = $this->crearRestaurante('sucursal-sur');

        $usuario = User::factory()->create();
        $usuario->restaurantes()->attach($tenant1->id, ['rol' => 'editor']);
        $usuario->restaurantes()->attach($tenant2->id, ['rol' => 'propietario']);

        // Enter Tenant 1
        $this->entrarAlPanel($usuario, $tenant1);
        $hook1 = (string) FilamentView::renderHook(PanelsRenderHook::USER_MENU_BEFORE);
        $this->assertStringContainsString('/m/sucursal-norte', $hook1);
        $this->assertStringNotContainsString('/m/sucursal-sur', $hook1);

        // Switch to Tenant 2
        $this->entrarAlPanel($usuario, $tenant2);
        $hook2 = (string) FilamentView::renderHook(PanelsRenderHook::USER_MENU_BEFORE);
        $this->assertStringContainsString('/m/sucursal-sur', $hook2);
        $this->assertStringNotContainsString('/m/sucursal-norte', $hook2);
    }

    public function test_tenant_with_adversarial_slug_is_properly_html_escaped_in_render_hook(): void
    {
        $adversarial = Restaurante::factory()->create();
        // Force an adversarial slug with characters that would break unquoted or unescaped HTML attributes
        $adversarial->forceFill(['slug' => 'slug"onmouseover="alert(1)'])->save();

        $owner = $this->crearUsuario('propietario', $adversarial);
        $this->entrarAlPanel($owner, $adversarial);

        $hook = (string) FilamentView::renderHook(PanelsRenderHook::USER_MENU_BEFORE);

        // Must not contain unescaped attribute injection
        $this->assertStringNotContainsString('slug"onmouseover="alert(1)', $hook);
        $this->assertStringContainsString('slug%22onmouseover=%22alert%281%29', $hook);
    }

    // =========================================================================
    // Area 2: Table Empty States Rendering Across All 9 Resources
    // =========================================================================

    public function test_all_9_resource_tables_render_empty_states_gracefully(): void
    {
        $tenant = $this->crearRestaurante('local-vacio');
        $owner = $this->crearUsuario('propietario', $tenant);
        $this->entrarAlPanel($owner, $tenant);

        $resourcesToTest = [
            'Categorias' => [
                'component' => ListCategorias::class,
                'expectedHeading' => 'Todavía no tienes categorías',
                'expectedIcon' => 'heroicon-o-rectangle-stack',
            ],
            'Productos' => [
                'component' => ListProductos::class,
                'expectedHeading' => 'Todavía no tienes platillos',
                'expectedIcon' => 'heroicon-o-cake',
            ],
            'Horarios' => [
                'component' => ListHorarios::class,
                'expectedHeading' => 'Sin horarios de atención configurados',
                'expectedIcon' => 'heroicon-o-clock',
            ],
            'CodigosQr' => [
                'component' => ListCodigosQr::class,
                'expectedHeading' => 'Sin códigos QR generados',
                'expectedIcon' => 'heroicon-o-qr-code',
            ],
            'Contactos' => [
                'component' => ListContactos::class,
                'expectedHeading' => 'No hay canales de contacto registrados',
                'expectedIcon' => 'heroicon-o-phone',
            ],
            'Invitaciones' => [
                'component' => ListInvitaciones::class,
                'expectedHeading' => 'No hay invitaciones enviadas',
                'expectedIcon' => 'heroicon-o-envelope',
            ],
            'Users' => [
                'component' => ListUsers::class,
                'expectedHeading' => 'No hay miembros adicionales en el equipo',
                'expectedIcon' => 'heroicon-o-users',
            ],
            'HistorialCambios' => [
                'component' => ListHistorialCambios::class,
                'expectedHeading' => 'Sin eventos de auditoría',
                'expectedIcon' => 'heroicon-o-shield-check',
            ],
        ];

        foreach ($resourcesToTest as $name => $spec) {
            $test = Livewire::test($spec['component']);
            $test->assertStatus(200);

            $table = $test->instance()->getTable();

            $this->assertSame($spec['expectedHeading'], $table->getEmptyStateHeading(), "Heading mismatch on {$name}");
            $this->assertNotEmpty($table->getEmptyStateDescription(), "Empty description on {$name}");
            $this->assertSame($spec['expectedIcon'], $table->getEmptyStateIcon(), "Icon mismatch on {$name}");

            // For resources that have 0 records, verify empty state renders in HTML
            // Note: UsersTable is not empty because the logged-in owner is a user of the tenant.
            if ($name !== 'Users') {
                $html = $test->html();
                $this->assertStringContainsString($spec['expectedHeading'], $html, "Rendered HTML missing heading for {$name}");
            }
        }

        // Test the global Restaurante records in the panel's tenant route context.
        $superadmin = $this->crearUsuario('superadmin');
        // Delete any restaurants so table is strictly empty
        Restaurante::query()->delete();

        $this->actingAs($superadmin);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($tenant);

        $testRestaurantes = Livewire::test(ListRestaurantes::class);
        $testRestaurantes->assertStatus(200);

        $tableRestaurantes = $testRestaurantes->instance()->getTable();
        $this->assertSame('No hay restaurantes registrados', $tableRestaurantes->getEmptyStateHeading());
        $this->assertNotEmpty($tableRestaurantes->getEmptyStateDescription());
        $this->assertSame('heroicon-o-building-storefront', $tableRestaurantes->getEmptyStateIcon());

        $htmlRestaurantes = $testRestaurantes->html();
        $this->assertStringContainsString('No hay restaurantes registrados', $htmlRestaurantes);
    }

    public function test_cross_tenant_isolation_in_empty_states_tenant_a_empty_while_tenant_b_has_data(): void
    {
        $tenantA = $this->crearRestaurante('tenant-vacio');
        $tenantB = $this->crearRestaurante('tenant-poblado');

        // Populate Tenant B
        $catB = Categoria::factory()->create(['restaurante_id' => $tenantB->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $catB->id, 'idioma' => 'es', 'nombre' => 'Exclusivo B']);

        $prodB = Producto::factory()->create(['restaurante_id' => $tenantB->id, 'categoria_id' => $catB->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $prodB->id, 'idioma' => 'es', 'nombre' => 'Platillo B']);

        Horario::factory()->create(['restaurante_id' => $tenantB->id]);
        Contacto::factory()->create(['restaurante_id' => $tenantB->id]);
        CodigoQr::factory()->create(['restaurante_id' => $tenantB->id]);

        // Enter Tenant A (has 0 records of anything)
        $ownerA = $this->crearUsuario('propietario', $tenantA);
        $this->entrarAlPanel($ownerA, $tenantA);

        // CategoriasTable in Tenant A MUST display empty state and NOT show Tenant B records
        $testCat = Livewire::test(ListCategorias::class);
        $testCat->assertStatus(200);
        $testCat->assertSee('Todavía no tienes categorías');
        $testCat->assertDontSee('Exclusivo B');

        // ProductosTable in Tenant A MUST display empty state and NOT show Tenant B records
        $testProd = Livewire::test(ListProductos::class);
        $testProd->assertStatus(200);
        $testProd->assertSee('Todavía no tienes platillos');
        $testProd->assertDontSee('Platillo B');

        // HorariosTable in Tenant A MUST display empty state
        $testHorarios = Livewire::test(ListHorarios::class);
        $testHorarios->assertStatus(200);
        $testHorarios->assertSee('Sin horarios de atención configurados');

        // CodigosQrTable in Tenant A MUST display empty state
        $testQr = Livewire::test(ListCodigosQr::class);
        $testQr->assertStatus(200);
        $testQr->assertSee('Sin códigos QR generados');
    }

    // =========================================================================
    // Area 3: Translated Search in ProductosTable with Accented & Special Chars
    // =========================================================================

    public function test_productos_table_search_with_accents_and_special_characters(): void
    {
        $tenant = $this->crearRestaurante('restaurante-degustacion');
        $cat = Categoria::factory()->create(['restaurante_id' => $tenant->id]);
        CategoriaTraduccion::factory()->create(['categoria_id' => $cat->id, 'idioma' => 'es', 'nombre' => 'General']);

        $owner = $this->crearUsuario('propietario', $tenant);
        $this->entrarAlPanel($owner, $tenant);

        // Seed diverse products with accents, special punctuation, numbers, and symbols
        $productosTestData = [
            'p_tradicion' => ['nombre' => 'Tacos al Pastor Tradición', 'precio' => '85.00'],
            'p_cafe' => ['nombre' => 'Café Espresso Doble', 'precio' => '45.00'],
            'p_jalapeno' => ['nombre' => 'Chiles Jalapeños Rellenos', 'precio' => '95.00'],
            'p_nino' => ['nombre' => 'Menú Infantil Niño Feliz', 'precio' => '70.00'],
            'p_guero' => ['nombre' => 'Chile Güero Gratinado', 'precio' => '65.00'],
            'p_porciento' => ['nombre' => 'Hamburguesa 100% Angus', 'precio' => '150.00'],
            'p_comillas' => ['nombre' => "Plato del Chef 'Especial'", 'precio' => '220.00'],
            'p_ampersand' => ['nombre' => 'Pescado & Mariscos Frescos', 'precio' => '180.00'],
            'p_parentesis' => ['nombre' => 'Ensalada Mixta (Para Compartir)', 'precio' => '110.00'],
            'p_guion_bajo' => ['nombre' => 'Combo_Promocion_Noche', 'precio' => '130.00'],
            'p_corchetes' => ['nombre' => 'Corte Tomahawk [1kg]', 'precio' => '650.00'],
        ];

        $records = [];
        foreach ($productosTestData as $key => $data) {
            $prod = Producto::factory()->create([
                'restaurante_id' => $tenant->id,
                'categoria_id' => $cat->id,
                'precio' => $data['precio'],
            ]);
            ProductoTraduccion::factory()->create([
                'producto_id' => $prod->id,
                'idioma' => 'es',
                'nombre' => $data['nombre'],
            ]);
            $records[$key] = $prod;
        }

        // 1. Accented Search: "Tradición"
        Livewire::test(ListProductos::class)
            ->searchTable('Tradición')
            ->assertCanSeeTableRecords([$records['p_tradicion']])
            ->assertCanNotSeeTableRecords([$records['p_cafe'], $records['p_jalapeno']]);

        // 2. Accented Search: "Café"
        Livewire::test(ListProductos::class)
            ->searchTable('Café')
            ->assertCanSeeTableRecords([$records['p_cafe']])
            ->assertCanNotSeeTableRecords([$records['p_tradicion']]);

        // 3. Accented Search: "Jalapeños"
        Livewire::test(ListProductos::class)
            ->searchTable('Jalapeños')
            ->assertCanSeeTableRecords([$records['p_jalapeno']])
            ->assertCanNotSeeTableRecords([$records['p_nino'], $records['p_guero']]);

        // 4. Letter with tilde: "Niño"
        Livewire::test(ListProductos::class)
            ->searchTable('Niño')
            ->assertCanSeeTableRecords([$records['p_nino']])
            ->assertCanNotSeeTableRecords([$records['p_jalapeno']]);

        // 5. Letter with diaeresis: "Güero"
        Livewire::test(ListProductos::class)
            ->searchTable('Güero')
            ->assertCanSeeTableRecords([$records['p_guero']])
            ->assertCanNotSeeTableRecords([$records['p_nino']]);

        // 6. Percent Symbol: "100%"
        Livewire::test(ListProductos::class)
            ->searchTable('100%')
            ->assertCanSeeTableRecords([$records['p_porciento']])
            ->assertCanNotSeeTableRecords([$records['p_cafe']]);

        // 7. Single quote: "'Especial'"
        Livewire::test(ListProductos::class)
            ->searchTable("'Especial'")
            ->assertCanSeeTableRecords([$records['p_comillas']])
            ->assertCanNotSeeTableRecords([$records['p_cafe']]);

        // 8. Ampersand: "& Mariscos"
        Livewire::test(ListProductos::class)
            ->searchTable('& Mariscos')
            ->assertCanSeeTableRecords([$records['p_ampersand']])
            ->assertCanNotSeeTableRecords([$records['p_cafe']]);

        // 9. Parentheses: "(Para Compartir)"
        Livewire::test(ListProductos::class)
            ->searchTable('(Para Compartir)')
            ->assertCanSeeTableRecords([$records['p_parentesis']])
            ->assertCanNotSeeTableRecords([$records['p_cafe']]);

        // 10. Underscore: "Combo_Promocion"
        Livewire::test(ListProductos::class)
            ->searchTable('Combo_Promocion')
            ->assertCanSeeTableRecords([$records['p_guion_bajo']])
            ->assertCanNotSeeTableRecords([$records['p_cafe']]);

        // 11. Brackets: "[1kg]"
        Livewire::test(ListProductos::class)
            ->searchTable('[1kg]')
            ->assertCanSeeTableRecords([$records['p_corchetes']])
            ->assertCanNotSeeTableRecords([$records['p_cafe']]);

        // 12. Adversarial SQL Injection String: "'; DROP TABLE productos; --"
        // Must NOT crash, must NOT drop table, must return 0 records
        Livewire::test(ListProductos::class)
            ->searchTable("'; DROP TABLE productos; --")
            ->assertCanNotSeeTableRecords(array_values($records))
            ->assertStatus(200);

        $this->assertTrue(Schema::hasTable('productos'), 'Table productos was dropped!');

        // 13. Non-existent search returns empty table without error
        Livewire::test(ListProductos::class)
            ->searchTable('PalabraInexistenteQueNoMatcheaNada12345')
            ->assertCanNotSeeTableRecords(array_values($records))
            ->assertStatus(200);

        // 14. Very long search query (500 chars)
        Livewire::test(ListProductos::class)
            ->searchTable(str_repeat('A', 500))
            ->assertCanNotSeeTableRecords(array_values($records))
            ->assertStatus(200);
    }

    public function test_productos_table_search_strictly_respects_cross_tenant_isolation(): void
    {
        $tenantA = $this->crearRestaurante('tenant-search-a');
        $tenantB = $this->crearRestaurante('tenant-search-b');

        $catA = Categoria::factory()->create(['restaurante_id' => $tenantA->id]);
        $catB = Categoria::factory()->create(['restaurante_id' => $tenantB->id]);

        $prodA = Producto::factory()->create(['restaurante_id' => $tenantA->id, 'categoria_id' => $catA->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $prodA->id, 'idioma' => 'es', 'nombre' => 'Enchiladas Verdes Mexicanas']);

        $prodB = Producto::factory()->create(['restaurante_id' => $tenantB->id, 'categoria_id' => $catB->id]);
        ProductoTraduccion::factory()->create(['producto_id' => $prodB->id, 'idioma' => 'es', 'nombre' => 'Enchiladas Suizas Mexicanas']);

        // Log into Tenant A
        $ownerA = $this->crearUsuario('propietario', $tenantA);
        $this->entrarAlPanel($ownerA, $tenantA);

        // Search common term "Mexicanas"
        Livewire::test(ListProductos::class)
            ->searchTable('Mexicanas')
            ->assertCanSeeTableRecords([$prodA])
            ->assertCanNotSeeTableRecords([$prodB]);

        // Search term unique to B: "Suizas"
        Livewire::test(ListProductos::class)
            ->searchTable('Suizas')
            ->assertCanNotSeeTableRecords([$prodA, $prodB]);
    }
}
