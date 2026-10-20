<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Public site template, map location and reservation capacity. Every column has a default,
     * so existing restaurants keep working untouched.
     */
    public function up(): void
    {
        Schema::table('restaurantes', function (Blueprint $table) {
            $table->string('sitio_plantilla', 1)->default('a')->after('color_acento');
            $table->decimal('latitud', 9, 6)->nullable()->after('sitio_plantilla');
            $table->decimal('longitud', 9, 6)->nullable()->after('latitud');
            $table->boolean('reservas_activas')->default(true)->after('longitud');
            $table->unsignedSmallInteger('reservas_cupo')->default(30)->after('reservas_activas');
            $table->unsignedSmallInteger('reservas_franja_min')->default(60)->after('reservas_cupo');
        });
    }

    public function down(): void
    {
        Schema::table('restaurantes', function (Blueprint $table) {
            $table->dropColumn(['sitio_plantilla', 'latitud', 'longitud', 'reservas_activas', 'reservas_cupo', 'reservas_franja_min']);
        });
    }
};
