<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MasterController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\TphController;
use App\Http\Controllers\Api\HarvestingController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\PengangkutanController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\ApiLogController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\AncakController;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1')->name('auth.register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:3,1')->name('auth.login');

Route::middleware(['auth:sanctum', \App\Http\Middleware\ApiLogger::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/user/{id}', [AuthController::class, 'getUser'])->name('auth.user');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('auth.password.change');
    Route::patch('user/{id}/status', [AuthController::class, 'updateStatus'])->name('user.updateStatus');

    Route::prefix('master')->group(function () {
        Route::get('/sips-users', [MasterController::class, 'index'])->name('master.users');
        Route::get('/sips-fields', [MasterController::class, 'field'])->name('master.field');
        Route::get('/sips-karyawans', [MasterController::class, 'karyawan'])->name('master.karyawan');
        Route::get('/sips-karyawans-kemandoran', [MasterController::class, 'karyawanKemandoran'])->name('master.karyawan.kemandoran');
        Route::get('/sips-kendaraan', [MasterController::class, 'vehicle'])->name('master.kendaraan');
        Route::get('/sips-businessunit', [MasterController::class, 'businessunit'])->name('master.businessunit');
    });

    Route::prefix('apps')->group(function () {
        Route::apiResource('tphs', TphController::class)
            ->parameters([
                'tphs' => 'id', // Ganti parameter menjadi 'id'
            ]);

        Route::apiResource('ancaks', AncakController::class)
            ->parameters([
                'ancaks' => 'id',
            ]);

        Route::apiResource('karyawans', EmployeeController::class)
            ->parameters([
                'karyawans' => 'id', // Ganti parameter menjadi 'id'
            ]);

        Route::apiResource('absensis', AttendanceController::class)
            ->parameters([
                'absensis' => 'id', // Ganti parameter menjadi 'id'
            ]);

        // Route untuk memperbarui hanya field status_absensi (STATUS_ATTENDANCE)
        Route::patch('absensis/{id}/status', [AttendanceController::class, 'updateStatus'])->name('absensis.updateStatus');

        Route::apiResource('panens', HarvestingController::class)
            ->parameters([
                'panens' => 'id', // Ganti parameter menjadi 'id'
            ]);

        // Route untuk memperbarui hanya field status_absensi (STATUS_ATTENDANCE)
        Route::patch('panens/{id}/status', [HarvestingController::class, 'updateStatus'])->name('panens.updateStatus');

        Route::apiResource('pengangkutans', PengangkutanController::class)
            ->parameters([
                'pengangkutans' => 'id', // Ganti parameter menjadi 'id'
            ]);

        // Route untuk memperbarui hanya field status_absensi (STATUS_ATTENDANCE)
        Route::patch('pengangkutans/{id}/status', [PengangkutanController::class, 'updateStatus'])->name('pengangkutans.updateStatus');
    });

    Route::prefix('report')->group(function () {
        Route::get('/hasil-panen', [ReportController::class, 'hasil_panen'])->name('report.hasil-panen');
        Route::get('/hasil-pengangkutan', [ReportController::class, 'hasil_pengangkutan'])->name('report.hasil-pengangkutan');
        Route::get('/hasil-langsir', [ReportController::class, 'hasil_langsir'])->name('report.hasil-langsir');
        Route::get('/upload-attendance', [ReportController::class, 'upload_attendance'])->name('report.upload-attendance');
    });

    Route::prefix('uploads')->group(function () {
        Route::post('/attendance', [UploadController::class, 'attendance'])->name('uploads.attendance');
    });

    Route::prefix('settings')->group(function () {
        // Devices API
        Route::apiResource('devices', DeviceController::class)
            ->parameters([
                'devices' => 'id',
            ]);
    });

    // API Logs Routes
    Route::prefix('logs')->group(function () {
        Route::get('/', [ApiLogController::class, 'index'])->name('logs.index');
        Route::get('/{id}', [ApiLogController::class, 'show'])->name('logs.show');
    });
});
