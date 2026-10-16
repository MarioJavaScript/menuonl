<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\CodigoQr;
use App\Models\Contacto;
use App\Models\Horario;
use App\Models\Invitacion;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use App\Models\VarianteTraduccion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ArmaEscenarioDePanel;
use Tests\TestCase;

/**
 * Permission matrix from docs/01-especificacion.md (sections 1.1, RF-12 to RF-19, RF-30) plus the
 * decisions recorded with the project owner. Expected values are written out here on purpose,
 * independent from the policy constants, so a change in either one is caught.
 */
class PoliticasMatrizTest extends TestCase
{
    use ArmaEscenarioDePanel, RefreshDatabase;

    private const array ROLES = ['superadmin', 'propietario', 'editor'];

    private const array CON_REGISTRO = ['view', 'update', 'delete', 'restore', 'forceDelete'];

    private const array SIN_REGISTRO = ['viewAny', 'create', 'deleteAny', 'restoreAny', 'forceDeleteAny', 'reorder'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpEscenarioDePanel();
    }

    /**
     * @return array<string, array{0: string, 1: array<string, list<string>>}>
     */
    public static function matrices(): array
    {
        $menu = ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'deleteAny', 'restoreAny', 'reorder'];

        $todosMenu = ['superadmin' => $menu, 'propietario' => $menu, 'editor' => $menu];

        return [
            'categoria' => ['categoria', $todosMenu],
            'producto' => ['producto', $todosMenu],
            'contacto' => ['contacto', $todosMenu],
            'horario' => ['horario', $todosMenu],
            'producto variante (via producto)' => ['variante', $todosMenu],
            'categoria traduccion (via categoria)' => ['categoriaTraduccion', $todosMenu],
            'producto traduccion (via producto)' => ['productoTraduccion', $todosMenu],
            'variante traduccion (via variante)' => ['varianteTraduccion', $todosMenu],
            // The editor creates and edits QR codes (diagram 6.4) but cannot delete them.
            'codigo qr' => ['codigoQr', [
                'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'deleteAny', 'restoreAny'],
                'propietario' => ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'deleteAny', 'restoreAny'],
                'editor' => ['viewAny', 'view', 'create', 'update'],
            ]],
            // Languages are restaurant settings (RF-19): the editor can only read them.
            'idioma' => ['idioma', [
                'superadmin' => ['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', 'reorder'],
                'propietario' => ['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', 'reorder'],
                'editor' => ['viewAny', 'view'],
            ]],
            // Only propietario and superadmin invite and revoke (RF-13); invitations are never edited.
            'invitacion' => ['invitacion', [
                'superadmin' => ['viewAny', 'view', 'create', 'delete', 'deleteAny'],
                'propietario' => ['viewAny', 'view', 'create', 'delete', 'deleteAny'],
                'editor' => [],
            ]],
            // Platform-wide list and signup for the superadmin only; settings for propietario (RF-19).
            'restaurante' => ['restaurante', [
                'superadmin' => ['viewAny', 'view', 'create', 'update'],
                'propietario' => ['view', 'update'],
                'editor' => ['view'],
            ]],
        ];
    }

    /**
     * Only the model names of matrices(): outsiders are denied every ability, so no matrix is needed.
     *
     * @return array<string, array{0: string}>
     */
    public static function modelos(): array
    {
        return array_map(fn (array $caso): array => [$caso[0]], self::matrices());
    }

    /**
     * @param  array<string, list<string>>  $permitidas
     */
    #[DataProvider('matrices')]
    public function test_cada_rol_puede_hacer_solo_lo_que_le_toca(string $modelo, array $permitidas): void
    {
        $restaurante = $this->crearRestaurante('a');
        $registro = $this->crearRegistro($modelo, $restaurante);
        RestauranteScope::setRestauranteId($restaurante->id);

        foreach (self::ROLES as $rol) {
            $usuario = $this->crearUsuario($rol, $restaurante);

            foreach (self::CON_REGISTRO as $habilidad) {
                $this->assertSame(
                    in_array($habilidad, $permitidas[$rol], true),
                    Gate::forUser($usuario)->allows($habilidad, $registro),
                    "{$rol} / {$modelo} / {$habilidad}",
                );
            }

            foreach (self::SIN_REGISTRO as $habilidad) {
                $this->assertSame(
                    in_array($habilidad, $permitidas[$rol], true),
                    Gate::forUser($usuario)->allows($habilidad, $registro::class),
                    "{$rol} / {$modelo} / {$habilidad}",
                );
            }
        }
    }

    #[DataProvider('modelos')]
    public function test_un_miembro_de_otro_restaurante_no_puede_nada_sobre_el_registro(string $modelo): void
    {
        $a = $this->crearRestaurante('a');
        $b = $this->crearRestaurante('b');
        $registro = $this->crearRegistro($modelo, $a);

        foreach (['propietario', 'editor'] as $rol) {
            $ajeno = $this->crearUsuario($rol, $b);

            foreach (self::CON_REGISTRO as $habilidad) {
                $this->assertFalse(Gate::forUser($ajeno)->allows($habilidad, $registro), "{$rol} de B / {$modelo} / {$habilidad}");
            }

            // Class-level abilities are judged against the active restaurant: with A active, B's members have no role there.
            RestauranteScope::setRestauranteId($a->id);

            foreach (self::SIN_REGISTRO as $habilidad) {
                $this->assertFalse(Gate::forUser($ajeno)->allows($habilidad, $registro::class), "{$rol} de B / {$modelo} / {$habilidad}");
            }
        }
    }

    public function test_el_superadmin_no_puede_borrar_restaurantes(): void
    {
        $restaurante = $this->crearRestaurante('a');
        $superadmin = $this->crearUsuario('superadmin');

        $this->assertFalse(Gate::forUser($superadmin)->allows('delete', $restaurante));
        $this->assertFalse(Gate::forUser($superadmin)->allows('forceDelete', $restaurante));
    }

    public function test_nadie_borra_definitivamente_menu_ni_contactos(): void
    {
        $restaurante = $this->crearRestaurante('a');
        $contacto = $this->crearRegistro('contacto', $restaurante);

        foreach (self::ROLES as $rol) {
            $this->assertFalse(Gate::forUser($this->crearUsuario($rol, $restaurante))->allows('forceDelete', $contacto), $rol);
        }
    }

    public function test_el_editor_no_gestiona_usuarios_ni_invitaciones(): void
    {
        $restaurante = $this->crearRestaurante('a');
        $editor = $this->crearUsuario('editor', $restaurante);
        $otro = $this->crearUsuario('editor', $restaurante);
        RestauranteScope::setRestauranteId($restaurante->id);

        $this->assertFalse(Gate::forUser($editor)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($editor)->allows('revocarAcceso', $otro));
        $this->assertFalse(Gate::forUser($editor)->allows('create', Invitacion::class));
    }

    public function test_el_propietario_revoca_acceso_pero_no_a_si_mismo_ni_al_ultimo_propietario(): void
    {
        $restaurante = $this->crearRestaurante('a');
        $propietario = $this->crearUsuario('propietario', $restaurante);
        $editor = $this->crearUsuario('editor', $restaurante);
        $ajeno = $this->crearUsuario('editor', $this->crearRestaurante('b'));
        RestauranteScope::setRestauranteId($restaurante->id);

        $this->assertTrue(Gate::forUser($propietario)->allows('revocarAcceso', $editor));
        $this->assertFalse(Gate::forUser($propietario)->allows('revocarAcceso', $propietario));
        $this->assertFalse(Gate::forUser($propietario)->allows('revocarAcceso', $ajeno));

        $segundoPropietario = $this->crearUsuario('propietario', $restaurante);
        $this->assertTrue(Gate::forUser($propietario)->allows('revocarAcceso', $segundoPropietario));

        $segundoPropietario->restaurantes()->detach($restaurante->id);
        $unico = $this->crearUsuario('superadmin');
        $this->assertFalse(Gate::forUser($unico)->allows('revocarAcceso', $propietario), 'el último propietario no se puede quitar');
    }

    private function crearRegistro(string $modelo, Restaurante $restaurante): Model
    {
        $datos = ['restaurante_id' => $restaurante->id];

        return match ($modelo) {
            'categoria' => Categoria::factory()->create($datos),
            'producto' => Producto::factory()->create($datos),
            'contacto' => Contacto::factory()->create($datos),
            'horario' => Horario::factory()->create($datos + ['dia_semana' => 1, 'abre' => '09:00', 'cierra' => '18:00']),
            'variante' => ProductoVariante::factory()->create(['producto_id' => Producto::factory()->create($datos)->id]),
            'categoriaTraduccion' => CategoriaTraduccion::factory()->create(['categoria_id' => Categoria::factory()->create($datos)->id]),
            'productoTraduccion' => ProductoTraduccion::factory()->create(['producto_id' => Producto::factory()->create($datos)->id]),
            'varianteTraduccion' => VarianteTraduccion::factory()->create([
                'variante_id' => ProductoVariante::factory()->create(['producto_id' => Producto::factory()->create($datos)->id])->id,
            ]),
            'codigoQr' => CodigoQr::factory()->create($datos),
            'idioma' => RestauranteIdioma::factory()->create($datos + ['idioma' => 'en']),
            'invitacion' => Invitacion::factory()->create($datos),
            'restaurante' => $restaurante,
        };
    }
}
