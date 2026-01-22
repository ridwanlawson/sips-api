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
        Schema::create('employee', function (Blueprint $table) {
            $table->id();
            $table->string('fccode', 50);
            $table->string('fcname', 100);
            $table->string('sectionname', 20)->nullable();
            $table->string('gangcode', 20)->nullable();
            $table->string('fcba', 10)->nullable();
            $table->string('noancak', 10)->nullable();
            $table->string('photo')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('tph', function (Blueprint $table) {
            $table->id();
            $table->string('notph', 15);
            $table->string('fieldcode', 15);
            $table->string('ancakno', 10)->nullable();
            $table->string('afdeling', 10)->nullable();
            $table->string('fcba', 10)->nullable();
            $table->string('typetph', 30)->nullable();
            $table->string('status', 30)->nullable();
            $table->string('location')->nullable();
            $table->decimal('ha', 8, 2)->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('kode_karyawan_mandor', 50)->nullable();
            $table->string('kode_karyawan', 50);
            $table->date('time_in')->nullable();
            $table->string('location_in')->nullable();
            $table->date('time_out')->nullable();
            $table->string('location_out')->nullable();
            $table->string('pengancakan', 10)->nullable();
            $table->string('total_late_time', 5)->nullable();
            $table->string('go_home_early', 5)->nullable();
            $table->string('attendance_type', 20)->nullable();
            $table->string('exception_case')->nullable();
            $table->string('no_ba_exca')->nullable();
            $table->string('fcba', 10);
            $table->string('section', 20)->nullable();
            $table->string('gang', 20)->nullable();
            $table->string('attendance', 5);
            $table->decimal('mandays', 3, 2)->nullable();
            $table->string('status_attendance', 20)->nullable();
            $table->string('fcba_destination', 20)->nullable();
            $table->string('id_device', 100)->nullable();
            $table->string('mac_address', 50)->nullable();
            $table->string('images')->nullable();
            $table->string('flag', 5);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('harvesting', function (Blueprint $table) {
            $table->id();
            $table->string('nodokumen', 50);
            $table->date('tanggal');
            $table->string('kode_karyawan_mandor1', 50)->nullable();
            $table->string('kode_karyawan_mandor_panen', 50)->nullable();
            $table->string('kode_karyawan_kerani', 50)->nullable();
            $table->string('kode_karyawan', 50);
            $table->string('noancak', 10);
            $table->string('tph', 15)->nullable();
            $table->string('fieldcode', 15)->nullable();
            $table->integer('output');
            $table->integer('mentah');
            $table->integer('overripe');
            $table->integer('busuk');
            $table->integer('busuk2');
            $table->integer('buahkecil');
            $table->integer('parteno');
            $table->integer('brondol');
            $table->integer('alasbrondol');
            $table->integer('tangkaipanjang');
            $table->string('status_assistensi', 50)->nullable();
            $table->string('images')->nullable();
            $table->string('afdeling');
            $table->string('fcba');
            $table->string('id_device', 100)->nullable();
            $table->string('card_id', 100);
            $table->string('flag', 5);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('pengangkutan', function (Blueprint $table) {
            $table->id();
            $table->string('nopengangkutan', 50);
            $table->string('nospb', 50)->unique();
            $table->string('nodokumen', 50);
            $table->date('tanggal');
            $table->string('kode_karyawan_kerani', 50);
            $table->string('kode_karyawan_driver', 50);
            $table->string('tkbm1', 50);
            $table->string('tkbm2', 50)->nullable();
            $table->string('tkbm3', 50)->nullable();
            $table->string('tkbm4', 50)->nullable();
            $table->string('tkbm5', 50)->nullable();
            $table->string('type_pengangkutan', 30)->nullable();
            $table->string('kode_kendaraan', 15);
            $table->string('tph', 15)->nullable();
            $table->string('fieldcode', 15)->nullable();
            $table->integer('totaljanjang');
            $table->integer('output');
            $table->integer('janjangnormal');
            $table->integer('brondolan');
            $table->string('status_pengangkutan', 30)->nullable();
            $table->string('afdeling', 20);
            $table->string('fcba', 10);
            $table->string('pabrik_tujuan', 10);
            $table->string('images')->nullable();
            $table->string('card_id', 100);
            $table->string('flag', 5);
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee');
        Schema::dropIfExists('tph');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('harvesting');
        Schema::dropIfExists('pengangkutan');
    }
};
