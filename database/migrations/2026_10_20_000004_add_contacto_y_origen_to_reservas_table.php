<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phone of the guest and where the reservation came from (panel or public site). Existing rows read as "panel".
     */
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->string('telefono', 20)->nullable()->after('personas');
            $table->string('origen', 10)->default('panel')->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn(['telefono', 'origen']);
        });
    }
};
