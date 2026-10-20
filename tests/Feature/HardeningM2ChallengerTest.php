<?php

namespace Tests\Feature;

use App\Enums\TipoCodigoAcceso;
use App\Filament\Resources\CodigosQr\Pages\ListCodigosQr;
use App\Http\Middleware\ConfiarEnProxiesConfigurados;
use App\Models\CodigoQr;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use stdClass;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Adversarial Challenger suite for Milestone 2:
 * 1. ConfiarEnProxiesConfigurados static state clearing.
 * 2. TRUSTED_PROXIES universal CIDR rejection in config/menuonl.php.
 * 3. ListCodigosQr batch generation (single existence query, atomicity in DB transaction, unique codes).
 * 4. Cache serialization with allowed_classes (Carbon, CarbonImmutable, stdClass vs unwhitelisted gadgets).
 */
class HardeningM2ChallengerTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    protected function tearDown(): void
    {
        // Reset static Request trusted proxies to prevent test cross-contamination
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PREFIX | Request::HEADER_X_FORWARDED_AWS_ELB);
        RestauranteScope::setRestauranteId(null);

        parent::tearDown();
    }

    // =========================================================================
    // Area 1: Network & Proxy Hardening (ConfiarEnProxiesConfigurados)
    // =========================================================================

    public function test_confiar_en_proxies_clears_static_state_when_proxies_config_is_empty(): void
    {
        // 1. Artificially pollute static Symfony/Laravel Request state as if a prior request trusted 1.2.3.4
        Request::setTrustedProxies(['1.2.3.4'], Request::HEADER_X_FORWARDED_FOR);
        $this->assertSame(['1.2.3.4'], Request::getTrustedProxies());

        // 2. Configure empty proxies list (menuonl.trusted_proxies = [])
        config(['menuonl.trusted_proxies' => []]);

        // 3. Execute middleware
        $middleware = new ConfiarEnProxiesConfigurados;
        $request = Request::create('/_probe', 'GET');
        $executed = false;

        $response = $middleware->handle($request, function ($req) use (&$executed) {
            $executed = true;

            return response('OK');
        });

        $this->assertTrue($executed);
        $this->assertSame('OK', $response->getContent());

        // 4. Verify static state is strictly cleared to empty array
        $this->assertSame([], Request::getTrustedProxies(), 'Static Request::$trustedProxies must be reset to [] when config is empty');

        // 5. Verify that in an actual request dispatched through the app, spoofed X-Forwarded-For is ignored
        Route::get('/_test_proxy_reset', fn (Request $req) => $req->ip());

        $res = $this->withServerVariables(['REMOTE_ADDR' => '1.2.3.4'])
            ->withHeaders(['X-Forwarded-For' => '9.9.9.9'])
            ->get('/_test_proxy_reset');

        $this->assertSame('1.2.3.4', $res->getContent(), 'Remote address must NOT be replaced with spoofed X-Forwarded-For when proxies is empty');
    }

    public function test_confiar_en_proxies_properly_sets_specified_proxies(): void
    {
        config(['menuonl.trusted_proxies' => ['10.0.0.1']]);

        $middleware = new ConfiarEnProxiesConfigurados;
        $request = Request::create('/_probe_set', 'GET');

        $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertSame(['10.0.0.1'], Request::getTrustedProxies());
    }

    // =========================================================================
    // Area 2: TRUSTED_PROXIES Environment Filtering (config/menuonl.php)
    // =========================================================================

    public function test_trusted_proxies_env_filtering_universal_subnets_and_malformed_inputs(): void
    {
        $evaluarConfig = function (string $envVal): array {
            putenv('TRUSTED_PROXIES='.$envVal);
            $_ENV['TRUSTED_PROXIES'] = $envVal;

            return (require config_path('menuonl.php'))['trusted_proxies'];
        };

        try {
            // Specified challenge: TRUSTED_PROXIES=0.0.0.0/0,::/0,1.2.3.4 strictly results in ['1.2.3.4']
            $resultado = $evaluarConfig('0.0.0.0/0,::/0,1.2.3.4');
            $this->assertSame(['1.2.3.4'], $resultado, 'Universal subnets 0.0.0.0/0 and ::/0 must be rejected, retaining only valid 1.2.3.4');

            // Adversarial variations of universal/wildcard routes
            $this->assertSame([], $evaluarConfig('0/0'));
            $this->assertSame([], $evaluarConfig('*'));
            $this->assertSame([], $evaluarConfig('**'));
            $this->assertSame([], $evaluarConfig('*, **, 0.0.0.0/0, ::/0, 0/0'));

            // Malformed and out-of-range CIDRs
            $this->assertSame([], $evaluarConfig('1.2.3.4/0'));    // mask <= 0
            $this->assertSame([], $evaluarConfig('1.2.3.4/33'));   // IPv4 mask > 32
            $this->assertSame([], $evaluarConfig('1.2.3.4/abc'));  // Non-numeric mask
            $this->assertSame([], $evaluarConfig('2001:db8::/129')); // IPv6 mask > 128
            $this->assertSame([], $evaluarConfig('999.999.999.999/24')); // Invalid IP subnet
            $this->assertSame([], $evaluarConfig('not-an-ip'));
            $this->assertSame([], $evaluarConfig('   ,   ,  '));

            // Legitimate mixed inputs with spaces
            $legitimos = $evaluarConfig(' 10.0.0.0/8 , 192.168.1.1, 2606:4700::/32 ');
            $this->assertSame(['10.0.0.0/8', '192.168.1.1', '2606:4700::/32'], $legitimos);
        } finally {
            putenv('TRUSTED_PROXIES');
            unset($_ENV['TRUSTED_PROXIES']);
        }
    }

    // =========================================================================
    // Area 3: Batch Table Generation Hardening (ListCodigosQr)
    // =========================================================================

    public function test_batch_table_generation_executes_single_batch_existence_query(): void
    {
        $restaurante = $this->crearRestaurante('trattoria-roma');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        // Pre-create some tables (e.g. 2, 5, 9)
        CodigoQr::factory()->deMesa('2')->create(['restaurante_id' => $restaurante->id]);
        CodigoQr::factory()->deMesa('5')->create(['restaurante_id' => $restaurante->id]);
        CodigoQr::factory()->deMesa('9')->create(['restaurante_id' => $restaurante->id]);

        $this->entrarAlPanel($usuario, $restaurante);

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 1,
                'hasta_mesa' => 10,
                'tipo' => TipoCodigoAcceso::Qr->value,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Entrecomillado del dialecto activo ("mesa" en SQLite, `mesa` en MariaDB).
        $columnaMesa = strtolower(DB::connection()->getQueryGrammar()->wrap('mesa'));

        // Find the batch existence query checking pre-existing tables
        $batchExistenceQueries = array_filter($queries, function ($q) use ($columnaMesa) {
            $sql = strtolower($q['query']);

            return str_contains($sql, 'select')
                && str_contains($sql, $columnaMesa)
                && str_contains($sql, 'in (');
        });

        // Verify there is exactly ONE batch existence check query
        $this->assertCount(
            1,
            $batchExistenceQueries,
            'Batch table generation must execute exactly ONE batch existence check query using whereIn'
        );

        // Verify there are ZERO individual N+1 table existence checks
        $individualExistenceQueries = array_filter($queries, function ($q) use ($columnaMesa) {
            $sql = strtolower($q['query']);

            return str_contains($sql, 'select')
                && str_contains($sql, 'codigos_qr')
                && (str_contains($sql, $columnaMesa.' =') || str_contains($sql, 'mesa ='));
        });

        $this->assertCount(
            0,
            $individualExistenceQueries,
            'Batch table generation must NOT execute individual table SELECT queries (N+1)'
        );

        // Verify that tables were generated
        $this->assertDatabaseCount('codigos_qr', 10);
        $this->assertDatabaseHas('codigos_qr', ['restaurante_id' => $restaurante->id, 'mesa' => '1']);
        $this->assertDatabaseHas('codigos_qr', ['restaurante_id' => $restaurante->id, 'mesa' => '10']);
    }

    public function test_batch_table_generation_rolls_back_atomically_within_db_transaction_on_failure(): void
    {
        $restaurante = $this->crearRestaurante('atomicity-bistro');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $this->entrarAlPanel($usuario, $restaurante);

        $this->assertDatabaseCount('codigos_qr', 0);

        // Hook into CodigoQr creating event to simulate an unexpected failure mid-batch (e.g. at table 4)
        $simulatedFailureHit = false;
        CodigoQr::creating(function (CodigoQr $qr) use (&$simulatedFailureHit) {
            if ($qr->mesa === '4') {
                $simulatedFailureHit = true;
                throw new \RuntimeException('Simulated catastrophic database interruption during batch table 4');
            }
        });

        try {
            Livewire::test(ListCodigosQr::class)
                ->callAction('generarLoteMesas', [
                    'desde_mesa' => 1,
                    'hasta_mesa' => 6,
                    'tipo' => TipoCodigoAcceso::Qr->value,
                ]);

            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Simulated catastrophic database interruption', $e->getMessage());
        } finally {
            // Clear the creating event listener
            CodigoQr::flushEventListeners();
        }

        $this->assertTrue($simulatedFailureHit, 'Creating listener should have been triggered');

        // Due to DB::transaction, tables 1, 2, 3 must have been completely rolled back!
        $this->assertSame(
            0,
            CodigoQr::withoutGlobalScopes()->where('restaurante_id', $restaurante->id)->count(),
            'All records must be rolled back within DB::transaction on any mid-batch failure'
        );
        $this->assertDatabaseCount('codigos_qr', 0);
    }

    public function test_batch_table_generation_generates_cryptographically_unique_8char_codes(): void
    {
        $restaurante = $this->crearRestaurante('uniqueness-steakhouse');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $this->entrarAlPanel($usuario, $restaurante);

        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 1,
                'hasta_mesa' => 30,
                'tipo' => TipoCodigoAcceso::Qr->value,
            ])
            ->assertHasNoActionErrors();

        $qrs = CodigoQr::where('restaurante_id', $restaurante->id)->get();
        $this->assertCount(30, $qrs);

        $codes = $qrs->pluck('codigo')->all();
        $uniqueCodes = array_unique($codes);

        $this->assertCount(30, $uniqueCodes, 'Every generated QR code in the batch must be unique');

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $code);
        }
    }

    public function test_batch_table_generation_rejects_exceeded_batch_size_and_null_tenant(): void
    {
        $restaurante = $this->crearRestaurante('limit-diner');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $this->entrarAlPanel($usuario, $restaurante);

        // Batch size > 250 (e.g. 1 to 252 -> diff 251)
        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 1,
                'hasta_mesa' => 252,
                'tipo' => TipoCodigoAcceso::Qr->value,
            ])
            ->assertNotified('El tamaño máximo por lote es de 250 mesas a la vez.');

        $this->assertDatabaseCount('codigos_qr', 0);
    }

    // =========================================================================
    // Area 4: Cache Serialization & allowed_classes Security
    // =========================================================================

    public function test_cache_serializable_classes_configuration_includes_carbon_and_stdclass(): void
    {
        $serializableClasses = config('cache.serializable_classes');

        $this->assertIsArray($serializableClasses);
        $this->assertContains(Carbon::class, $serializableClasses);
        $this->assertContains(CarbonImmutable::class, $serializableClasses);
        $this->assertContains(stdClass::class, $serializableClasses);
    }

    public function test_carbon_and_stdclass_can_be_serialized_and_deserialized_in_cache(): void
    {
        $now = Carbon::parse('2026-10-18 12:34:56');
        $immutableNow = CarbonImmutable::parse('2026-10-18 12:34:56');

        $std = new stdClass;
        $std->title = 'Test Object';
        $std->timestamp = $now;
        $std->immutable = $immutableNow;
        $std->nested = (object) ['key' => 'value'];

        // Test with file cache store which serializes objects to disk
        Cache::store('file')->put('test_carbon_key', $now, 60);
        Cache::store('file')->put('test_immutable_key', $immutableNow, 60);
        Cache::store('file')->put('test_stdclass_key', $std, 60);

        $retrievedCarbon = Cache::store('file')->get('test_carbon_key');
        $retrievedImmutable = Cache::store('file')->get('test_immutable_key');
        $retrievedStd = Cache::store('file')->get('test_stdclass_key');

        $this->assertInstanceOf(Carbon::class, $retrievedCarbon);
        $this->assertSame('2026-10-18 12:34:56', $retrievedCarbon->format('Y-m-d H:i:s'));

        $this->assertInstanceOf(CarbonImmutable::class, $retrievedImmutable);
        $this->assertSame('2026-10-18 12:34:56', $retrievedImmutable->format('Y-m-d H:i:s'));

        $this->assertInstanceOf(stdClass::class, $retrievedStd);
        $this->assertSame('Test Object', $retrievedStd->title);
        $this->assertInstanceOf(Carbon::class, $retrievedStd->timestamp);
        $this->assertInstanceOf(CarbonImmutable::class, $retrievedStd->immutable);
        $this->assertSame('value', $retrievedStd->nested->key);

        Cache::store('file')->forget('test_carbon_key');
        Cache::store('file')->forget('test_immutable_key');
        Cache::store('file')->forget('test_stdclass_key');
    }

    public function test_adversarial_unwhitelisted_class_is_blocked_by_allowed_classes(): void
    {
        // Construct an object of a class that is NOT in config('cache.serializable_classes')
        // e.g. User or a mock/exploit gadget dummy class
        $unwhitelistedObject = new User(['name' => 'Adversarial Gadget', 'email' => 'hacker@evil.com']);

        $serialized = serialize($unwhitelistedObject);

        $allowedClasses = config('cache.serializable_classes');

        // Unserialize with allowed_classes
        $unserialized = unserialize($serialized, ['allowed_classes' => $allowedClasses]);

        // When not in allowed_classes, PHP turns the object into __PHP_Incomplete_Class
        $this->assertInstanceOf(
            \__PHP_Incomplete_Class::class,
            $unserialized,
            'Classes not present in cache.serializable_classes must deserialize as __PHP_Incomplete_Class to block gadget chains'
        );
    }
}
