<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\CategoriaTraduccion;
use App\Models\CodigoQr;
use App\Models\Contacto;
use App\Models\Etiqueta;
use App\Models\HistorialCambio;
use App\Models\Horario;
use App\Models\Invitacion;
use App\Models\Producto;
use App\Models\ProductoTraduccion;
use App\Models\ProductoVariante;
use App\Models\Restaurante;
use App\Models\RestauranteIdioma;
use App\Models\User;
use App\Models\VarianteTraduccion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoRestaurantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Users
        $superadmin = User::firstOrCreate(
            ['email' => 'admin@menuonl.com'],
            [
                'name' => 'Superadmin MENUONL',
                'password' => Hash::make('password'),
                'es_superadmin' => true,
                'activo' => true,
                'email_verified_at' => now(),
            ]
        );

        $propietario = User::firstOrCreate(
            ['email' => 'propietario@demo.com'],
            [
                'name' => 'Propietario Demo',
                'password' => Hash::make('password'),
                'es_superadmin' => false,
                'activo' => true,
                'email_verified_at' => now(),
            ]
        );

        $editor = User::firstOrCreate(
            ['email' => 'editor@demo.com'],
            [
                'name' => 'Editor Demo',
                'password' => Hash::make('password'),
                'es_superadmin' => false,
                'activo' => true,
                'email_verified_at' => now(),
            ]
        );

        // 2. Restaurante
        $restaurante = Restaurante::firstOrCreate(
            ['slug' => 'la-trattoria-demo'],
            [
                'nombre' => 'La Trattoria Demo',
                'descripcion' => 'Auténtica comida italiana, pastas artesanales y pizzas al horno de leña.',
                'moneda' => 'MXN',
                'idioma_default' => 'es',
                'zona_horaria' => 'America/Mexico_City',
                'whatsapp_pedidos' => '5215512345678',
                'pedidos_activos' => true,
                'mostrar_agotados' => true,
                'estado' => 'activo',
            ]
        );

        // 3. User association
        $restaurante->usuarios()->syncWithoutDetaching([
            $propietario->id => ['rol' => 'propietario'],
            $editor->id => ['rol' => 'editor'],
        ]);

        // 4. Languages
        RestauranteIdioma::firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'codigo' => 'es'],
            ['idioma' => 'es', 'orden' => 1, 'activo' => true]
        );
        RestauranteIdioma::firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'codigo' => 'en'],
            ['idioma' => 'en', 'orden' => 2, 'activo' => true]
        );

        // 5. Dietary Tags map
        $tags = Etiqueta::all()->keyBy('slug');

        // 6. Categories & Products
        // Category 1: Antipasti
        $catAntipasti = Categoria::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'orden' => 1],
            ['visible' => true]
        );
        CategoriaTraduccion::firstOrCreate(
            ['categoria_id' => $catAntipasti->id, 'idioma' => 'es'],
            ['nombre' => 'Antipasti & Entradas', 'descripcion' => 'Entradas clásicas para abrir el apetito']
        );
        CategoriaTraduccion::firstOrCreate(
            ['categoria_id' => $catAntipasti->id, 'idioma' => 'en'],
            ['nombre' => 'Antipasti & Appetizers', 'descripcion' => 'Classic Italian starters to begin your meal']
        );

        // Product 1: Bruschetta
        $prodBruschetta = Producto::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'categoria_id' => $catAntipasti->id, 'orden' => 1],
            [
                'precio' => 85.00,
                'precio_oferta' => 75.00,
                'disponible' => true,
                'visible' => true,
                'destacado' => true,
            ]
        );
        ProductoTraduccion::firstOrCreate(
            ['producto_id' => $prodBruschetta->id, 'idioma' => 'es'],
            [
                'nombre' => 'Bruschetta al Pomodoro',
                'descripcion' => 'Pan tostado con tomate fresco, albahaca y aceite de oliva virgen extra.',
            ]
        );
        ProductoTraduccion::firstOrCreate(
            ['producto_id' => $prodBruschetta->id, 'idioma' => 'en'],
            [
                'nombre' => 'Tomato Bruschetta',
                'descripcion' => 'Toasted rustic bread with fresh tomatoes, basil, and extra virgin olive oil.',
            ]
        );
        if (isset($tags['vegano'], $tags['vegetariano'], $tags['recomendado'])) {
            $prodBruschetta->etiquetas()->syncWithoutDetaching([
                $tags['vegano']->id,
                $tags['vegetariano']->id,
                $tags['recomendado']->id,
            ]);
        }

        // Product 2: Carpaccio
        $prodCarpaccio = Producto::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'categoria_id' => $catAntipasti->id, 'orden' => 2],
            [
                'precio' => 150.00,
                'precio_oferta' => null,
                'disponible' => true,
                'visible' => true,
                'destacado' => false,
            ]
        );
        ProductoTraduccion::firstOrCreate(
            ['producto_id' => $prodCarpaccio->id, 'idioma' => 'es'],
            [
                'nombre' => 'Carpaccio di Manzo',
                'descripcion' => 'Finas láminas de res con arúgula, alcaparras y parmesano reggiano.',
            ]
        );
        ProductoTraduccion::firstOrCreate(
            ['producto_id' => $prodCarpaccio->id, 'idioma' => 'en'],
            [
                'nombre' => 'Beef Carpaccio',
                'descripcion' => 'Thinly sliced beef tenderloin with arugula, capers, and parmigiano reggiano.',
            ]
        );
        if (isset($tags['sin_gluten'])) {
            $prodCarpaccio->etiquetas()->syncWithoutDetaching([$tags['sin_gluten']->id]);
        }

        // Category 2: Pastas & Pizzas
        $catPastas = Categoria::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'orden' => 2],
            ['visible' => true]
        );
        CategoriaTraduccion::firstOrCreate(
            ['categoria_id' => $catPastas->id, 'idioma' => 'es'],
            ['nombre' => 'Pastas & Pizzas', 'descripcion' => 'Pastas frescas hechas a mano y pizzas a la leña']
        );
        CategoriaTraduccion::firstOrCreate(
            ['categoria_id' => $catPastas->id, 'idioma' => 'en'],
            ['nombre' => 'Pasta & Pizza', 'descripcion' => 'Fresh handmade pasta and wood-fired pizzas']
        );

        // Product 3: Pizza Margherita (with variants)
        $prodPizza = Producto::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'categoria_id' => $catPastas->id, 'orden' => 1],
            [
                'precio' => 160.00,
                'precio_oferta' => null,
                'disponible' => true,
                'visible' => true,
                'destacado' => true,
            ]
        );
        ProductoTraduccion::firstOrCreate(
            ['producto_id' => $prodPizza->id, 'idioma' => 'es'],
            [
                'nombre' => 'Pizza Margherita',
                'descripcion' => 'Salsa de tomate San Marzano, mozzarella fior di latte y albahaca fresca.',
            ]
        );
        ProductoTraduccion::firstOrCreate(
            ['producto_id' => $prodPizza->id, 'idioma' => 'en'],
            [
                'nombre' => 'Margherita Pizza',
                'descripcion' => 'San Marzano tomato sauce, fresh mozzarella fior di latte, and fresh basil.',
            ]
        );
        if (isset($tags['vegetariano'], $tags['recomendado'])) {
            $prodPizza->etiquetas()->syncWithoutDetaching([
                $tags['vegetariano']->id,
                $tags['recomendado']->id,
            ]);
        }

        // Variants for Pizza Margherita
        $varChica = ProductoVariante::firstOrCreate(
            ['producto_id' => $prodPizza->id, 'orden' => 1],
            ['precio' => 160.00, 'disponible' => true]
        );
        VarianteTraduccion::firstOrCreate(
            ['variante_id' => $varChica->id, 'idioma' => 'es'],
            ['nombre' => 'Individual (25cm)']
        );
        VarianteTraduccion::firstOrCreate(
            ['variante_id' => $varChica->id, 'idioma' => 'en'],
            ['nombre' => 'Personal (10 inch)']
        );

        $varGrande = ProductoVariante::firstOrCreate(
            ['producto_id' => $prodPizza->id, 'orden' => 2],
            ['precio' => 240.00, 'disponible' => true]
        );
        VarianteTraduccion::firstOrCreate(
            ['variante_id' => $varGrande->id, 'idioma' => 'es'],
            ['nombre' => 'Grande (35cm)']
        );
        VarianteTraduccion::firstOrCreate(
            ['variante_id' => $varGrande->id, 'idioma' => 'en'],
            ['nombre' => 'Large (14 inch)']
        );

        // Product 4: Carbonara
        $prodCarbonara = Producto::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'categoria_id' => $catPastas->id, 'orden' => 2],
            [
                'precio' => 185.00,
                'precio_oferta' => 165.00,
                'disponible' => true,
                'visible' => true,
                'destacado' => true,
            ]
        );
        ProductoTraduccion::firstOrCreate(
            ['producto_id' => $prodCarbonara->id, 'idioma' => 'es'],
            [
                'nombre' => 'Spaghetti alla Carbonara',
                'descripcion' => 'Receta tradicional romana con guanciale, yema de huevo y pecorino romano.',
            ]
        );
        ProductoTraduccion::firstOrCreate(
            ['producto_id' => $prodCarbonara->id, 'idioma' => 'en'],
            [
                'nombre' => 'Spaghetti Carbonara',
                'descripcion' => 'Authentic Roman recipe with cured guanciale, egg yolk, and pecorino romano.',
            ]
        );
        if (isset($tags['recomendado'])) {
            $prodCarbonara->etiquetas()->syncWithoutDetaching([$tags['recomendado']->id]);
        }

        // Product 5: Lasagna (Agotado)
        $prodLasagna = Producto::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'categoria_id' => $catPastas->id, 'orden' => 3],
            [
                'precio' => 195.00,
                'precio_oferta' => null,
                'disponible' => false,
                'visible' => true,
                'destacado' => false,
            ]
        );
        ProductoTraduccion::firstOrCreate(
            ['producto_id' => $prodLasagna->id, 'idioma' => 'es'],
            [
                'nombre' => 'Lasagna Bolognese',
                'descripcion' => 'Capas de pasta fresca con ragú tradicional y bechamel gratinada.',
            ]
        );
        ProductoTraduccion::firstOrCreate(
            ['producto_id' => $prodLasagna->id, 'idioma' => 'en'],
            [
                'nombre' => 'Bolognese Lasagna',
                'descripcion' => 'Layers of fresh pasta baked with slow-cooked ragù and creamy bechamel.',
            ]
        );

        // Category 3: Postres
        $catPostres = Categoria::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'orden' => 3],
            ['visible' => true]
        );
        CategoriaTraduccion::firstOrCreate(
            ['categoria_id' => $catPostres->id, 'idioma' => 'es'],
            ['nombre' => 'Postres & Café', 'descripcion' => 'Dulcísima repostería italiana tradicional']
        );
        CategoriaTraduccion::firstOrCreate(
            ['categoria_id' => $catPostres->id, 'idioma' => 'en'],
            ['nombre' => 'Desserts & Coffee', 'descripcion' => 'Traditional Italian sweet pastries and espresso']
        );

        // Product 6: Tiramisù
        $prodTiramisu = Producto::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'categoria_id' => $catPostres->id, 'orden' => 1],
            [
                'precio' => 95.00,
                'precio_oferta' => null,
                'disponible' => true,
                'visible' => true,
                'destacado' => true,
            ]
        );
        ProductoTraduccion::firstOrCreate(
            ['producto_id' => $prodTiramisu->id, 'idioma' => 'es'],
            [
                'nombre' => 'Tiramisù Clásico',
                'descripcion' => 'Bizcochos savoiardi bañados en café espresso y crema de mascarpone.',
            ]
        );
        ProductoTraduccion::firstOrCreate(
            ['producto_id' => $prodTiramisu->id, 'idioma' => 'en'],
            [
                'nombre' => 'Classic Tiramisu',
                'descripcion' => 'Savoiardi ladyfingers soaked in espresso coffee with mascarpone cream.',
            ]
        );
        if (isset($tags['vegetariano'], $tags['recomendado'])) {
            $prodTiramisu->etiquetas()->syncWithoutDetaching([
                $tags['vegetariano']->id,
                $tags['recomendado']->id,
            ]);
        }

        // 7. Contacts
        Contacto::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'tipo' => 'telefono', 'orden' => 1],
            ['etiqueta' => 'Reservaciones', 'valor' => '+525512345678', 'visible' => true]
        );
        Contacto::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'tipo' => 'whatsapp', 'orden' => 2],
            ['etiqueta' => 'Pedidos WhatsApp', 'valor' => '5215512345678', 'visible' => true]
        );
        Contacto::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'tipo' => 'instagram', 'orden' => 3],
            ['etiqueta' => 'Síguenos', 'valor' => 'https://instagram.com/latrattoriademo', 'visible' => true]
        );
        Contacto::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'tipo' => 'direccion', 'orden' => 4],
            ['etiqueta' => 'Sucursal Centro', 'valor' => 'Av. Paseo de la Reforma 123, Juárez, CDMX', 'visible' => true]
        );

        // 8. Schedules
        // Days 1..5: Shift 1 (12:00-17:00), Shift 2 (19:00-01:00 overnight)
        for ($dia = 1; $dia <= 5; $dia++) {
            Horario::withoutGlobalScopes()->firstOrCreate(
                ['restaurante_id' => $restaurante->id, 'dia_semana' => $dia, 'turno' => 1],
                ['abre' => '12:00:00', 'cierra' => '17:00:00', 'cerrado' => false]
            );
            Horario::withoutGlobalScopes()->firstOrCreate(
                ['restaurante_id' => $restaurante->id, 'dia_semana' => $dia, 'turno' => 2],
                ['abre' => '19:00:00', 'cierra' => '01:00:00', 'cerrado' => false]
            );
        }

        // Saturday (Day 6): 13:00-02:00 overnight
        Horario::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'dia_semana' => 6, 'turno' => 1],
            ['abre' => '13:00:00', 'cierra' => '02:00:00', 'cerrado' => false]
        );

        // Sunday (Day 0): Closed
        Horario::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'dia_semana' => 0, 'turno' => 1],
            ['abre' => null, 'cierra' => null, 'cerrado' => true]
        );

        // 9. QR Codes
        CodigoQr::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'codigo' => 'demo-gral'],
            ['nombre' => 'General', 'tipo' => 'general', 'mesa' => null, 'activo' => true]
        );
        CodigoQr::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'codigo' => 'demo-m01'],
            ['nombre' => 'Mesa 1', 'tipo' => 'mesa', 'mesa' => '1', 'activo' => true]
        );
        CodigoQr::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'codigo' => 'demo-m02'],
            ['nombre' => 'Mesa 2', 'tipo' => 'mesa', 'mesa' => '2', 'activo' => true]
        );

        // 10. Invitations
        Invitacion::withoutGlobalScopes()->firstOrCreate(
            ['restaurante_id' => $restaurante->id, 'email' => 'nuevo_editor@demo.com'],
            [
                'rol' => 'editor',
                'token' => 'demo_token_hash_example_64_characters_long_0123456789abcdef01234567',
                'invitado_por' => $propietario->id,
                'expira_en' => now()->addDays(7),
                'aceptada_en' => null,
            ]
        );

        // 11. Initial Audit Log
        HistorialCambio::withoutGlobalScopes()->firstOrCreate(
            [
                'restaurante_id' => $restaurante->id,
                'entidad' => 'restaurante',
                'entidad_id' => $restaurante->id,
                'accion' => 'crear',
            ],
            [
                'usuario_id' => $superadmin->id,
                'cambios' => [
                    'nombre' => ['antes' => null, 'despues' => 'La Trattoria Demo'],
                    'slug' => ['antes' => null, 'despues' => 'la-trattoria-demo'],
                ],
            ]
        );
    }
}
