<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('codigos_qr', function (Blueprint $table) {
            $table->string('tipo', 3)->default('qr')->after('mesa');
        });

        // SQLite cannot add CHECK constraints to an existing table; the model enum enforces the same values.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE codigos_qr ADD CONSTRAINT ck_codigos_qr_tipo CHECK (tipo IN ('qr', 'nfc'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE codigos_qr DROP CONSTRAINT ck_codigos_qr_tipo');
        }

        Schema::table('codigos_qr', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
