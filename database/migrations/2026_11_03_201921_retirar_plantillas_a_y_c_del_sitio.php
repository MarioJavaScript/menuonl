<?php

use App\Services\RetiraPlantillasDelSitio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retira las plantillas del sitio "a" (Claro y sencillo) y "c" (Clásico y cálido). Primero, en una transacción, los
     * restaurantes que las usaban pasan a "b" (Oscuro elegante) con su fila en el historial (App\Services\RetiraPlantillasDelSitio).
     * Después, fuera de la transacción porque en MariaDB un ALTER la cerraría, el valor por defecto de la columna pasa a "b".
     */
    public function up(): void
    {
        app(RetiraPlantillasDelSitio::class)(['a', 'c'], 'b');

        Schema::table('restaurantes', function (Blueprint $table) {
            $table->string('sitio_plantilla', 1)->default('b')->change();
        });
    }

    /**
     * Irreversible a propósito: las vistas y estilos de "a" y "c" se borraron junto con esta migración, así que regresar
     * los restaurantes a esas plantillas los dejaría sin sitio. Solo se devuelve el valor por defecto de la columna. Si
     * hiciera falta, la plantilla anterior de cada restaurante queda en `historial_cambios` (cambios.sitio_plantilla.antes).
     */
    public function down(): void
    {
        Schema::table('restaurantes', function (Blueprint $table) {
            $table->string('sitio_plantilla', 1)->default('a')->change();
        });
    }
};
