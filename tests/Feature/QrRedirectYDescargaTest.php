<?php

namespace Tests\Feature;

use App\Filament\Resources\CodigosQr\Pages\ListCodigosQr;
use App\Models\CodigoQr;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

class QrRedirectYDescargaTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);

        parent::tearDown();
    }

    // =========================================================================
    // Tier 1: Feature Coverage (Core Behavior)
    // =========================================================================

    /**
     * Table QR redirects to /{slug}/menu?mesa=N (302).
     */
    public function test_table_qr_redirects_to_public_menu_with_mesa_query(): void
    {
        $restaurante = Restaurante::factory()->create([
            'slug' => 'taqueria-el-fogon',
            'estado' => 'activo',
        ]);

        $qr = CodigoQr::factory()->deMesa('5')->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'fogon05',
            'activo' => true,
        ]);

        $response = $this->get(route('qr.redirect', ['codigo' => $qr->codigo]));

        $response->assertStatus(302);
        $response->assertRedirect(route('menu.publico', [
            'slug' => 'taqueria-el-fogon',
            'mesa' => '5',
        ]));
    }

    /**
     * The encoded QR URL comes from app.url, not from the request Host header.
     */
    public function test_downloaded_qr_url_uses_app_url_regardless_of_host_header(): void
    {
        config(['app.url' => 'https://menu.ejemplo.test']);

        $restaurante = $this->crearRestaurante('host-seguro');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $qr = CodigoQr::factory()->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'hostqr01',
            'activo' => true,
        ]);

        $response = $this->actingAs($usuario)
            ->call('GET', route('codigos-qr.descargar', ['codigoQr' => $qr, 'formato' => 'pdf'], false), [], [], [], ['HTTP_HOST' => 'evil.test']);

        $response->assertOk();
        $response->assertViewHas('url', 'https://menu.ejemplo.test/q/hostqr01');
        $response->assertDontSee('evil.test');
    }

    /**
     * General QR redirects to /{slug}/menu (302).
     */
    public function test_general_qr_redirects_to_public_menu_without_mesa_query(): void
    {
        $restaurante = Restaurante::factory()->create([
            'slug' => 'bistro-central',
            'estado' => 'activo',
        ]);

        $qr = CodigoQr::factory()->create([
            'restaurante_id' => $restaurante->id,
            'nombre' => 'General Entrada',
            'mesa' => null,
            'codigo' => 'bistro01',
            'activo' => true,
        ]);

        $response = $this->get(route('qr.redirect', ['codigo' => $qr->codigo]));

        $response->assertStatus(302);
        $response->assertRedirect(route('menu.publico', [
            'slug' => 'bistro-central',
        ]));
    }

    /**
     * Authenticated user can download SVG QR (status 200, Content-Type: image/svg+xml).
     */
    public function test_authenticated_user_can_download_svg_qr(): void
    {
        $restaurante = $this->crearRestaurante('la-antigua');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $qr = CodigoQr::factory()->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'svgtest01',
            'activo' => true,
        ]);

        $response = $this->actingAs($usuario)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qr, 'formato' => 'svg']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('attachment; filename="qr-svgtest01.svg"', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('<svg', $response->getContent());
        $this->assertStringContainsString('</svg>', $response->getContent());
    }

    /**
     * Authenticated user can download PNG QR (status 200, Content-Type: image/png, valid PNG header \x89PNG).
     */
    public function test_authenticated_user_can_download_png_qr(): void
    {
        $restaurante = $this->crearRestaurante('el-rinconcito');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $qr = CodigoQr::factory()->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'pngtest01',
            'activo' => true,
        ]);

        $response = $this->actingAs($usuario)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qr, 'formato' => 'png']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('attachment; filename="qr-pngtest01.png"', (string) $response->headers->get('Content-Disposition'));

        $content = $response->getContent();
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $content);
    }

    /**
     * Authenticated user can open printable sheet (status 200, contains SVG, restaurant name, table name/number).
     */
    public function test_authenticated_user_can_open_printable_sheet(): void
    {
        $restaurante = $this->crearRestaurante('pampa-grill');
        $restaurante->update(['nombre' => 'Pampa Grill & Bar']);
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $qr = CodigoQr::factory()->deMesa('12')->create([
            'restaurante_id' => $restaurante->id,
            'nombre' => 'Mesa Terraza 12',
            'mesa' => '12',
            'codigo' => 'pampa12',
            'activo' => true,
        ]);

        $response = $this->actingAs($usuario)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qr, 'formato' => 'pdf']));

        $response->assertOk();
        $response->assertViewIs('qr.imprimible');
        $response->assertSee('Pampa Grill & Bar');
        $response->assertSee('Mesa 12');
        $response->assertSee('<svg', false);
    }

    /**
     * Filament batch table creation creates multiple tables skipping already existing ones.
     */
    public function test_filament_batch_table_creation_creates_tables_and_skips_existing(): void
    {
        $restaurante = $this->crearRestaurante('trattoria-roma');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        // Pre-create mesa 2 so it should be skipped
        CodigoQr::factory()->deMesa('2')->create([
            'restaurante_id' => $restaurante->id,
            'nombre' => 'Mesa 2 Previa',
            'codigo' => 'existente02',
            'activo' => true,
        ]);

        $this->entrarAlPanel($usuario, $restaurante);

        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 1,
                'hasta_mesa' => 4,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        // Tables 1, 3, 4 should be newly created; table 2 should remain as pre-created
        $this->assertDatabaseCount('codigos_qr', 4);

        $this->assertDatabaseHas('codigos_qr', [
            'restaurante_id' => $restaurante->id,
            'mesa' => '1',
            'nombre' => 'Mesa 1',
            'activo' => true,
        ]);

        $this->assertDatabaseHas('codigos_qr', [
            'restaurante_id' => $restaurante->id,
            'mesa' => '2',
            'nombre' => 'Mesa 2 Previa',
            'codigo' => 'existente02',
        ]);

        $this->assertDatabaseHas('codigos_qr', [
            'restaurante_id' => $restaurante->id,
            'mesa' => '3',
            'nombre' => 'Mesa 3',
            'activo' => true,
        ]);

        $this->assertDatabaseHas('codigos_qr', [
            'restaurante_id' => $restaurante->id,
            'mesa' => '4',
            'nombre' => 'Mesa 4',
            'activo' => true,
        ]);
    }

    // =========================================================================
    // Tier 2: Boundary & Corner Cases
    // =========================================================================

    /**
     * Short code with invalid characters (special characters) returns 404 with view menu.no-disponible.
     */
    public function test_short_code_format_invalid_with_special_characters_returns_404(): void
    {
        $response = $this->get('/q/bad@code!');

        $response->assertNotFound();
        $response->assertViewIs('menu.no-disponible');
    }

    /**
     * Short code exceeding 16 characters returns 404 with view menu.no-disponible.
     */
    public function test_short_code_exceeding_16_characters_returns_404(): void
    {
        $response = $this->get('/q/12345678901234567');

        $response->assertNotFound();
        $response->assertViewIs('menu.no-disponible');
    }

    /**
     * Non-existent code returns 404 with view menu.no-disponible.
     */
    public function test_non_existent_code_returns_404(): void
    {
        $response = $this->get('/q/noexiste123');

        $response->assertNotFound();
        $response->assertViewIs('menu.no-disponible');
    }

    /**
     * Inactive QR (activo = false) returns 404 with view menu.no-disponible.
     */
    public function test_inactive_qr_returns_404(): void
    {
        $restaurante = Restaurante::factory()->create([
            'slug' => 'cafe-inactivo',
            'estado' => 'activo',
        ]);

        $qr = CodigoQr::factory()->inactivo()->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'desactivado',
        ]);

        $response = $this->get(route('qr.redirect', ['codigo' => $qr->codigo]));

        $response->assertNotFound();
        $response->assertViewIs('menu.no-disponible');
    }

    /**
     * QR belonging to suspended restaurant returns 404 with view menu.no-disponible.
     */
    public function test_restaurant_suspended_returns_404(): void
    {
        $restaurante = Restaurante::factory()->suspendido()->create([
            'slug' => 'restaurante-suspendido',
        ]);

        $qr = CodigoQr::factory()->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'suspendido01',
            'activo' => true,
        ]);

        $response = $this->get(route('qr.redirect', ['codigo' => $qr->codigo]));

        $response->assertNotFound();
        $response->assertViewIs('menu.no-disponible');
    }

    /**
     * QR belonging to soft-deleted restaurant returns 404 with view menu.no-disponible.
     */
    public function test_soft_deleted_restaurant_returns_404(): void
    {
        $restaurante = Restaurante::factory()->create([
            'slug' => 'restaurante-borrado',
            'estado' => 'activo',
        ]);

        $qr = CodigoQr::factory()->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'borrado01',
            'activo' => true,
        ]);

        $restaurante->delete();

        $response = $this->get(route('qr.redirect', ['codigo' => $qr->codigo]));

        $response->assertNotFound();
        $response->assertViewIs('menu.no-disponible');
    }

    /**
     * Unauthenticated user cannot access download route (redirects to login or 401/403).
     */
    public function test_unauthenticated_user_cannot_access_download_route(): void
    {
        $restaurante = Restaurante::factory()->create();
        $qr = CodigoQr::factory()->create(['restaurante_id' => $restaurante->id]);

        $responseWeb = $this->get(route('codigos-qr.descargar', ['codigoQr' => $qr, 'formato' => 'svg']));
        $responseWeb->assertRedirect('/admin/login');

        $responseJson = $this->getJson(route('codigos-qr.descargar', ['codigoQr' => $qr, 'formato' => 'svg']));
        $responseJson->assertUnauthorized();
    }

    /**
     * User from Restaurant A cannot download QR belonging to Restaurant B (403 Forbidden).
     */
    public function test_user_from_restaurant_a_cannot_download_qr_belonging_to_restaurant_b(): void
    {
        $restauranteA = $this->crearRestaurante('restaurante-a');
        $restauranteB = $this->crearRestaurante('restaurante-b');

        $usuarioA = $this->crearUsuario('propietario', $restauranteA);
        $qrB = CodigoQr::factory()->create(['restaurante_id' => $restauranteB->id]);

        $response = $this->actingAs($usuarioA)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qrB, 'formato' => 'svg']));

        $response->assertForbidden();
    }

    /**
     * Invalid download format (e.g. /descargar/exe) returns 404.
     */
    public function test_invalid_download_format_returns_404(): void
    {
        $restaurante = $this->crearRestaurante('restaurante-valido');
        $usuario = $this->crearUsuario('propietario', $restaurante);
        $qr = CodigoQr::factory()->create(['restaurante_id' => $restaurante->id]);

        $response = $this->actingAs($usuario)
            ->get("/admin/codigos-qr/{$qr->id}/descargar/exe");

        $response->assertNotFound();
    }

    /**
     * Batch table generation with hasta_mesa < desde_mesa is rejected.
     */
    public function test_batch_table_generation_with_hasta_mesa_less_than_desde_mesa_is_rejected(): void
    {
        $restaurante = $this->crearRestaurante('la-estacion');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $this->entrarAlPanel($usuario, $restaurante);

        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 5,
                'hasta_mesa' => 2,
            ])
            ->assertHasActionErrors(['hasta_mesa']);

        $this->assertDatabaseCount('codigos_qr', 0);
    }

    // =========================================================================
    // Tier 3: Cross-Feature Combinations
    // =========================================================================

    /**
     * Table QR with alphanumeric mesa identifiers (e.g. "Terraza-1", "VIP 2").
     */
    public function test_table_qr_with_alphanumeric_mesa_identifiers(): void
    {
        $restaurante = Restaurante::factory()->create([
            'slug' => 'bistro-del-mar',
            'estado' => 'activo',
        ]);

        $qrTerraza = CodigoQr::factory()->deMesa('Terraza-1')->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'terraza1',
            'activo' => true,
        ]);

        $qrVip = CodigoQr::factory()->deMesa('VIP 2')->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'vip02',
            'activo' => true,
        ]);

        $responseTerraza = $this->get(route('qr.redirect', ['codigo' => $qrTerraza->codigo]));
        $responseTerraza->assertStatus(302);
        $responseTerraza->assertRedirect(route('menu.publico', [
            'slug' => 'bistro-del-mar',
            'mesa' => 'Terraza-1',
        ]));

        $responseVip = $this->get(route('qr.redirect', ['codigo' => $qrVip->codigo]));
        $responseVip->assertStatus(302);
        $responseVip->assertRedirect(route('menu.publico', [
            'slug' => 'bistro-del-mar',
            'mesa' => 'VIP 2',
        ]));
    }

    /**
     * Multi-tenant isolation verified across multiple tenants.
     */
    public function test_multi_tenant_isolation_verified_across_multiple_tenants(): void
    {
        $restaurante1 = $this->crearRestaurante('tenant-uno');
        $restaurante2 = $this->crearRestaurante('tenant-dos');

        $user1 = $this->crearUsuario('editor', $restaurante1);
        $user2 = $this->crearUsuario('editor', $restaurante2);
        $superadmin = $this->crearUsuario('superadmin');

        $qr1 = CodigoQr::factory()->deMesa('1')->create([
            'restaurante_id' => $restaurante1->id,
            'codigo' => 't1mesa1',
            'activo' => true,
        ]);

        $qr2 = CodigoQr::factory()->deMesa('1')->create([
            'restaurante_id' => $restaurante2->id,
            'codigo' => 't2mesa1',
            'activo' => true,
        ]);

        // 1. Redirections are correctly tenant-specific
        $this->get('/q/t1mesa1')
            ->assertStatus(302)
            ->assertRedirect(route('menu.publico', ['slug' => 'tenant-uno', 'mesa' => '1']));

        $this->get('/q/t2mesa1')
            ->assertStatus(302)
            ->assertRedirect(route('menu.publico', ['slug' => 'tenant-dos', 'mesa' => '1']));

        // 2. Cross-tenant downloads are rejected for regular users
        $this->actingAs($user1)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qr1, 'formato' => 'svg']))
            ->assertOk();

        $this->actingAs($user1)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qr2, 'formato' => 'svg']))
            ->assertForbidden();

        $this->actingAs($user2)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qr2, 'formato' => 'svg']))
            ->assertOk();

        $this->actingAs($user2)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qr1, 'formato' => 'svg']))
            ->assertForbidden();

        // 3. Superadmin can download from any tenant
        $this->actingAs($superadmin)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qr1, 'formato' => 'svg']))
            ->assertOk();

        $this->actingAs($superadmin)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qr2, 'formato' => 'svg']))
            ->assertOk();
    }

    // =========================================================================
    // Tier 4: Real-World Workflow
    // =========================================================================

    /**
     * Full lifecycle: Create restaurant, create table QR, scan QR -> redirected to public menu with table query, download QR in SVG/PNG.
     */
    public function test_full_qr_lifecycle_creation_scan_redirection_and_download(): void
    {
        // 1. Create restaurant and owner user
        $restaurante = $this->crearRestaurante('hacienda-real');
        $restaurante->update(['nombre' => 'Hacienda Real Steakhouse']);
        $propietario = $this->crearUsuario('propietario', $restaurante);

        // 2. Owner enters panel and generates batch tables (1 to 3)
        $this->entrarAlPanel($propietario, $restaurante);

        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 1,
                'hasta_mesa' => 3,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseCount('codigos_qr', 3);

        $qrMesa2 = CodigoQr::where('restaurante_id', $restaurante->id)
            ->where('mesa', '2')
            ->firstOrFail();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{1,16}$/', $qrMesa2->codigo);

        // 3. Comensal scans QR for Table 2
        $scanResponse = $this->get(route('qr.redirect', ['codigo' => $qrMesa2->codigo]));
        $scanResponse->assertStatus(302);
        $scanResponse->assertRedirect(route('menu.publico', [
            'slug' => 'hacienda-real',
            'mesa' => '2',
        ]));

        // 4. Comensal arrives at public menu
        $publicMenuResponse = $this->get(route('menu.publico', [
            'slug' => 'hacienda-real',
            'mesa' => '2',
        ]));
        $publicMenuResponse->assertOk();
        $publicMenuResponse->assertSee('Hacienda Real Steakhouse');

        // 5. Owner downloads SVG for Table 2
        $svgResponse = $this->actingAs($propietario)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qrMesa2, 'formato' => 'svg']));
        $svgResponse->assertOk();
        $svgResponse->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $svgResponse->getContent());

        // 6. Owner downloads PNG for Table 2
        $pngResponse = $this->actingAs($propietario)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qrMesa2, 'formato' => 'png']));
        $pngResponse->assertOk();
        $pngResponse->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $pngResponse->getContent());

        // 7. Owner renders printable sheet for Table 2
        $pdfResponse = $this->actingAs($propietario)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qrMesa2, 'formato' => 'pdf']));
        $pdfResponse->assertOk();
        $pdfResponse->assertViewIs('qr.imprimible');
        $pdfResponse->assertSee('Hacienda Real Steakhouse');
        $pdfResponse->assertSee('Mesa 2');
    }
}
