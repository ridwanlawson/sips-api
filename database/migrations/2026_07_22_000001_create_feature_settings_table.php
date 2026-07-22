<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_settings', function (Blueprint $table) {
            $table->id();
            $table->string('menu', 50);
            $table->string('feature', 30);
            $table->string('fcba', 10)->nullable();
            $table->string('afdeling', 20)->nullable();
            $table->string('status', 1)->default('Y');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['menu', 'feature', 'fcba', 'afdeling'], 'feat_set_uniq');
            $table->index('menu');
            $table->index('fcba');
            $table->index('afdeling');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_settings');
    }
};
