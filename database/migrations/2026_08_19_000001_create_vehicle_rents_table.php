<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('vehicle_rents', function (Blueprint $table) {
            $table->id();
            $table->string('contract_no')->nullable();
            $table->string('fcba')->nullable();
            $table->string('vehicle_code')->nullable();
            $table->string('vehicle_name')->nullable();
            $table->string('registration_no')->nullable();
            $table->string('nik')->nullable();
            $table->string('driver_name')->nullable();
            $table->date('tanggal')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index('contract_no');
            $table->index('vehicle_code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('vehicle_rents');
    }
};