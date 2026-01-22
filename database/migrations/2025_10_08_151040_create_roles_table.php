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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();   // EM, ASISTEN, MANDOR1, MD.PANEN, KR.PANEN, KR.TRANS
            $table->string('name', 100);
            $table->timestamps();
        });


        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamp('starts_at');          // kapan mulai berlaku
            $table->timestamp('ends_at')->nullable(); // null = masih aktif hingga diganti/diakhiri
            $table->json('meta')->nullable();        // catatan opsional (surat tugas, dll)
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Optimasi pencarian “yang aktif”
            $table->index(['user_id', 'starts_at']);
            $table->index(['user_id', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
        Schema::dropIfExists('user_roles');
    }
};
