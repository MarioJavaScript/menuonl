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
        // Enrich users table with system flags
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('es_superadmin')->default(false)->after('password');
            $table->boolean('activo')->default(true)->after('es_superadmin');
            $table->dateTime('ultimo_acceso')->nullable()->after('activo');
        });

        // Restaurantes table
        Schema::create('restaurantes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('slug', 80)->unique();
            $table->string('descripcion', 500)->nullable();
            $table->string('logo', 255)->nullable();
            $table->string('portada', 255)->nullable();
            $table->char('moneda', 3)->default('MXN');
            $table->string('idioma_default', 5)->default('es');
            $table->string('zona_horaria', 50)->default('America/Mexico_City');
            $table->string('whatsapp_pedidos', 20)->nullable();
            $table->boolean('pedidos_activos')->default(false);
            $table->boolean('mostrar_agotados')->default(true);
            $table->enum('estado', ['activo', 'suspendido'])->default('activo')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Pivot: restaurante_usuario
        Schema::create('restaurante_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurante_id')->constrained('restaurantes')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->enum('rol', ['propietario', 'editor'])->default('editor');
            $table->timestamps();

            $table->unique(['restaurante_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurante_usuario');
        Schema::dropIfExists('restaurantes');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['es_superadmin', 'activo', 'ultimo_acceso']);
        });
    }
};
