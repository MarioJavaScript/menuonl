<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plan de venta del restaurante (App\Enums\PlanRestaurante). El valor por defecto `completo`
     * llena solo las filas existentes: ningún restaurante cambia de comportamiento ni se reescriben datos.
     */
    public function up(): void
    {
        Schema::table('restaurantes', function (Blueprint $table) {
            $table->string('plan', 20)->default('completo')->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('restaurantes', function (Blueprint $table) {
            $table->dropColumn('plan');
        });
    }
};
