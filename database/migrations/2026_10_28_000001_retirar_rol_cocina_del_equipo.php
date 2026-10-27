<?php

use App\Services\ReasignaRolRetirado;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retira el rol "cocina": no habrá pedidos de mesa. Primero se reasignan las filas (si no, MariaDB estricto rechaza
     * reducir el enum y SQLite no puede copiar las filas al CHECK nuevo) y después se reducen los dos enums.
     *
     * Las membresías con ese rol quedan como "recepcion" desactivadas; las invitaciones sin aceptar se borran y las
     * aceptadas pasan a "recepcion" (ver App\Services\ReasignaRolRetirado).
     */
    public function up(): void
    {
        app(ReasignaRolRetirado::class)('cocina', 'recepcion');

        Schema::table('restaurante_usuario', function (Blueprint $table) {
            $table->enum('rol', ['propietario', 'editor', 'gerente', 'recepcion'])->default('editor')->change();
        });

        Schema::table('invitaciones', function (Blueprint $table) {
            $table->enum('rol', ['propietario', 'editor', 'gerente', 'recepcion'])->default('editor')->change();
        });
    }

    /**
     * Vuelve a aceptar "cocina" en los enums. No recupera las filas reasignadas ni las invitaciones borradas.
     */
    public function down(): void
    {
        Schema::table('restaurante_usuario', function (Blueprint $table) {
            $table->enum('rol', ['propietario', 'editor', 'gerente', 'recepcion', 'cocina'])->default('editor')->change();
        });

        Schema::table('invitaciones', function (Blueprint $table) {
            $table->enum('rol', ['propietario', 'editor', 'gerente', 'recepcion', 'cocina'])->default('editor')->change();
        });
    }
};
