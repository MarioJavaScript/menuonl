<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Etiqueta;
use App\Models\Restaurante;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoRestauranteSeeder extends Seeder
{
    /**
     * Demo menu: categories with their products.
     *
     * @var list<array{nombres: array<string, string>, productos: list<array<string, mixed>>}>
     */
    private const MENU = [
        [
            'nombres' => ['es' => 'Entradas', 'en' => 'Starters'],
            'productos' => [
                [
                    'nombres' => ['es' => ['Guacamole', 'Aguacate machacado con chile serrano y totopos.'], 'en' => ['Guacamole', 'Mashed avocado with serrano pepper and tortilla chips.']],
                    'precio' => 85, 'precio_oferta' => 69, 'destacado' => true,
                    'etiquetas' => ['vegano', 'vegetariano', 'sin_gluten'],
                ],
                [
                    'nombres' => ['es' => ['Queso fundido', 'Queso Oaxaca gratinado con chorizo.'], 'en' => ['Melted cheese', 'Grilled Oaxaca cheese with chorizo.']],
                    'precio' => 95,
                    'etiquetas' => [],
                ],
                [
                    // Only in Spanish, to show the fallback to idioma_default.
                    'nombres' => ['es' => ['Sopa de tortilla', 'Caldo de jitomate con tortilla frita y aguacate.']],
                    'precio' => 75, 'disponible' => false,
                    'etiquetas' => ['vegetariano'],
                ],
            ],
        ],
        [
            'nombres' => ['es' => 'Tacos', 'en' => 'Tacos'],
            'productos' => [
                [
                    'nombres' => ['es' => ['Tacos al pastor', 'Cerdo adobado con piña y cebolla.'], 'en' => ['Al pastor tacos', 'Marinated pork with pineapple and onion.']],
                    'precio' => 60, 'destacado' => true,
                    'etiquetas' => ['recomendado', 'picante'],
                    'variantes' => [
                        ['es' => 'Orden de 3', 'en' => 'Order of 3', 'precio' => 60],
                        ['es' => 'Orden de 5', 'en' => 'Order of 5', 'precio' => 95],
                    ],
                ],
                [
                    'nombres' => ['es' => ['Tacos de suadero', 'Suadero de res con salsa verde.'], 'en' => ['Suadero tacos', 'Beef suadero with green salsa.']],
                    'precio' => 70, 'precio_oferta' => 59,
                    'etiquetas' => ['nuevo'],
                ],
                [
                    'nombres' => ['es' => ['Tacos de coliflor', 'Coliflor asada con salsa macha.'], 'en' => ['Cauliflower tacos', 'Roasted cauliflower with salsa macha.']],
                    'precio' => 65,
                    'etiquetas' => ['vegano', 'picante'],
                ],
            ],
        ],
        [
            'nombres' => ['es' => 'Bebidas', 'en' => 'Drinks'],
            'productos' => [
                [
                    'nombres' => ['es' => ['Agua fresca', 'Jamaica, horchata o tamarindo.'], 'en' => ['Agua fresca', 'Hibiscus, horchata or tamarind.']],
                    'precio' => 30,
                    'etiquetas' => ['vegano', 'sin_lactosa'],
                    'variantes' => [
                        ['es' => 'Chica', 'en' => 'Small', 'precio' => 30],
                        ['es' => 'Grande', 'en' => 'Large', 'precio' => 45, 'disponible' => false],
                    ],
                ],
                [
                    'nombres' => ['es' => ['Café de olla', 'Café con canela y piloncillo.'], 'en' => ['Café de olla', 'Coffee with cinnamon and piloncillo.']],
                    'precio' => 40, 'disponible' => false,
                    'etiquetas' => ['vegano', 'sin_gluten'],
                ],
            ],
        ],
    ];

    /**
     * Seed one active demo restaurant with its menu, contacts, hours, QR codes and panel users.
     */
    public function run(): void
    {
        $this->exigirEntornoDeDesarrollo();

        if (Restaurante::withTrashed()->where('slug', 'la-esquina')->exists()) {
            return;
        }

        $restaurante = Restaurante::create([
            'nombre' => 'Taquería La Esquina',
            'slug' => 'la-esquina',
            'descripcion' => 'Tacos, antojitos y aguas frescas desde 1998.',
            'moneda' => 'MXN',
            'idioma_default' => 'es',
            'zona_horaria' => 'America/Mexico_City',
            'whatsapp_pedidos' => '525512345678',
            'pedidos_activos' => true,
            'mostrar_agotados' => true,
            'estado' => 'activo',
        ]);

        $restaurante->idiomas()->createMany([
            ['idioma' => 'es', 'orden' => 0],
            ['idioma' => 'en', 'orden' => 1],
        ]);

        $etiquetas = Etiqueta::pluck('id', 'codigo');

        foreach (self::MENU as $orden => $categoriaDemo) {
            $this->crearCategoria($restaurante, $orden, $categoriaDemo, $etiquetas->all());
        }

        $restaurante->contactos()->createMany([
            ['tipo' => 'whatsapp', 'etiqueta' => 'Pedidos', 'valor' => '525512345678', 'orden' => 0],
            ['tipo' => 'telefono', 'etiqueta' => 'Reservaciones', 'valor' => '+52 55 1234 5678', 'orden' => 1],
            ['tipo' => 'direccion', 'etiqueta' => null, 'valor' => 'Av. Insurgentes Sur 123, Roma Nte., CDMX', 'orden' => 2],
            ['tipo' => 'instagram', 'etiqueta' => null, 'valor' => 'https://instagram.com/laesquina', 'orden' => 3],
        ]);

        $restaurante->horarios()->createMany([
            ['dia_semana' => 0, 'abre' => '09:00', 'cierra' => '17:00'],
            ['dia_semana' => 1, 'abre' => null, 'cierra' => null, 'cerrado' => true],
            // Martes a jueves: turno partido (comida y cena).
            ...collect([2, 3, 4])->flatMap(fn (int $dia): array => [
                ['dia_semana' => $dia, 'abre' => '13:00', 'cierra' => '17:00'],
                ['dia_semana' => $dia, 'abre' => '19:00', 'cierra' => '23:00'],
            ])->all(),
            // Viernes y sábado: turno nocturno que cierra al día siguiente.
            ['dia_semana' => 5, 'abre' => '13:00', 'cierra' => '02:00'],
            ['dia_semana' => 6, 'abre' => '13:00', 'cierra' => '02:00'],
        ]);

        $restaurante->codigosQr()->createMany([
            ['nombre' => 'General', 'mesa' => null, 'codigo' => 'ESQGENERAL'],
            ['nombre' => 'Mesa 5', 'mesa' => '5', 'codigo' => 'ESQMESA05'],
        ]);

        $this->crearUsuarios($restaurante);
    }

    /**
     * @param  array{nombres: array<string, string>, productos: list<array<string, mixed>>}  $categoriaDemo
     * @param  array<string, int>  $etiquetas  codigo => id
     */
    private function crearCategoria(Restaurante $restaurante, int $orden, array $categoriaDemo, array $etiquetas): void
    {
        $categoria = $restaurante->categorias()->create(['orden' => $orden]);

        foreach ($categoriaDemo['nombres'] as $idioma => $nombre) {
            $categoria->traducciones()->create(['idioma' => $idioma, 'nombre' => $nombre]);
        }

        foreach ($categoriaDemo['productos'] as $ordenProducto => $productoDemo) {
            $this->crearProducto($categoria, $ordenProducto, $productoDemo, $etiquetas);
        }
    }

    /**
     * @param  array<string, mixed>  $productoDemo
     * @param  array<string, int>  $etiquetas  codigo => id
     */
    private function crearProducto(Categoria $categoria, int $orden, array $productoDemo, array $etiquetas): void
    {
        $producto = $categoria->productos()->create([
            'restaurante_id' => $categoria->restaurante_id,
            'precio' => $productoDemo['precio'],
            'precio_oferta' => $productoDemo['precio_oferta'] ?? null,
            'disponible' => $productoDemo['disponible'] ?? true,
            'destacado' => $productoDemo['destacado'] ?? false,
            'orden' => $orden,
        ]);

        foreach ($productoDemo['nombres'] as $idioma => [$nombre, $descripcion]) {
            $producto->traducciones()->create(['idioma' => $idioma, 'nombre' => $nombre, 'descripcion' => $descripcion]);
        }

        foreach ($productoDemo['variantes'] ?? [] as $ordenVariante => $varianteDemo) {
            $variante = $producto->variantes()->create([
                'precio' => $varianteDemo['precio'],
                'disponible' => $varianteDemo['disponible'] ?? true,
                'orden' => $ordenVariante,
            ]);

            $variante->traducciones()->createMany([
                ['idioma' => 'es', 'nombre' => $varianteDemo['es']],
                ['idioma' => 'en', 'nombre' => $varianteDemo['en']],
            ]);
        }

        $producto->etiquetas()->attach(array_map(fn (string $codigo): int => $etiquetas[$codigo], $productoDemo['etiquetas']));
    }

    /**
     * This seeder creates accounts with a publicly known password, so it refuses to run anywhere but local and testing,
     * even when called directly with `db:seed --class=DemoRestauranteSeeder --force`.
     *
     * @throws RuntimeException
     */
    private function exigirEntornoDeDesarrollo(): void
    {
        if (app()->environment(['local', 'testing'])) {
            return;
        }

        throw new RuntimeException(
            'DemoRestauranteSeeder crea cuentas de prueba con una contraseña conocida y solo puede ejecutarse en los entornos '
            .'local y testing (entorno actual: '.app()->environment().'). No se creó nada. '
            .'Para el primer superadmin real usa: php artisan menuonl:crear-superadmin'
        );
    }

    /**
     * Superadmin, owner and editor accounts (password: "password"; local/testing only).
     */
    private function crearUsuarios(Restaurante $restaurante): void
    {
        // es_superadmin is not mass assignable, so it is set explicitly.
        User::firstOrCreate(
            ['email' => 'superadmin@menuonl.test'],
            ['name' => 'Superadmin', 'password' => 'password'],
        )->forceFill(['es_superadmin' => true])->save();

        $propietario = User::firstOrCreate(
            ['email' => 'propietario@menuonl.test'],
            ['name' => 'Propietario La Esquina', 'password' => 'password'],
        );

        $editor = User::firstOrCreate(
            ['email' => 'editor@menuonl.test'],
            ['name' => 'Editor La Esquina', 'password' => 'password'],
        );

        $restaurante->usuarios()->attach([
            $propietario->id => ['rol' => 'propietario'],
            $editor->id => ['rol' => 'editor'],
        ]);
    }
}
