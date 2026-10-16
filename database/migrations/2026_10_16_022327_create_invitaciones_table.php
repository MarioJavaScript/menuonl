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
        Schema::create('invitaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurante_id')->constrained('restaurantes')->cascadeOnDelete();
            $table->string('email', 190)->index();
            $table->enum('rol', ['propietario', 'editor'])->default('editor');
            $table->string('token', 64)->unique();
            $table->foreignId('invitado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('expira_en');
            $table->dateTime('aceptada_en')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitaciones');
    }
};
