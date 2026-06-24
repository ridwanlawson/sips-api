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
use App\Http\Controllers\Api\AppUploadController;
use App\Http\Controllers\Api\MapController;

Route::post("/register", [AuthController::class, "register"])
    ->middleware("throttle:3,1")
    ->name("auth.register");
Route::post("/login", [AuthController::class, "login"])
    ->middleware("throttle:3,1")
    ->name("auth.login");

// Public routes untuk App Update (tanpa auth agar bisa langsung diakses dari browser/mobile)
Route::post("/app-update/check", [
    AppUploadController::class,
    "checkUpdate",
])->name("app.check-update");
Route::prefix("app")->group(function () {
    Route::post("/apk", [AppUploadController::class, "upload_apk"])->name(
        "app.upload-apk",
    );
    Route::get("/apks", [AppUploadController::class, "list"])->name(
        "app.list-versions",
    );
    Route::delete("/apk/{id}", [AppUploadController::class, "delete"])->name(
        "app.delete-version",
    );
});

Route::middleware([
    "auth:sanctum",
    \App\Http\Middleware\ApiLogger::class,
])->group(function () {
    Route::post("/logout", [AuthController::class, "logout"])->name(
        "auth.logout",
    );
    Route::get("/user/{id}", [AuthController::class, "getUser"])->name(
        "auth.user",
    );
    Route::post("/change-password", [
        AuthController::class,
        "changePassword",
    ])->name("auth.password.change");
    Route::patch("user/{id}/status", [
        AuthController::class,
        "updateStatus",
    ])->name("user.updateStatus");

    Route::prefix("master")->group(function () {
        Route::get("/sips-users", [MasterController::class, "index"])->name(
            "master.users",
        );
        Route::get("/sips-fields", [MasterController::class, "field"])->name(
            "master.field",
        );
        Route::get("/sips-karyawans", [
            MasterController::class,
            "karyawan",
        ])->name("master.karyawan");
        Route::get("/sips-karyawans-kemandoran", [
            MasterController::class,
            "karyawanKemandoran",
        ])->name("master.karyawan.kemandoran");
        Route::get("/sips-kendaraan", [
            MasterController::class,
            "vehicle",
        ])->name("master.kendaraan");
        Route::get("/sips-businessunit", [
            MasterController::class,
            "businessunit",
        ])->name("master.businessunit");
        Route::get("/sips-section", [MasterController::class, "section"])->name(
            "master.section",
        );
        Route::get("/sips-gang", [MasterController::class, "gang"])->name(
            "master.gang",
        );

        Route::apiResource("maps", MapController::class)->parameters([
            "maps" => "id",
        ]);
    });

    Route::prefix("apps")->group(function () {
        Route::apiResource("tphs", TphController::class)->parameters([
            "tphs" => "id",
        ]);

        Route::apiResource("ancaks", AncakController::class)->parameters([
            "ancaks" => "id",
        ]);

        Route::apiResource("karyawans", EmployeeController::class)->parameters([
            "karyawans" => "id",
        ]);

        Route::apiResource("absensis", AttendanceController::class)->parameters(
            [
                "absensis" => "id",
            ],
        );

        // Route untuk memperbarui hanya field status_absensi (STATUS_ATTENDANCE)
        Route::patch("absensis/{id}/status", [
            AttendanceController::class,
            "updateStatus",
        ])->name("absensis.updateStatus");

        Route::apiResource("panens", HarvestingController::class)->parameters([
            "panens" => "id",
        ]);

        // Route untuk memperbarui hanya field status_absensi (STATUS_ATTENDANCE)
        Route::patch("panens/{id}/status", [
            HarvestingController::class,
            "updateStatus",
        ])->name("panens.updateStatus");

        Route::apiResource(
            "pengangkutans",
            PengangkutanController::class,
        )->parameters([
            "pengangkutans" => "id",
        ]);

        // Route untuk memperbarui hanya field status_absensi (STATUS_ATTENDANCE)
        Route::patch("pengangkutans/{id}/status", [
            PengangkutanController::class,
            "updateStatus",
        ])->name("pengangkutans.updateStatus");
        Route::patch("pengangkutans/{id}/spbno-etd", [
            PengangkutanController::class,
            "updateSPBnETD",
        ])->name("pengangkutans.updateSPBnETD");
    });

    Route::prefix("report")->group(function () {
        Route::get("/hasil-panen", [
            ReportController::class,
            "hasil_panen",
        ])->name("report.hasil-panen");
        Route::get("/hasil-pengangkutan", [
            ReportController::class,
            "hasil_pengangkutan",
        ])->name("report.hasil-pengangkutan");
        Route::get("/hasil-langsir", [
            ReportController::class,
            "hasil_langsir",
        ])->name("report.hasil-langsir");
        Route::get("/upload-attendance", [
            ReportController::class,
            "upload_attendance",
        ])->name("report.upload-attendance");
        Route::get("/upload-harvesting", [
            ReportController::class,
            "upload_harvesting",
        ])->name("report.upload-harvesting");
        Route::get("/upload-harvesting-quality", [
            ReportController::class,
            "upload_harvesting_quality",
        ])->name("report.upload-harvesting-quality");
        Route::get("/upload-lhm", [
            ReportController::class,
            "upload_lhm",
        ])->name("report.upload-lhm");
        Route::get("/get-lhm", [ReportController::class, "get_lhm"])->name(
            "report.get-lhm",
        );
        Route::get("/get-lha", [ReportController::class, "get_lha"])->name(
            "report.get-lha",
        );
        Route::get("/get-harvesting", [
            ReportController::class,
            "get_harvesting",
        ])->name("report.get-harvesting");
    });

    Route::prefix("uploads")->group(function () {
        Route::post("/attendance", [
            UploadController::class,
            "attendance",
        ])->name("uploads.attendance");
        Route::post("/harvesting", [
            UploadController::class,
            "harvesting",
        ])->name("uploads.harvesting");
        Route::post("/harvestingquality", [
            UploadController::class,
            "harvestingquality",
        ])->name("upload.harvestingquality");
        Route::post("/attendance/mobile", [
            UploadController::class,
            "attendance_mobile",
        ])->name("uploads.attendance.mobile");
        Route::post("/harvesting/mobile", [
            UploadController::class,
            "harvesting_mobile",
        ])->name("uploads.harvesting.mobile");
        Route::post("/harvestingquality/mobile", [
            UploadController::class,
            "harvestingquality_mobile",
        ])->name("upload.harvestingquality.mobile");
        Route::post("/lhm_data/mobile", [
            UploadController::class,
            "lhm_data",
        ])->name("upload.lhm.data");
        Route::post("/open_lhm_data/mobile", [
            UploadController::class,
            "open_lhm_data",
        ])->name("upload.open.lhm.data");
    });

    Route::prefix("settings")->group(function () {
        // Devices API
        Route::apiResource("devices", DeviceController::class)->parameters([
            "devices" => "id",
        ]);
    });

    // API Logs Routes
    Route::prefix("logs")->group(function () {
        Route::get("/", [ApiLogController::class, "index"])->name("logs.index");
        Route::get("/{id}", [ApiLogController::class, "show"])->name(
            "logs.show",
        );
    });
});

<<<<<<< HEAD
Route::post('/deploy', function (Request $request) {
    // DEBUG SEMENTARA
    // Log::info('DEBUG CONFIG', [
    //     'deploy_path'   => config('app.deploy_path'),
    //     'deploy_branch' => config('app.deploy_branch'),
    //     'deploy_secret' => config('app.deploy_secret') ? 'ADA' : 'KOSONG',
    //     'env_path'      => env('DEPLOY_PATH'),
    //     'env_branch'    => env('DEPLOY_BRANCH'),
    //     'env_secret'    => env('DEPLOY_SECRET') ? 'ADA' : 'KOSONG',
    // ]);

    // ===== VALIDASI SIGNATURE GITHUB (WAJIB - PALING UTAMA) =====
    $secret    = config('app.deploy_secret');
    $branch    = config('app.deploy_branch');
    $path      = rtrim(str_replace('/', '\\', config('app.deploy_path')), "\\/");
    $signature = $request->header('X-Hub-Signature-256');
    $payload   = $request->getContent();

    // DEBUG SIGNATURE
    // Log::info('DEBUG SIGNATURE', [
    //     'signature_dari_github' => $signature,
    //     'payload_length'        => strlen($payload),
    //     'payload_preview'       => substr($payload, 0, 100),
    //     'expected'              => 'sha256=' . hash_hmac('sha256', $payload, $secret),
    //     'secret_length'         => strlen($secret),
    // ]);

    if (!$signature || !$secret) {
        Log::warning('Deploy ditolak: signature atau secret tidak ada');
        return response()->json(['message' => 'Forbidden'], 403);
    }

    $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    if (!hash_equals($expected, $signature)) {
        Log::warning('Deploy ditolak: signature tidak valid', [
            'ip'         => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // ===== VALIDASI EVENT & BRANCH =====
    if ($request->header('X-GitHub-Event') !== 'push') {
        return response()->json(['message' => 'Event diabaikan'], 200);
    }

    $ref = $request->input('ref', '');
    if ($ref !== "refs/heads/$branch") {
        Log::info("Skip deploy: bukan branch $branch ($ref)");
        return response()->json(['message' => 'Branch tidak sesuai'], 200);
    }

    if (!is_dir($path)) {
        Log::error("Path tidak ditemukan: $path");
        return response()->json(['message' => 'Path tidak valid'], 500);
    }

    // ===== TOOL PATH =====
    $git = '"C:\\Program Files\\Git\\bin\\git.exe"';
    $php = '"D:\\bin\\php\\php.exe"';

    $composerPhar = $path . '\\composer.phar';
    $composer = file_exists($composerPhar)
        ? "$php \"$composerPhar\""
        : 'composer';

    $output = [];

    $run = function (string $cmd) use ($path, &$output) {
        $full = "cd /d \"$path\" && $cmd 2>&1";
        exec($full, $out, $code);
        $output = array_merge($output, $out);
        return $code;
    };

    try {
        putenv('GIT_CONFIG_COUNT=1');
        putenv('GIT_CONFIG_KEY_0=safe.directory');
        putenv('GIT_CONFIG_VALUE_0=*');

        $run("$git fetch origin");
        $run("$git reset --hard origin/$branch");

        $composerCheck = [];
        exec('composer --version 2>&1', $composerCheck, $composerCode);
        if ($composerCode === 0 || file_exists($composerPhar)) {
            $run("$composer install --no-dev --optimize-autoloader --no-interaction");
        } else {
            $output[] = '[SKIP] composer tidak ditemukan, lewati install';
        }

        $run("$php artisan config:cache");
        $run("$php artisan route:cache");
        $run("$php artisan view:cache");

        Log::info('Deploy success', $output);

        return response()->json(['message' => 'Deploy berhasil', 'log' => $output]);
    } catch (\Throwable $e) {
        Log::error('Deploy error: ' . $e->getMessage(), $output);
        return response()->json(['message' => 'Deploy gagal', 'error' => $e->getMessage(), 'log' => $output], 500);
    }
});
=======
Route::post('/deploy', [ApiLogController::class, 'deploy']);
>>>>>>> 404c92b70afd0a1fb69afe9078a59161e4278dba
