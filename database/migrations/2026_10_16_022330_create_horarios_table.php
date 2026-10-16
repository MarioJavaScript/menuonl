<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->unsignedTinyInteger('dia_semana')->comment('0=domingo ... 6=sábado');
            $table->time('abre')->nullable();
            $table->time('cierra')->nullable();
            $table->boolean('cerrado')->default(false);

            $table->index(['restaurante_id', 'dia_semana']);
        });

        // SQLite cannot add CHECK constraints to an existing table; the model enforces the same rule.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE horarios ADD CONSTRAINT ck_horarios_dia CHECK (dia_semana BETWEEN 0 AND 6)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horarios');
    }
};
