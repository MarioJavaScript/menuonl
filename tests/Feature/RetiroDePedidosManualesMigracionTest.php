<?php

namespace Tests\Feature;

use App\Models\Invitacion;
use App\Models\Restaurante;
use App\Models\User;
use App\Services\ReasignaRolRetirado;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Retiro del tablero de pedidos manuales y del rol "cocina".
 *
 * La lógica de datos y la forma final del esquema se prueban en SQLite y en MariaDB. El rollback real de las dos
 * migraciones solo corre en SQLite: en MariaDB el DDL no es transaccional y un fallo a medias dejaría el esquema
 * cambiado para el resto de la suite.
 */
class RetiroDePedidosManualesMigracionTest extends TestCase
{
    use RefreshDatabase;

    private const string MIGRACION_ROLES = '2026_10_28_000001_retirar_rol_cocina_del_equipo.php';

    private const string MIGRACION_PEDIDOS = '2026_10_28_000002_eliminar_tabla_pedidos.php';

    private function membresia(Restaurante $restaurante, string $rol, bool $activo = true): User
    {
        $usuario = User::factory()->create();
        $restaurante->usuarios()->attach($usuario, ['rol' => $rol, 'activo' => $activo]);

        return $usuario;
    }

    public function test_la_reasignacion_pasa_las_membresias_al_rol_destino_y_las_desactiva(): void
    {
        $restaurante = Restaurante::factory()->create();
        $retirado = $this->membresia($restaurante, 'gerente');
        $otroRetirado = $this->membresia(Restaurante::factory()->create(), 'gerente');
        $editor = $this->membresia($restaurante, 'editor');
        $propietario = $this->membresia($restaurante, 'propietario');

        $conteo = app(ReasignaRolRetirado::class)('gerente', 'recepcion');

        $this->assertSame(2, $conteo['membresias']);

        foreach ([$retirado, $otroRetirado] as $usuario) {
            $this->assertDatabaseHas('restaurante_usuario', ['user_id' => $usuario->id, 'rol' => 'recepcion', 'activo' => false]);
        }

        $this->assertDatabaseHas('restaurante_usuario', ['user_id' => $editor->id, 'rol' => 'editor', 'activo' => true]);
        $this->assertDatabaseHas('restaurante_usuario', ['user_id' => $propietario->id, 'rol' => 'propietario', 'activo' => true]);
    }

    public function test_la_reasignacion_borra_las_invitaciones_pendientes_y_conserva_las_aceptadas_con_el_rol_nuevo(): void
    {
        $restaurante = Restaurante::factory()->create();
        $pendiente = Invitacion::factory()->create(['restaurante_id' => $restaurante->id, 'rol' => 'gerente']);
        $vencida = Invitacion::factory()->expirada()->create(['restaurante_id' => $restaurante->id, 'rol' => 'gerente']);
        $aceptada = Invitacion::factory()->aceptada()->create(['restaurante_id' => $restaurante->id, 'rol' => 'gerente']);
        $deEditor = Invitacion::factory()->create(['restaurante_id' => $restaurante->id, 'rol' => 'editor']);

        $conteo = app(ReasignaRolRetirado::class)('gerente', 'recepcion');

        $this->assertSame(2, $conteo['invitaciones_borradas']);
        $this->assertSame(1, $conteo['invitaciones_aceptadas']);
        $this->assertDatabaseMissing('invitaciones', ['id' => $pendiente->id]);
        $this->assertDatabaseMissing('invitaciones', ['id' => $vencida->id]);
        $this->assertDatabaseHas('invitaciones', ['id' => $aceptada->id, 'rol' => 'recepcion']);
        $this->assertDatabaseHas('invitaciones', ['id' => $deEditor->id, 'rol' => 'editor']);
    }

    public function test_la_reasignacion_sin_filas_no_hace_nada(): void
    {
        $conteo = app(ReasignaRolRetirado::class)('gerente', 'recepcion');

        $this->assertSame(['membresias' => 0, 'invitaciones_aceptadas' => 0, 'invitaciones_borradas' => 0], $conteo);
    }

    public function test_la_tabla_de_pedidos_manuales_ya_no_existe(): void
    {
        $this->assertFalse(Schema::hasTable('pedidos'));
    }

    public function test_los_enums_rechazan_cocina_y_siguen_aceptando_recepcion(): void
    {
        $restaurante = Restaurante::factory()->create();
        $usuario = User::factory()->create();

        $this->assertRolRechazado(fn () => $restaurante->usuarios()->attach($usuario, ['rol' => 'cocina']));
        $this->assertRolRechazado(fn () => Invitacion::factory()->create(['restaurante_id' => $restaurante->id, 'rol' => 'cocina']));

        $restaurante->usuarios()->attach($usuario, ['rol' => 'recepcion']);
        $invitacion = Invitacion::factory()->create(['restaurante_id' => $restaurante->id, 'rol' => 'recepcion']);

        $this->assertDatabaseHas('restaurante_usuario', ['user_id' => $usuario->id, 'rol' => 'recepcion']);
        $this->assertDatabaseHas('invitaciones', ['id' => $invitacion->id, 'rol' => 'recepcion']);
    }

    public function test_el_rollback_y_la_vuelta_a_subir_reasignan_los_datos_y_recrean_la_tabla(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('El DDL de MariaDB no es transaccional: un fallo a medias dejaría el esquema cambiado para el resto de la suite.');
        }

        $roles = require database_path('migrations/'.self::MIGRACION_ROLES);
        $pedidos = require database_path('migrations/'.self::MIGRACION_PEDIDOS);
        $restaurante = Restaurante::factory()->create();

        try {
            $pedidos->down();
            $roles->down();

            $this->assertTrue(Schema::hasTable('pedidos'));
            $this->assertSame(
                ['id', 'restaurante_id', 'mesa', 'detalle', 'total', 'estado', 'entregado_at', 'created_at', 'updated_at'],
                array_column(Schema::getColumns('pedidos'), 'name'),
            );

            $indices = array_map(fn (array $indice): array => $indice['columns'], Schema::getIndexes('pedidos'));
            $this->assertContains(['restaurante_id', 'estado'], $indices);
            $this->assertContains(['restaurante_id', 'entregado_at'], $indices);

            $cocinero = User::factory()->create();
            $restaurante->usuarios()->attach($cocinero, ['rol' => 'cocina']);
            $pendiente = Invitacion::factory()->create(['restaurante_id' => $restaurante->id, 'rol' => 'cocina']);
            $aceptada = Invitacion::factory()->aceptada()->create(['restaurante_id' => $restaurante->id, 'rol' => 'cocina']);
        } finally {
            $roles->up();
            $pedidos->up();
        }

        $this->assertFalse(Schema::hasTable('pedidos'));
        $this->assertDatabaseHas('restaurante_usuario', ['user_id' => $cocinero->id, 'rol' => 'recepcion', 'activo' => false]);
        $this->assertDatabaseMissing('invitaciones', ['id' => $pendiente->id]);
        $this->assertDatabaseHas('invitaciones', ['id' => $aceptada->id, 'rol' => 'recepcion']);
        $this->assertRolRechazado(fn () => $restaurante->usuarios()->attach(User::factory()->create(), ['rol' => 'cocina']));
    }

    private function assertRolRechazado(callable $insercion): void
    {
        try {
            $insercion();
            $this->fail('La base debía rechazar el rol "cocina".');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
