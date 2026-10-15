<?php

namespace Tests\Feature;

use App\Models\Restaurante;
use App\Models\Scopes\RestauranteScope;
use App\Models\User;
use App\Traits\BelongsToRestaurante;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Dummy model to test the BelongsToRestaurante trait and scope
class DummyItem extends Model
{
    use BelongsToRestaurante;

    protected $table = 'dummy_items';

    protected $fillable = ['nombre', 'restaurante_id'];

    public $timestamps = false;
}

class RestauranteAislamientoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('dummy_items', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->foreignId('restaurante_id');
        });
    }

    public function test_usuario_puede_vincularse_a_restaurante_con_rol(): void
    {
        $restaurante = Restaurante::create([
            'nombre' => 'Taquería La Esquina',
            'slug' => 'la-esquina',
        ]);

        $usuario = User::create([
            'name' => 'Mario',
            'email' => 'mario@example.com',
            'password' => 'secret123',
            'activo' => true,
        ]);

        $usuario->restaurantes()->attach($restaurante->id, ['rol' => 'propietario']);

        $this->assertCount(1, $usuario->restaurantes);
        $panel = (new Panel)->id('admin');
        $this->assertTrue($usuario->canAccessPanel($panel));
    }

    public function test_global_scope_aisla_registros_por_restaurante(): void
    {
        $restaurante1 = Restaurante::create(['nombre' => 'Rest 1', 'slug' => 'rest-1']);
        $restaurante2 = Restaurante::create(['nombre' => 'Rest 2', 'slug' => 'rest-2']);

        DummyItem::withoutGlobalScopes()->create([
            'nombre' => 'Taco de Asada',
            'restaurante_id' => $restaurante1->id,
        ]);

        DummyItem::withoutGlobalScopes()->create([
            'nombre' => 'Pizza Margarita',
            'restaurante_id' => $restaurante2->id,
        ]);

        // When scoped to restaurant 1
        RestauranteScope::setRestauranteId($restaurante1->id);
        $items = DummyItem::all();
        $this->assertCount(1, $items);
        $this->assertEquals('Taco de Asada', $items->first()->nombre);

        // When scoped to restaurant 2
        RestauranteScope::setRestauranteId($restaurante2->id);
        $items = DummyItem::all();
        $this->assertCount(1, $items);
        $this->assertEquals('Pizza Margarita', $items->first()->nombre);

        // Reset scope
        RestauranteScope::setRestauranteId(null);
    }
}
