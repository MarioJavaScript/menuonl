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
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurante_id')->constrained('restaurantes')->cascadeOnDelete();
            $table->string('mesa', 20);
            $table->string('detalle', 255);
            $table->decimal('total', 10, 2)->default(0);
            $table->enum('estado', ['nuevo', 'cocina', 'listo', 'entregado'])->default('nuevo');
            $table->timestamp('entregado_at')->nullable();
            $table->timestamps();

            $table->index(['restaurante_id', 'estado']);
            $table->index(['restaurante_id', 'entregado_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
