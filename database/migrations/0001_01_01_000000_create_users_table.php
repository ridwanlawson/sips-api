<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Contoh menghapus sequence jika ada
        // DB::statement("BEGIN
        //     FOR rec IN (
        //         SELECT sequence_name FROM all_sequences WHERE sequence_owner = 'SIPSMOBILE'
        //     ) LOOP
        //         EXECUTE IMMEDIATE 'DROP SEQUENCE SIPSMOBILE.' || rec.sequence_name;
        //     END LOOP;
        // END;");

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 75)->unique();
            $table->string('fullname', 100);
            $table->string('email', 100)->unique()->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('fcba', 10);
            $table->string('afdeling', 20)->nullable();
            $table->string('gangcode', 20)->nullable();
            $table->string('idkaryawan', 50)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('level', 10)->nullable();
            $table->string('position', 50)->nullable();
            $table->string('photo')->nullable();
            $table->string('status')->nullable();
            $table->string('updated_by')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
