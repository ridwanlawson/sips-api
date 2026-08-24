<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ATTENDANCE', function (Blueprint $table) {
            $table->string('IMAGES_OUT', 500)->nullable()->after('IMAGES');
        });
    }

    public function down(): void
    {
        Schema::table('ATTENDANCE', function (Blueprint $table) {
            $table->dropColumn('IMAGES_OUT');
        });
    }
};
