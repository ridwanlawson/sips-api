<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_json_files', function (Blueprint $table) {
            $table->id();
            $table->string('activity');
            $table->string('fcba');
            $table->string('afdeling');
            $table->date('tanggal');
            $table->string('year', 4);
            $table->string('month', 2);
            $table->string('date', 2);
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size');
            $table->string('url');
            $table->string('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['activity', 'fcba', 'afdeling', 'year', 'month', 'date'], 'idx_backup_act_fcba_afd');
            $table->index(['fcba', 'afdeling'], 'idx_backup_fcba_afd');
            $table->index('tanggal', 'idx_backup_tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_json_files');
    }
};
