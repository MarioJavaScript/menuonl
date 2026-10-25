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
     * El correo de la invitación sale por la cola: estas columnas dicen si ya salió o si falló, para que el superadmin
     * lo vea y pueda reenviarla. Las invitaciones que ya existían se dan por enviadas (se mandaron de forma sincrónica).
     */
    public function up(): void
    {
        Schema::table('invitaciones', function (Blueprint $table) {
            $table->dateTime('enviada_en')->nullable()->after('aceptada_en');
            $table->dateTime('envio_fallido_en')->nullable()->after('enviada_en');
            $table->string('envio_error', 255)->nullable()->after('envio_fallido_en');
        });

        DB::table('invitaciones')->update(['enviada_en' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitaciones', function (Blueprint $table) {
            $table->dropColumn(['enviada_en', 'envio_fallido_en', 'envio_error']);
        });
    }
};
