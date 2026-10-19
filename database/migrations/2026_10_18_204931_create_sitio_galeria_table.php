<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fotos de la galería del sitio (máximo 12 por restaurante, forzado en el modelo SitioGaleria).
     */
    public function up(): void
    {
        Schema::create('sitio_galeria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurante_id')->constrained('restaurantes')->cascadeOnDelete();
            $table->string('imagen', 255);
            $table->string('titulo', 60);
            $table->string('texto_alternativo', 160);
            $table->string('creditos', 150)->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->index(['restaurante_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitio_galeria');
    }
};
