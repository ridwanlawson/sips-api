<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @hideFromAPIDocumentation
 */
class ApiLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ApiLog::query();

        // Filter by date range
        if ($request->has("start_date") && $request->has("end_date")) {
            $query->whereBetween("created_at", [
                $request->start_date,
                $request->end_date,
            ]);
        }

        // Filter by user
        if ($request->has("user_id")) {
            $query->where("user_id", $request->user_id);
        }

        // Filter by method
        if ($request->has("method")) {
            $query->where("method", $request->method);
        }

        // Filter by endpoint
        if ($request->has("endpoint")) {
            $query->where("endpoint", "LIKE", "%" . $request->endpoint . "%");
        }

        // Order by latest first
        $query->orderBy("created_at", "desc");

        // Paginate results
        $logs = $query->paginate($request->per_page ?? 10);

        return response()->json([
            "status" => "success",
            "data" => $logs,
            "message" => "API logs retrieved successfully",
        ]);
    }

    public function show($id)
    {
        $log = ApiLog::findOrFail($id);

        return response()->json([
            "status" => "success",
            "data" => $log,
            "message" => "API log detail retrieved successfully",
        ]);
    }

    public function deploy(Request $request)
    {
        // ===== VALIDASI SIGNATURE GITHUB (WAJIB - PALING UTAMA) =====
        $secret = config("app.deploy_secret");
        $branch = config("app.deploy_branch");
        $path = rtrim(str_replace("/", "\\", config("app.deploy_path")), "\\/");
        $signature = $request->header("X-Hub-Signature-256");
        $payload = $request->getContent();

        if (!$signature || !$secret) {
            Log::warning("Deploy ditolak: signature atau secret tidak ada");
            return response()->json(["message" => "Forbidden"], 403);
        }

        $expected = "sha256=" . hash_hmac("sha256", $payload, $secret);
        if (!hash_equals($expected, $signature)) {
            Log::warning("Deploy ditolak: signature tidak valid", [
                "ip" => $request->ip(),
                "user_agent" => $request->header("User-Agent"),
            ]);
            return response()->json(["message" => "Forbidden"], 403);
        }

        // ===== VALIDASI EVENT & BRANCH =====
        if ($request->header("X-GitHub-Event") !== "push") {
            return response()->json(["message" => "Event diabaikan"], 200);
        }

        $ref = $request->input("ref", "");
        if ($ref !== "refs/heads/$branch") {
            Log::info("Skip deploy: bukan branch $branch ($ref)");
            return response()->json(["message" => "Branch tidak sesuai"], 200);
        }

        if (!is_dir($path)) {
            Log::error("Path tidak ditemukan: $path");
            return response()->json(["message" => "Path tidak valid"], 500);
        }

        // ===== TOOL PATH =====
        $git = '"' . str_replace('/', '\\', config('app.git_path')) . '"';
        $php = '"' . str_replace('/', '\\', config('app.php_path')) . '"';

        $composerPhar = $path . "\\composer.phar";
        $composer = file_exists($composerPhar)
            ? "$php \"$composerPhar\""
            : "composer";

        $output = [];

        $run = function (string $cmd) use ($path, &$output) {
            $full = "cd /d \"$path\" && $cmd 2>&1";
            exec($full, $out, $code);
            $output = array_merge($output, $out);
            return $code;
        };

        try {
            putenv("GIT_CONFIG_COUNT=1");
            putenv("GIT_CONFIG_KEY_0=safe.directory");
            putenv("GIT_CONFIG_VALUE_0=*");

            $run("$git fetch origin");
            $run("$git reset --hard origin/$branch");

            $composerCheck = [];
            exec("composer --version 2>&1", $composerCheck, $composerCode);
            if ($composerCode === 0 || file_exists($composerPhar)) {
                $run(
                    "$composer install --no-dev --optimize-autoloader --no-interaction",
                );
            } else {
                $output[] = "[SKIP] composer tidak ditemukan, lewati install";
            }

            $run("$php artisan config:cache");
            $run("$php artisan route:cache");
            $run("$php artisan view:cache");

            Log::info("Deploy success", $output);

            return response()->json([
                "message" => "Deploy berhasil",
                "log" => $output,
            ]);
        } catch (\Throwable $e) {
            Log::error("Deploy error: " . $e->getMessage(), $output);
            return response()->json(
                [
                    "message" => "Deploy gagal",
                    "error" => $e->getMessage(),
                    "log" => $output,
                ],
                500,
            );
        }
    }

    /**
     * Health Check
     *
     * Endpoint untuk memantau apakah server sedang online.
     * Digunakan oleh sistem sync storage untuk mendeteksi ketersediaan DEV server.
     *
     * @subgroup Health Check
     * @subgroupDescription Monitoring status server
     *
     * @response 200 scenario="Server online" {
     *   "status": "ok",
     *   "server": "production",
     *   "time": "2025-01-01T00:00:00+07:00"
     * }
     */
    public function health()
    {
        return response()->json([
            "status" => "ok",
            "server" => config("app.server_role"),
            "time" => now()->toIso8601String(),
        ]);
    }
}
