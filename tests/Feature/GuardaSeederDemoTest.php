<?php

namespace Tests\Feature;

use App\Models\Restaurante;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoRestauranteSeeder;
use Database\Seeders\EtiquetaSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * El seeder de demostración crea cuentas con contraseña conocida: solo puede correr en local y testing.
 */
class GuardaSeederDemoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function entornosDeProduccion(): array
    {
        return [
            'production' => ['production'],
            'staging' => ['staging'],
            'sin nombre conocido' => ['demo'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function entornosDeDesarrollo(): array
    {
        return [
            'local' => ['local'],
            'testing' => ['testing'],
        ];
    }

    private function enEntorno(string $entorno): void
    {
        $this->app->detectEnvironment(fn (): string => $entorno);
    }

    #[DataProvider('entornosDeProduccion')]
    public function test_el_seeder_aborta_fuera_de_local_y_testing_sin_crear_nada(string $entorno): void
    {
        $this->enEntorno($entorno);

        try {
            (new DemoRestauranteSeeder)->run();
            $this->fail('El seeder debió abortar.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('solo puede ejecutarse en los entornos local y testing', $e->getMessage());
            $this->assertStringContainsString($entorno, $e->getMessage());
            $this->assertStringContainsString('menuonl:crear-superadmin', $e->getMessage());
        }

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Restaurante::withTrashed()->count());
    }

    public function test_por_artisan_termina_con_codigo_distinto_de_cero_y_dice_por_que(): void
    {
        $this->enEntorno('production');

        $salida = new BufferedOutput;
        $codigo = $this->app->make(Kernel::class)->handle(
            new ArrayInput(['command' => 'db:seed', '--class' => DemoRestauranteSeeder::class, '--force' => true]),
            $salida,
        );

        $this->assertNotSame(0, $codigo);
        $this->assertStringContainsString('entorno actual: production', $salida->fetch());
        $this->assertSame(0, User::query()->where('email', 'like', '%@menuonl.test')->count());
    }

    public function test_el_seeder_general_en_produccion_no_crea_cuentas_de_prueba(): void
    {
        $this->enEntorno('production');

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertExitCode(0);

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Restaurante::withTrashed()->count());
    }

    #[DataProvider('entornosDeDesarrollo')]
    public function test_el_seeder_corre_en_local_y_testing(string $entorno): void
    {
        $this->enEntorno($entorno);

        $this->seed(EtiquetaSeeder::class);
        $this->seed(DemoRestauranteSeeder::class);

        $this->assertTrue(User::query()->where('email', 'superadmin@menuonl.test')->firstOrFail()->isSuperadmin());
        $this->assertSame(1, Restaurante::query()->count());
    }

    public function test_no_hay_otro_seeder_con_datos_de_prueba_sin_guarda(): void
    {
        $seeders = collect(glob(database_path('seeders/*.php')))
            ->map(fn (string $ruta): string => basename($ruta, '.php'))
            ->sort()
            ->values()
            ->all();

        // Un seeder nuevo obliga a decidir: ¿es de catálogo (seguro en producción) o de prueba (con guarda y prueba aquí)?
        $this->assertSame(['DatabaseSeeder', 'DemoRestauranteSeeder', 'EtiquetaSeeder'], $seeders);
    }
}
