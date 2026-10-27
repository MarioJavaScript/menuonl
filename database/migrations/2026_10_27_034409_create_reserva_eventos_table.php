<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who created, confirmed, cancelled or moved a reservation, and when. No guest data (name, phone, note, party size).
     * `reserva_id` has no foreign key on purpose: the history outlives a deleted reservation.
     */
    public function up(): void
    {
        Schema::create('reserva_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurante_id')->constrained('restaurantes')->cascadeOnDelete();
            $table->unsignedBigInteger('reserva_id');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 20);
            $table->string('origen', 10);
            $table->string('estado_antes', 20)->nullable();
            $table->string('estado_despues', 20)->nullable();
            $table->string('hora_antes', 5)->nullable();
            $table->string('hora_despues', 5)->nullable();
            $table->date('fecha_antes')->nullable();
            $table->date('fecha_despues')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['reserva_id', 'created_at']);
            $table->index(['restaurante_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_eventos');
    }
};
