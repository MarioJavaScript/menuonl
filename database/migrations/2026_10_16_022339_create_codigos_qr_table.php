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
        Schema::create('codigos_qr', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurante_id')->constrained('restaurantes')->cascadeOnDelete();
            $table->string('nombre', 80)->nullable();
            $table->string('codigo', 16)->unique();
            $table->enum('tipo', ['general', 'mesa'])->default('general');
            $table->string('mesa', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('contador_escaneos')->default(0);
            $table->dateTime('ultimo_escaneo_en')->nullable();
            $table->timestamps();

            $table->index('restaurante_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('codigos_qr');
    }
};
