<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Operational roles (gerente, recepcion, cocina) next to propietario and editor, and a per-restaurant
     * switch to deactivate a member without touching the account. Existing rows keep their values.
     */
    public function up(): void
    {
        Schema::table('restaurante_usuario', function (Blueprint $table) {
            $table->enum('rol', ['propietario', 'editor', 'gerente', 'recepcion', 'cocina'])->default('editor')->change();
            $table->boolean('activo')->default(true)->after('rol');
        });

        Schema::table('invitaciones', function (Blueprint $table) {
            $table->enum('rol', ['propietario', 'editor', 'gerente', 'recepcion', 'cocina'])->default('editor')->change();
        });
    }

    /**
     * Only the added column is dropped: narrowing the enums back could reject rows already using the new roles.
     */
    public function down(): void
    {
        Schema::table('restaurante_usuario', function (Blueprint $table) {
            $table->dropColumn('activo');
        });
    }
};
