<?php

namespace Tests\Feature;

use App\Models\HistorialCambio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * menuonl:crear-superadmin: el primer superadmin real, sin seeders ni tinker y sin que la contraseña salga a la vista.
 */
class CrearSuperadminTest extends TestCase
{
    use RefreshDatabase;

    private const string CONTRASENA = 'una-clave-larga-y-secreta';

    /**
     * @return array<string, array{0: string}>
     */
    public static function contrasenasCortas(): array
    {
        return [
            'vacía' => [''],
            'tres caracteres' => ['abc'],
            'once caracteres' => ['12345678901'],
            'once caracteres con acentos' => ['áéíóúñÁÉÍÓÚ'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function correosInvalidos(): array
    {
        return [
            'sin arroba' => ['correo-sin-arroba'],
            'sin dominio' => ['alguien@'],
            'sin usuario' => ['@ejemplo.com'],
            'con espacios' => ['alguien con espacios@ejemplo.com'],
            'vacío' => [''],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function correosDuplicados(): array
    {
        return [
            'igual' => ['existente@ejemplo.com'],
            'con otras mayúsculas' => ['Existente@Ejemplo.COM'],
            'con espacios alrededor' => ['  existente@ejemplo.com  '],
        ];
    }

    public function test_con_datos_validos_crea_el_superadmin_con_el_hash_de_la_aplicacion(): void
    {
        $this->artisan('menuonl:crear-superadmin')
            ->expectsQuestion('Correo del superadmin', 'Dueño@Ejemplo.com')
            ->expectsQuestion('Nombre completo', 'Ana Pérez')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, no se muestra)', self::CONTRASENA)
            ->expectsQuestion('Confirma la contraseña', self::CONTRASENA)
            ->expectsOutputToContain('Superadmin creado: dueño@ejemplo.com')
            ->doesntExpectOutputToContain(self::CONTRASENA)
            ->assertExitCode(0);

        $usuario = User::query()->where('email', 'dueño@ejemplo.com')->firstOrFail();

        $this->assertTrue($usuario->isSuperadmin());
        $this->assertTrue($usuario->activo);
        $this->assertSame('Ana Pérez', $usuario->name);
        $this->assertNotNull($usuario->email_verified_at);
        $this->assertNotSame(self::CONTRASENA, $usuario->password);
        $this->assertTrue(Hash::check(self::CONTRASENA, $usuario->password));
        $this->assertSame(Hash::info(Hash::make('otra'))['algoName'], Hash::info($usuario->password)['algoName']);
        $this->assertSame('argon2id', Hash::info($usuario->password)['algoName']);
    }

    public function test_la_contrasena_nunca_se_imprime_ni_se_registra(): void
    {
        Log::spy();

        $this->artisan('menuonl:crear-superadmin')
            ->expectsQuestion('Correo del superadmin', 'sin-eco@ejemplo.com')
            ->expectsQuestion('Nombre completo', 'Sin Eco')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, no se muestra)', self::CONTRASENA)
            ->expectsQuestion('Confirma la contraseña', self::CONTRASENA)
            ->doesntExpectOutputToContain(self::CONTRASENA)
            ->assertExitCode(0);

        Log::shouldHaveReceived('info')->withArgs(fn (string $mensaje, array $contexto = []): bool => str_contains($mensaje, 'menuonl:crear-superadmin')
            && ! str_contains(json_encode($contexto, JSON_UNESCAPED_UNICODE), self::CONTRASENA)
            && ! array_key_exists('password', $contexto))->once();
    }

    public function test_la_contrasena_no_se_puede_pasar_como_argumento_ni_opcion(): void
    {
        $definicion = Artisan::all()['menuonl:crear-superadmin']->getDefinition();

        $this->assertSame([], $definicion->getArguments());
        $this->assertSame(['confirmo-otro-superadmin'], array_keys(Arr::except($definicion->getOptions(), [
            'help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env',
        ])));
    }

    public function test_sin_terminal_interactiva_se_niega_a_correr(): void
    {
        $this->artisan('menuonl:crear-superadmin', ['--no-interaction' => true])
            ->expectsOutputToContain('Este comando es interactivo')
            ->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    #[DataProvider('contrasenasCortas')]
    public function test_una_contrasena_corta_se_rechaza_y_no_crea_nada(string $corta): void
    {
        $this->artisan('menuonl:crear-superadmin')
            ->expectsQuestion('Correo del superadmin', 'corta@ejemplo.com')
            ->expectsQuestion('Nombre completo', 'Clave Corta')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, no se muestra)', $corta)
            ->expectsOutputToContain('al menos 12 caracteres')
            ->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    public function test_doce_caracteres_exactos_alcanzan(): void
    {
        $doce = 'abcdefghijkl';

        $this->artisan('menuonl:crear-superadmin')
            ->expectsQuestion('Correo del superadmin', 'doce@ejemplo.com')
            ->expectsQuestion('Nombre completo', 'Doce')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, no se muestra)', $doce)
            ->expectsQuestion('Confirma la contraseña', $doce)
            ->assertExitCode(0);

        $this->assertTrue(User::query()->where('email', 'doce@ejemplo.com')->firstOrFail()->isSuperadmin());
    }

    public function test_si_la_confirmacion_no_coincide_no_crea_nada(): void
    {
        $this->artisan('menuonl:crear-superadmin')
            ->expectsQuestion('Correo del superadmin', 'distinta@ejemplo.com')
            ->expectsQuestion('Nombre completo', 'Distinta')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, no se muestra)', self::CONTRASENA)
            ->expectsQuestion('Confirma la contraseña', self::CONTRASENA.'x')
            ->expectsOutputToContain('no coinciden')
            ->doesntExpectOutputToContain(self::CONTRASENA)
            ->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    #[DataProvider('correosInvalidos')]
    public function test_un_correo_con_formato_invalido_se_rechaza(string $correo): void
    {
        $this->artisan('menuonl:crear-superadmin')
            ->expectsQuestion('Correo del superadmin', $correo)
            ->expectsQuestion('Nombre completo', 'Correo Malo')
            ->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    #[DataProvider('correosDuplicados')]
    public function test_un_correo_ya_registrado_se_rechaza_sin_importar_mayusculas_ni_espacios(string $correo): void
    {
        $existente = User::factory()->create(['email' => 'existente@ejemplo.com']);

        $this->artisan('menuonl:crear-superadmin')
            ->expectsQuestion('Correo del superadmin', $correo)
            ->expectsQuestion('Nombre completo', 'Duplicado')
            ->expectsOutputToContain('Ya existe una cuenta con ese correo')
            ->assertExitCode(1);

        $this->assertSame(1, User::query()->count());
        $this->assertFalse($existente->fresh()->isSuperadmin());
    }

    public function test_si_ya_existe_un_superadmin_se_niega_sin_preguntar_nada(): void
    {
        User::factory()->superadmin()->create();

        $this->artisan('menuonl:crear-superadmin')
            ->expectsOutputToContain('Ya existe un superadmin')
            ->assertExitCode(1);

        $this->assertSame(1, User::query()->count());
    }

    public function test_con_la_opcion_de_confirmacion_permite_crear_otro_superadmin(): void
    {
        User::factory()->superadmin()->create();

        $this->artisan('menuonl:crear-superadmin', ['--confirmo-otro-superadmin' => true])
            ->expectsQuestion('Correo del superadmin', 'segundo@ejemplo.com')
            ->expectsQuestion('Nombre completo', 'Segundo')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, no se muestra)', self::CONTRASENA)
            ->expectsQuestion('Confirma la contraseña', self::CONTRASENA)
            ->assertExitCode(0);

        $this->assertSame(2, User::query()->where('es_superadmin', true)->count());
    }

    public function test_un_usuario_normal_existente_no_cuenta_como_superadmin(): void
    {
        User::factory()->create();

        $this->artisan('menuonl:crear-superadmin')
            ->expectsQuestion('Correo del superadmin', 'primero@ejemplo.com')
            ->expectsQuestion('Nombre completo', 'Primero')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, no se muestra)', self::CONTRASENA)
            ->expectsQuestion('Confirma la contraseña', self::CONTRASENA)
            ->assertExitCode(0);

        $this->assertSame(1, User::query()->where('es_superadmin', true)->count());
    }

    public function test_funciona_en_produccion_donde_el_seeder_de_demostracion_no(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('menuonl:crear-superadmin')
            ->expectsQuestion('Correo del superadmin', 'produccion@ejemplo.com')
            ->expectsQuestion('Nombre completo', 'Producción')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, no se muestra)', self::CONTRASENA)
            ->expectsQuestion('Confirma la contraseña', self::CONTRASENA)
            ->assertExitCode(0);

        $this->assertTrue(User::query()->where('email', 'produccion@ejemplo.com')->firstOrFail()->isSuperadmin());
    }

    public function test_no_escribe_en_el_historial_porque_un_superadmin_no_pertenece_a_un_restaurante(): void
    {
        $this->artisan('menuonl:crear-superadmin')
            ->expectsQuestion('Correo del superadmin', 'historial@ejemplo.com')
            ->expectsQuestion('Nombre completo', 'Historial')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, no se muestra)', self::CONTRASENA)
            ->expectsQuestion('Confirma la contraseña', self::CONTRASENA)
            ->assertExitCode(0);

        $this->assertSame(0, HistorialCambio::withoutGlobalScopes()->count());
    }
}
