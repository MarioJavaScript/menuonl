<?php

namespace Tests\Feature;

use Database\Seeders\EtiquetaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseMigrationAndSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tier 1: All 14 schema tables from docs/esquema.sql must exist.
     */
    public function test_all_14_schema_tables_exist(): void
    {
        $expectedTables = [
            'users',                  // or usuarios
            'restaurantes',
            'restaurante_usuario',
            'invitaciones',
            'restaurante_idiomas',
            'contactos',
            'horarios',
            'categorias',
            'categoria_traducciones',
            'productos',
            'producto_traducciones',
            'producto_variantes',
            'variante_traducciones',
            'etiquetas',
            'producto_etiqueta',
            'codigos_qr',
            'historial_cambios',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Failed asserting that table [{$table}] exists in the database schema."
            );
        }
    }

    /**
     * Tier 1: Restaurantes table has all required columns and soft deletes.
     */
    public function test_restaurantes_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('restaurantes'));

        $expectedColumns = [
            'id',
            'nombre',
            'slug',
            'descripcion',
            'logo',
            'portada',
            'moneda',
            'idioma_default',
            'zona_horaria',
            'whatsapp_pedidos',
            'pedidos_activos',
            'mostrar_agotados',
            'estado',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('restaurantes', $column),
                "Failed asserting that table [restaurantes] contains column [{$column}]."
            );
        }
    }

    /**
     * Tier 1: SoftDeletes column exists on required entities (restaurantes, categorias, productos).
     */
    public function test_soft_deletes_columns_exist_on_catalog_tables(): void
    {
        $this->assertTrue(
            Schema::hasColumn('restaurantes', 'deleted_at'),
            'restaurantes table must have deleted_at column for soft deletes.'
        );
        $this->assertTrue(
            Schema::hasColumn('categorias', 'deleted_at'),
            'categorias table must have deleted_at column for soft deletes.'
        );
        $this->assertTrue(
            Schema::hasColumn('productos', 'deleted_at'),
            'productos table must have deleted_at column for soft deletes.'
        );
    }

    /**
     * Tier 1: Productos table has required price, availability, and relation columns.
     */
    public function test_productos_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('productos'));

        $expectedColumns = [
            'id',
            'restaurante_id',
            'categoria_id',
            'precio',
            'precio_oferta',
            'imagen',
            'disponible',
            'visible',
            'destacado',
            'orden',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('productos', $column),
                "Failed asserting that table [productos] contains column [{$column}]."
            );
        }
    }

    /**
     * Tier 1: Horarios table has required day of week, shifts, and closed flag columns.
     */
    public function test_horarios_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('horarios'));

        $expectedColumns = [
            'id',
            'restaurante_id',
            'dia_semana',
            'abre',
            'cierra',
            'cerrado',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('horarios', $column),
                "Failed asserting that table [horarios] contains column [{$column}]."
            );
        }
    }

    /**
     * Tier 1: Contactos table has required type, label, value, and ordering columns.
     */
    public function test_contactos_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('contactos'));

        $expectedColumns = [
            'id',
            'restaurante_id',
            'tipo',
            'etiqueta',
            'valor',
            'orden',
            'visible',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('contactos', $column),
                "Failed asserting that table [contactos] contains column [{$column}]."
            );
        }
    }

    /**
     * Tier 1: Codigos_qr table has required shortcode and table assignment columns.
     */
    public function test_codigos_qr_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('codigos_qr'));

        $expectedColumns = [
            'id',
            'restaurante_id',
            'nombre',
            'mesa',
            'codigo',
            'activo',
            'created_at',
            'updated_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('codigos_qr', $column),
                "Failed asserting that table [codigos_qr] contains column [{$column}]."
            );
        }
    }

    /**
     * Tier 1: Invitaciones table has token, role, expiry, and accepted timestamp columns.
     */
    public function test_invitaciones_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('invitaciones'));

        $expectedColumns = [
            'id',
            'restaurante_id',
            'email',
            'rol',
            'token',
            'invitado_por',
            'expira_en',
            'aceptada_en',
            'created_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('invitaciones', $column),
                "Failed asserting that table [invitaciones] contains column [{$column}]."
            );
        }
    }

    /**
     * Tier 1: Historial_cambios table has entity, action, and JSON diff snapshot columns.
     */
    public function test_historial_cambios_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('historial_cambios'));

        $expectedColumns = [
            'id',
            'restaurante_id',
            'usuario_id',
            'entidad',
            'entidad_id',
            'accion',
            'cambios',
            'created_at',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('historial_cambios', $column),
                "Failed asserting that table [historial_cambios] contains column [{$column}]."
            );
        }
    }

    /**
     * Tier 1: EtiquetaSeeder populates exactly the 7 dietary and promotional tags with icons.
     */
    public function test_etiqueta_seeder_populates_7_system_tags_with_icons(): void
    {
        $this->seed(EtiquetaSeeder::class);

        $this->assertDatabaseCount('etiquetas', 7);

        $expectedTags = [
            'vegano' => 'leaf',
            'vegetariano' => 'carrot',
            'picante' => 'flame',
            'sin_gluten' => 'wheat-off',
            'sin_lactosa' => 'milk-off',
            'nuevo' => 'sparkles',
            'recomendado' => 'star',
        ];

        foreach ($expectedTags as $codigo => $icono) {
            $this->assertDatabaseHas('etiquetas', [
                'codigo' => $codigo,
                'icono' => $icono,
            ]);
        }
    }

    /**
     * Tier 4: DatabaseSeeder or DemoRestaurantSeeder creates a demo restaurant with complete graph.
     */
    public function test_database_seeder_creates_demo_restaurant_graph(): void
    {
        $this->seed();

        // Must have at least 1 restaurant
        $this->assertGreaterThanOrEqual(1, DB::table('restaurantes')->count());

        $demoRestaurant = DB::table('restaurantes')->first();
        $this->assertNotNull($demoRestaurant);

        // Demo restaurant must have categories
        $this->assertDatabaseHas('categorias', [
            'restaurante_id' => $demoRestaurant->id,
        ]);

        // Demo restaurant must have products
        $this->assertDatabaseHas('productos', [
            'restaurante_id' => $demoRestaurant->id,
        ]);

        // Demo restaurant must have contacts
        $this->assertDatabaseHas('contactos', [
            'restaurante_id' => $demoRestaurant->id,
        ]);

        // Demo restaurant must have schedules
        $this->assertDatabaseHas('horarios', [
            'restaurante_id' => $demoRestaurant->id,
        ]);

        // Demo restaurant must have QR codes
        $this->assertDatabaseHas('codigos_qr', [
            'restaurante_id' => $demoRestaurant->id,
        ]);
    }
}
