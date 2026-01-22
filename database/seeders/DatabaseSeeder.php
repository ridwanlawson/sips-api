<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Device;
use App\Models\Ancak;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        // Device::factory(10)->create();
        Ancak::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        // Ambil kolom spesifik dari IPLASPROD
        // $employees = DB::connection('sips_production')->table('employee')
        //     ->select('FCCODE','FCNAME','SECTIONNAME','GANGCODE','FCBA') // Ganti dengan kolom yang diinginkan
        //     ->whereNull('DATETERMINATE')
        //     ->get();

        // // Insert ke SIPSMOBILE dengan pemetaan kolom
        // foreach ($employees as $employee) {
        //     DB::table('employee')->insert([
        //         'FCCODE' => $employee->fccode, // Pemetaan kolom
        //         'FCNAME' => $employee->fcname,
        //         'SECTIONNAME' => $employee->sectionname,
        //         'GANGCODE' => $employee->gangcode,
        //         'FCBA' => $employee->fcba,
        //         'CREATED_BY' => 'SYSTEM',
        //         'CREATED_AT' => Now(),
        //         // Tambahkan pemetaan lain sesuai kebutuhan
        //     ]);
        // }
    }
}
