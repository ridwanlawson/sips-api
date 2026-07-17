<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nfc_cards', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique();
            $table->string('card_id')->nullable();
            $table->string('ownership')->nullable();
            $table->string('status')->default('Y');
            $table->text('notes')->nullable();
            $table->string('fcba')->nullable();
            $table->string('afdeling')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfc_cards');
    }
};
