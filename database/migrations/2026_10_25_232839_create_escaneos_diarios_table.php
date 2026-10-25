<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Un agregado por (código, día, tipo): solo un contador. Sin created_at/updated_at a propósito, para que la tabla no
     * guarde la hora de ningún escaneo, ni IP, user-agent o cookies. La fecha es el día local del restaurante.
     */
    public function up(): void
    {
        Schema::create('escaneos_diarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurante_id')->constrained('restaurantes')->cascadeOnDelete();
            $table->foreignId('codigo_qr_id')->constrained('codigos_qr')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('tipo', 3);
            $table->unsignedInteger('conteo')->default(0);

            $table->unique(['codigo_qr_id', 'fecha', 'tipo'], 'uq_escaneos_diarios_codigo_fecha_tipo');
            $table->index(['restaurante_id', 'fecha'], 'ix_escaneos_diarios_restaurante_fecha');
            $table->index('fecha', 'ix_escaneos_diarios_fecha');
        });

        // SQLite cannot add CHECK constraints to an existing table; the model enum enforces the same values.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE escaneos_diarios ADD CONSTRAINT ck_escaneos_diarios_tipo CHECK (tipo IN ('qr', 'nfc'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escaneos_diarios');
    }
};
