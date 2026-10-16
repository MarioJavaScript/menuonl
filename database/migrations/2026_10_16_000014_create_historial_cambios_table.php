<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('historial_cambios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurante_id')->constrained('restaurantes')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entidad', 50);
            $table->unsignedBigInteger('entidad_id');
            $table->enum('accion', ['crear', 'actualizar', 'eliminar']);
            $table->json('cambios')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['restaurante_id', 'created_at']);
            $table->index(['entidad', 'entidad_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_cambios');
    }
};
