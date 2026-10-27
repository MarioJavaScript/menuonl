<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retira el tablero de pedidos manuales (no habrá pedidos de mesa). Ninguna otra tabla apunta a "pedidos".
     * Los pedidos por WhatsApp del menú público no se guardan aquí: son un enlace wa.me.
     */
    public function up(): void
    {
        Schema::dropIfExists('pedidos');
    }

    /**
     * Recrea la tabla con la estructura de 2026_10_19_202033_create_pedidos_table (sin los datos).
     */
    public function down(): void
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
};
