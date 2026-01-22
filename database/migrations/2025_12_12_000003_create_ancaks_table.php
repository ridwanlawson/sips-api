<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ancaks', function (Blueprint $table) {
            $table->id();
            $table->string('fcba')->nullable();
            $table->string('afdeling')->nullable();
            $table->string('fieldcode')->nullable();
            $table->string('noancak')->nullable();
            $table->decimal('luas', 10, 2)->nullable();
            $table->unsignedBigInteger('tph_id')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            // Indexes untuk query yang efficient
            $table->index('fcba');
            $table->index('afdeling');
            $table->index('fieldcode');
            $table->index('noancak');
            $table->index('status');
            $table->index('tph_id');

            // Foreign key ke tph table (jika diperlukan - opsional untuk flexibility)
            // $table->foreign('tph_id')->references('id')->on('tph')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ancaks');
    }
};
