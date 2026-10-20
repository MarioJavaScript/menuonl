<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contenido editable del sitio del restaurante (/sitio/{slug}): una fila por restaurante.
     * Los límites de longitud son los de la validación del panel (SitioWebService).
     */
    public function up(): void
    {
        Schema::create('sitio_configuraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurante_id')->unique()->constrained('restaurantes')->cascadeOnDelete();
            $table->string('hero_imagen', 255)->nullable();
            $table->string('hero_imagen_creditos', 150)->nullable();
            $table->string('hero_subtitulo', 160)->nullable();
            $table->string('historia_etiqueta', 30)->nullable();
            $table->string('historia_titulo', 80)->nullable();
            $table->text('historia_parrafo_1')->nullable();
            $table->text('historia_parrafo_2')->nullable();
            $table->string('historia_imagen', 255)->nullable();
            $table->string('historia_imagen_creditos', 150)->nullable();
            $table->text('estadisticas')->nullable();
            $table->boolean('reservas_activas')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitio_configuraciones');
    }
};
