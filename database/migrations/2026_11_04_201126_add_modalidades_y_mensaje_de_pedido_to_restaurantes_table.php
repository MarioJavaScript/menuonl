<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pedidos por WhatsApp: modalidades de entrega que el cliente puede elegir (domicilio y para recoger; ya no hay
     * pedidos de mesa) y un mensaje opcional del restaurante al final del pedido. Las dos modalidades nacen encendidas,
     * así que los restaurantes que ya reciben pedidos siguen igual. No reescribe datos.
     */
    public function up(): void
    {
        Schema::table('restaurantes', function (Blueprint $table) {
            $table->boolean('pedidos_domicilio')->default(true)->after('pedidos_activos');
            $table->boolean('pedidos_recoger')->default(true)->after('pedidos_domicilio');
            $table->string('pedidos_mensaje', 200)->nullable()->after('pedidos_recoger');
        });
    }

    public function down(): void
    {
        Schema::table('restaurantes', function (Blueprint $table) {
            $table->dropColumn(['pedidos_domicilio', 'pedidos_recoger', 'pedidos_mensaje']);
        });
    }
};
