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
        Schema::table('maps', function (Blueprint $table) {
            // Tambah kolom type_map
            $table->enum('type_map', ['JALAN', 'BLOK', 'ANCAK'])->nullable()->after('geojson');

            // Drop kolom lama
            if (Schema::hasColumn('maps', 'fcba')) {
                $table->dropColumn('fcba');
            }
            if (Schema::hasColumn('maps', 'sectionname')) {
                $table->dropColumn('sectionname');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maps', function (Blueprint $table) {
            // Kembalikan kolom lama
            $table->string('fcba', 10)->nullable()->after('geojson');
            $table->string('sectionname', 20)->nullable()->after('fcba');

            // Drop kolom baru
            if (Schema::hasColumn('maps', 'type_map')) {
                $table->dropColumn('type_map');
            }
        });
    }
};
