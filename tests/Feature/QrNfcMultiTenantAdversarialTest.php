<?php

namespace Tests\Feature;

use App\Enums\TipoCodigoAcceso;
use App\Filament\Resources\CodigosQr\Pages\CreateCodigoQr;
use App\Filament\Resources\CodigosQr\Pages\EditCodigoQr;
use App\Filament\Resources\CodigosQr\Pages\ListCodigosQr;
use App\Models\CodigoQr;
use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

class QrNfcMultiTenantAdversarialTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private Restaurante $tenantA;

    private Restaurante $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();

        $this->tenantA = $this->crearRestaurante('tenant-alfa');
        $this->tenantB = $this->crearRestaurante('tenant-beta');
    }

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);

        parent::tearDown();
    }

    public function test_usuario_de_tenant_a_no_puede_descargar_svg_png_ni_pdf_de_qr_o_nfc_de_tenant_b(): void
    {
        $propietarioA = $this->crearUsuario('propietario', $this->tenantA);
        $editorA = $this->crearUsuario('editor', $this->tenantA);

        $qrAjeno = CodigoQr::factory()->create([
            'restaurante_id' => $this->tenantB->id,
            'codigo' => 'qrajeno1',
            'tipo' => TipoCodigoAcceso::Qr,
            'activo' => true,
        ]);

        $nfcAjeno = CodigoQr::factory()->nfc()->create([
            'restaurante_id' => $this->tenantB->id,
            'codigo' => 'nfcajeno',
            'activo' => true,
        ]);

        foreach ([$propietarioA, $editorA] as $usuario) {
            foreach ([$qrAjeno, $nfcAjeno] as $codigoAjeno) {
                foreach (['svg', 'png', 'pdf'] as $formato) {
                    $this->actingAs($usuario)
                        ->get(route('codigos-qr.descargar', [
                            'codigoQr' => $codigoAjeno,
                            'formato' => $formato,
                        ]))
                        ->assertForbidden();
                }
            }
        }
    }

    public function test_descarga_con_contexto_filament_activo_en_tenant_a_bloquea_codigo_de_tenant_b_incluso_si_usuario_pertenece_a_ambos(): void
    {
        $usuarioMultitenant = $this->crearUsuario('propietario', $this->tenantA);
        $this->tenantB->usuarios()->attach($usuarioMultitenant->id, ['rol' => 'propietario']);

        $nfcTenantB = CodigoQr::factory()->nfc()->create([
            'restaurante_id' => $this->tenantB->id,
            'codigo' => 'nfcb0001',
        ]);

        // 1. Con tenant activo en Filament (sin global scope), QrDownloadController bloquea con 403
        Filament::setTenant($this->tenantA, isQuiet: true);
        RestauranteScope::setRestauranteId(null);

        foreach (['svg', 'png', 'pdf'] as $formato) {
            $this->actingAs($usuarioMultitenant)
                ->get(route('codigos-qr.descargar', [
                    'codigoQr' => $nfcTenantB,
                    'formato' => $formato,
                ]))
                ->assertForbidden();
        }

        // 2. Con RestauranteScope activo en Tenant A, el route model binding ni siquiera expone el ID (404)
        $this->entrarAlPanel($usuarioMultitenant, $this->tenantA);

        foreach (['svg', 'png', 'pdf'] as $formato) {
            $this->actingAs($usuarioMultitenant)
                ->get("/admin/codigos-qr/{$nfcTenantB->id}/descargar/{$formato}")
                ->assertNotFound();
        }
    }

    public function test_accion_generar_lote_mesas_ignora_intento_de_inyectar_restaurante_id_ajeno(): void
    {
        $propietarioA = $this->crearUsuario('propietario', $this->tenantA);
        $this->entrarAlPanel($propietarioA, $this->tenantA);

        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 1,
                'hasta_mesa' => 5,
                'tipo' => TipoCodigoAcceso::Nfc->value,
                'restaurante_id' => $this->tenantB->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(
            5,
            CodigoQr::withoutGlobalScopes()->where('restaurante_id', $this->tenantA->id)->count()
        );
        $this->assertSame(
            0,
            CodigoQr::withoutGlobalScopes()->where('restaurante_id', $this->tenantB->id)->count(),
            'Ningún código debe crearse en el tenant B aunque se inyecte restaurante_id en el payload de la acción.'
        );
    }

    public function test_formulario_crear_codigo_qr_nfc_ignora_restaurante_id_manipulado_en_livewire(): void
    {
        $propietarioA = $this->crearUsuario('propietario', $this->tenantA);
        $this->entrarAlPanel($propietarioA, $this->tenantA);

        Livewire::test(CreateCodigoQr::class)
            ->fillForm([
                'nombre' => 'NFC Manipulado',
                'mesa' => '99',
                'tipo' => TipoCodigoAcceso::Nfc->value,
                'codigo' => 'nfcsec99',
                'restaurante_id' => $this->tenantB->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $creado = CodigoQr::withoutGlobalScopes()->where('codigo', 'nfcsec99')->firstOrFail();
        $this->assertSame($this->tenantA->id, (int) $creado->restaurante_id);
        $this->assertNotSame($this->tenantB->id, (int) $creado->restaurante_id);
    }

    public function test_lotes_de_mesas_entre_tenants_no_interfieren_entre_si_y_mantienen_unicidad_global(): void
    {
        $propietarioA = $this->crearUsuario('propietario', $this->tenantA);
        $propietarioB = $this->crearUsuario('propietario', $this->tenantB);

        // Tenant A genera mesas 1..5 como NFC
        $this->entrarAlPanel($propietarioA, $this->tenantA);
        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 1,
                'hasta_mesa' => 5,
                'tipo' => TipoCodigoAcceso::Nfc->value,
            ])
            ->assertHasNoActionErrors();

        // Tenant B genera las mismas mesas 1..5 como QR: no deben omitirse por existir en Tenant A
        $this->entrarAlPanel($propietarioB, $this->tenantB);
        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 1,
                'hasta_mesa' => 5,
                'tipo' => TipoCodigoAcceso::Qr->value,
            ])
            ->assertHasNoActionErrors();

        $codigosA = CodigoQr::withoutGlobalScopes()->where('restaurante_id', $this->tenantA->id)->get();
        $codigosB = CodigoQr::withoutGlobalScopes()->where('restaurante_id', $this->tenantB->id)->get();

        $this->assertCount(5, $codigosA);
        $this->assertCount(5, $codigosB);

        $todosLosTokens = $codigosA->pluck('codigo')->merge($codigosB->pluck('codigo'))->all();
        $this->assertCount(10, array_unique($todosLosTokens));
    }

    public function test_intento_de_editar_o_ver_codigo_ajeno_en_filament_devuelve_404(): void
    {
        $propietarioA = $this->crearUsuario('propietario', $this->tenantA);
        $qrAjeno = CodigoQr::factory()->create([
            'restaurante_id' => $this->tenantB->id,
            'nombre' => 'Mesa VIP Beta',
            'codigo' => 'vipbeta1',
        ]);

        $this->entrarAlPanel($propietarioA, $this->tenantA);

        Livewire::test(EditCodigoQr::class, ['record' => $qrAjeno->getRouteKey()])
            ->assertNotFound();

        $this->assertDatabaseHas('codigos_qr', [
            'id' => $qrAjeno->id,
            'restaurante_id' => $this->tenantB->id,
            'nombre' => 'Mesa VIP Beta',
        ]);
    }
}
