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
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurante_id')->constrained('restaurantes')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana')->comment('0=domingo ... 6=sabado');
            $table->unsignedTinyInteger('turno')->default(1);
            $table->time('abre')->nullable();
            $table->time('cierra')->nullable();
            $table->boolean('cerrado')->default(false);
            $table->timestamps();

            $table->index(['restaurante_id', 'dia_semana']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horarios');
    }
};
