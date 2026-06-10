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
        Schema::create('attendance_gad', function (Blueprint $table) {
            $table->string('documentno', 100)->nullable();
            $table->string('gangcode', 30);
            $table->date('fddate');
            $table->string('supervision_1', 30)->nullable();
            $table->string('supervision_2', 30)->nullable();
            $table->string('supervision_3', 30)->nullable();
            $table->string('supervision_4', 30)->nullable();
            $table->string('supervision_5', 30)->nullable();
            $table->string('employeecode', 30);
            $table->string('attendance', 30);
            $table->string('jobcode', 30);
            $table->string('locationtype', 50);
            $table->string('locationcode', 50);
            $table->integer('mandays')->nullable();
            $table->integer('othrs')->nullable();
            $table->integer('rate')->nullable();
            $table->integer('unit')->nullable();
            $table->integer('output')->nullable();
            $table->string('reference', 70)->nullable();
            $table->string('remarks', 300)->nullable();
            $table->integer('overtime_hours')->nullable();
            $table->integer('type_overtime')->nullable();
            $table->string('chargejob', 30)->nullable();
            $table->string('chargetype', 30)->nullable();
            $table->string('chargecode', 30)->nullable();
            $table->integer('bucket')->nullable();
            $table->string('spbno', 50)->nullable();
            $table->integer('kg_janjang')->nullable();
            $table->integer('kg_brondolan')->nullable();
            $table->string('rowstate', 30)->nullable();
            $table->string('document_classification', 30)->nullable();
            $table->integer('basis_bm')->nullable();
            $table->integer('bjr')->nullable();
            $table->integer('linenokey')->nullable();
            $table->string('fcentry', 30)->nullable();
            $table->string('fcedit', 30)->nullable();
            $table->string('fcip', 30)->nullable();
            $table->string('fcba', 30);
            $table->date('lastupdate');
            $table->string('lasttime', 5);
            $table->string('lastapproval', 30)->nullable();
        });

        Schema::create('harvestingspb', function (Blueprint $table) {
            $table->string('spbno', 50);
            $table->string('fieldcode', 15)->nullable();
            $table->date('receptiondate')->nullable();
            $table->date('harvestdate')->nullable();
            $table->string('cropcode', 15)->nullable();
            $table->string('productcode', 15)->nullable();
            $table->string('own', 50)->nullable();
            $table->string('vehicle', 50)->nullable();
            $table->string('driver', 50)->nullable();
            $table->string('mill', 100)->nullable();
            $table->string('agreementcode', 50)->nullable();
            $table->string('transporttype', 50)->nullable();
            $table->string('spb_type', 50)->nullable();
            $table->integer('bunch')->nullable();
            $table->integer('bucket')->nullable();
            $table->integer('pressemester_abw')->nullable();
            $table->integer('bunch_estateweight')->nullable();
            $table->string('chitno', 30)->nullable();
            $table->integer('mill_weight_bruto')->nullable();
            $table->integer('mill_weight_gross')->nullable();
            $table->integer('mill_weight_tarra')->nullable();
            $table->integer('mill_weight_potongan')->nullable();
            $table->integer('mill_weight_netto')->nullable();
            $table->integer('mentah')->nullable();
            $table->integer('tankos')->nullable();
            $table->integer('hilang')->nullable();
            $table->string('keterangan', 30)->nullable();
            $table->integer('mill_weight_dtl')->nullable();
            $table->integer('bjr_chit')->nullable();
            $table->string('fcentry', 30)->nullable();
            $table->string('fcedit', 30)->nullable();
            $table->string('fcip', 30)->nullable();
            $table->string('fcba', 30)->nullable();
            $table->date('lastupdate');
            $table->string('lasttime', 5);
            $table->string('lastapproval', 30)->nullable();
        });

        Schema::create('harvestingspb', function (Blueprint $table) {
            $table->string('spbno', 50);
            $table->string('fieldcode', 15)->nullable();
            $table->date('receptiondate')->nullable();
            $table->date('harvestdate')->nullable();
            $table->string('cropcode', 15)->nullable();
            $table->string('productcode', 15)->nullable();
            $table->string('own', 50)->nullable();
            $table->string('vehicle', 50)->nullable();
            $table->string('driver', 50)->nullable();
            $table->string('mill', 100)->nullable();
            $table->string('agreementcode', 50)->nullable();
            $table->string('transporttype', 50)->nullable();
            $table->string('spb_type', 50)->nullable();
            $table->integer('bunch')->nullable();
            $table->integer('bucket')->nullable();
            $table->integer('pressemester_abw')->nullable();
            $table->integer('bunch_estateweight')->nullable();
            $table->string('chitno', 30)->nullable();
            $table->integer('mill_weight_bruto')->nullable();
            $table->integer('mill_weight_gross')->nullable();
            $table->integer('mill_weight_tarra')->nullable();
            $table->integer('mill_weight_potongan')->nullable();
            $table->integer('mill_weight_netto')->nullable();
            $table->integer('mentah')->nullable();
            $table->integer('tankos')->nullable();
            $table->integer('hilang')->nullable();
            $table->string('keterangan', 30)->nullable();
            $table->integer('mill_weight_dtl')->nullable();
            $table->integer('bjr_chit')->nullable();
            $table->string('fcentry', 30)->nullable();
            $table->string('fcedit', 30)->nullable();
            $table->string('fcip', 30)->nullable();
            $table->string('fcba', 30)->nullable();
            $table->date('lastupdate');
            $table->string('lasttime', 5);
            $table->string('lastapproval', 30)->nullable();
        });

        Schema::create('harvestingquality', function (Blueprint $table) {
            $table->string('documentno')->nullable();
            $table->string('empcode', 30);
            $table->date('fddate');
            $table->string('fieldcode', 30);
            $table->number('under_ripe')->nullable();
            $table->number('over_ripe')->nullable();
            $table->number('abnormal')->nullable();
            $table->number('long_stalk')->nullable();
            $table->number('eaten_by_rat')->nullable();
            $table->number('unharvest_ffb')->nullable();
            $table->number('uncollect_lf_circle')->nullable();
            $table->number('uncollect_lf_piece')->nullable();
            $table->number('unarrange_ffb')->nullable();
            $table->number('unprune_frond')->nullable();
            $table->number('qe_1')->nullable();
            $table->number('qe_2')->nullable();
            $table->number('qe_3')->nullable();
            $table->number('qe_4')->nullable();
            $table->number('qe_5')->nullable();
            $table->number('qe_6')->nullable();
            $table->number('qe_7')->nullable();
            $table->number('qe_8')->nullable();
            $table->number('qe_9')->nullable();
            $table->number('qe_10')->nullable();
            $table->number('qe_11')->nullable();
            $table->number('qe_12')->nullable();
            $table->number('qe_13')->nullable();
            $table->number('qe_14')->nullable();
            $table->number('qe_15')->nullable();
            $table->number('qe_16')->nullable();
            $table->number('qe_17')->nullable();
            $table->string('fcentry', 30)->nullable();
            $table->string('fcedit', 30)->nullable();
            $table->string('fcip', 30)->nullable();
            $table->string('fcba', 30);
            $table->date('lastupdate');
            $table->string('lasttime', 5);
            $table->string('lastapproval')->nullable();
        });

        Schema::create('lhm_data', function (Blueprint $table) {
            $table->id();
            $table->integer('rowdata')->default(0);
            $table->string('kemandoran', 30);
            $table->date('fddate');
            $table->string('fcba', 10);
            $table->string('afdeling', 10);
            $table->string('employeecode', 30);
            $table->string('nama', 100);
            $table->string('attendance', 10);
            $table->integer('hk')->default(0);
            $table->string('blok', 30);
            $table->integer('tahuntanam')->default(0);
            $table->integer('jjg')->default(0);
            $table->integer('brd')->default(0);
            $table->integer('ha')->default(0);
            $table->integer('mentahqty')->default(0);
            $table->integer('mentahrp')->default(0);
            $table->integer('emptybunchqty')->default(0);
            $table->integer('emptybunchrp')->default(0);
            $table->integer('jumlahdenda')->default(0);
            $table->integer('totalalljjg')->default(0);
            $table->integer('basis')->default(0);
            $table->integer('rpbasis')->default(0);
            $table->integer('premilv1')->default(0);
            $table->integer('rate1')->default(0);
            $table->integer('rplv1')->default(0);
            $table->integer('premilv2')->default(0);
            $table->integer('rate2')->default(0);
            $table->integer('rplv2')->default(0);
            $table->integer('premilv3')->default(0);
            $table->integer('rate3')->default(0);
            $table->integer('rplv3')->default(0);
            $table->integer('totalrppremi')->default(0);
            $table->integer('kurangbasis')->default(0);
            $table->integer('harilibur')->default(0);
            $table->integer('totalbrd')->default(0);
            $table->integer('rate_brondolan')->default(0);
            $table->integer('rphk')->default(0);
            $table->integer('brd_rp')->default(0);
            $table->integer('total')->default(0);
            $table->string('fcentry');
            $table->string('fcedit');
            $table->string('fcip');
            $table->date('lastupdate');
            $table->string('lasttime');
            $table->string('lastapproval')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload');
    }
};
