<?php

use App\Services\RetiraElPlanCompleto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Planes Aura (`sitio_menu`) y Lite (`solo_menu`): se retira el plan "Completo". Primero, en una transacción, los
     * restaurantes en `completo` pasan a `sitio_menu` con su fila en el historial (App\Services\RetiraElPlanCompleto).
     * Después, fuera de la transacción porque en MariaDB un ALTER la cerraría, el valor por defecto de la columna pasa a
     * `sitio_menu`. Mientras el código nuevo convive con filas viejas, `completo` se sigue leyendo como Aura
     * (PlanRestaurante::desde()), así que ningún restaurante pierde su sitio durante el despliegue.
     */
    public function up(): void
    {
        app(RetiraElPlanCompleto::class)();

        Schema::table('restaurantes', function (Blueprint $table) {
            $table->string('plan', 20)->default('sitio_menu')->change();
        });
    }

    /**
     * Irreversible a propósito: el plan "Completo" ya no existe en el código (se lee como Aura), y no hay forma de saber
     * qué restaurantes en `sitio_menu` venían de `completo` sin leer el historial. Solo se devuelve el valor por defecto
     * de la columna. Si hiciera falta, el plan anterior de cada restaurante queda en `historial_cambios`
     * (cambios.plan.antes = "completo", sin usuario).
     */
    public function down(): void
    {
        Schema::table('restaurantes', function (Blueprint $table) {
            $table->string('plan', 20)->default('completo')->change();
        });
    }
};
