<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Apariencia de la página pública: tema, color de acento y eslogan.
     */
    public function up(): void
    {
        Schema::table('restaurantes', function (Blueprint $table) {
            $table->string('tema', 20)->default('calido')->after('portada');
            $table->string('color_acento', 7)->nullable()->after('tema');
            $table->string('eslogan', 120)->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('restaurantes', function (Blueprint $table) {
            $table->dropColumn(['tema', 'color_acento', 'eslogan']);
        });
    }
};
