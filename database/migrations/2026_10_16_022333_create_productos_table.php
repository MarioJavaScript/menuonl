<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurante_id')->constrained('restaurantes')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $table->decimal('precio', 10, 2)->default(0);
            $table->decimal('precio_oferta', 10, 2)->nullable();
            $table->string('imagen', 255)->nullable();
            $table->boolean('disponible')->default(true);
            $table->boolean('visible')->default(true);
            $table->boolean('destacado')->default(false);
            $table->integer('orden')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['restaurante_id', 'orden']);
            $table->index(['categoria_id', 'orden']);
        });

        // SQLite cannot add CHECK constraints to an existing table; the model enforces the same rules.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE productos ADD CONSTRAINT ck_productos_precio CHECK (precio >= 0)');
            DB::statement('ALTER TABLE productos ADD CONSTRAINT ck_productos_oferta CHECK (precio_oferta IS NULL OR precio_oferta >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
