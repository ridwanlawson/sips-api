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
        Schema::create('app_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('platform'); // android, ios
            $table->string('version');
            $table->string('min_version')->nullable();
            $table->boolean('force_update')->default(false);
            $table->string('file_name');
            $table->string('file_path');
            $table->bigInteger('file_size');
            $table->string('file_extension');
            $table->text('changelog')->nullable();
            $table->string('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['platform', 'version']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_uploads');
    }
};
