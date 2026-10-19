<?php

namespace Tests\Feature;

use App\Models\Etiqueta;
use App\Models\Restaurante;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EtiquetaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoRestauranteSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_etiqueta_seeder_crea_el_catalogo_fijo_sin_duplicar(): void
    {
        $this->seed(EtiquetaSeeder::class);
        $this->seed(EtiquetaSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['vegano', 'vegetariano', 'picante', 'sin_gluten', 'sin_lactosa', 'nuevo', 'recomendado'],
            Etiqueta::pluck('codigo')->all(),
        );
    }

    public function test_seeder_demo_crea_un_restaurante_completo_y_es_repetible(): void
    {
        Storage::fake('public');

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $restaurante = Restaurante::sole();

        $this->assertTrue($restaurante->isActivo());
        $this->assertEqualsCanonicalizing(['es', 'en'], $restaurante->idiomas->pluck('idioma')->all());
        $this->assertTrue($restaurante->productos()->whereNotNull('precio_oferta')->exists());
        $this->assertTrue($restaurante->productos()->where('disponible', false)->exists());
        $this->assertTrue($restaurante->productos()->has('variantes')->has('etiquetas')->exists());
        $this->assertSame(2, $restaurante->horarios()->where('dia_semana', 2)->count());
        $this->assertEqualsCanonicalizing([null, '5'], $restaurante->codigosQr->pluck('mesa')->all());
        $this->assertEqualsCanonicalizing(
            ['propietario', 'editor'],
            $restaurante->usuarios->pluck('pivot.rol')->all(),
        );
        $this->assertTrue(User::where('email', 'superadmin@menuonl.test')->sole()->isSuperadmin());
    }
}
