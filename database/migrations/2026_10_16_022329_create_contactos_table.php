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
        Schema::create('contactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurante_id')->constrained('restaurantes')->cascadeOnDelete();
            $table->enum('tipo', ['telefono', 'whatsapp', 'email', 'direccion', 'instagram', 'facebook', 'tiktok', 'web', 'otro']);
            $table->string('etiqueta', 80)->nullable();
            $table->string('valor', 255);
            $table->integer('orden')->default(0);
            $table->boolean('visible')->default(true);
            $table->timestamps();

            $table->index(['restaurante_id', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contactos');
    }
};
