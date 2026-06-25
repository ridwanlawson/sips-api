<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\StorageService;

class SyncFilesToDev extends Command
{
    protected $signature = "sync:files-to-dev {--dry-run : Tampilkan tanpa eksekusi}";
    protected $description = "Sync file sementara di PROD ke DEV (jalankan tengah malam)";

    public function handle(StorageService $storage): int
    {
        $this->info("[" . now() . "] Mulai sync...");

        if (!$storage->isDevOnline()) {
            $this->warn("DEV offline. Sync ditunda.");
            Log::warning("SyncFilesToDev: DEV offline");
            return Command::FAILURE;
        }

        $prodBase = rtrim(config("app.url"), "/");

        // Ambil semua record yang URL-nya masih mengarah ke PROD
        $records = DB::connection("oracle")->select(
            "
            SELECT ID, IMAGES
            FROM SIPSMOBILE.ATTENDANCE
            WHERE IMAGES IS NOT NULL
              AND IMAGES LIKE :prod_prefix
              AND DELETED_AT IS NULL
        ",
            ["prod_prefix" => $prodBase . "%"],
        );

        if (empty($records)) {
            $this->info("Tidak ada file yang perlu di-sync.");
            return Command::SUCCESS;
        }

        $this->info("Ditemukan " . count($records) . " file untuk di-sync.");

        $success = 0;
        $failed = 0;

        foreach ($records as $record) {
            $relativePath = ltrim(
                str_replace($prodBase, "", $record->images),
                "/",
            );
            $localAbsPath = public_path($relativePath);

            if (!file_exists($localAbsPath)) {
                $this->warn(
                    "File tidak ditemukan: {$relativePath} (ID: {$record->id})",
                );
                $failed++;
                continue;
            }

            if ($this->option("dry-run")) {
                $this->line("[DRY-RUN] ID {$record->id} → {$relativePath}");
                continue;
            }

            $devUrl = $storage->uploadToDev($localAbsPath, $relativePath);

            if ($devUrl) {
                DB::connection("oracle")->statement(
                    "
                    UPDATE SIPSMOBILE.ATTENDANCE
                    SET IMAGES     = :images,
                        UPDATED_AT = SYSDATE
                    WHERE ID = :id
                ",
                    [
                        "images" => $devUrl,
                        "id" => $record->id,
                    ],
                );

                @unlink($localAbsPath);

                $this->info("✓ ID {$record->id} → {$devUrl}");
                $success++;
            } else {
                $this->error("✗ ID {$record->id} gagal diupload");
                Log::error("SyncFilesToDev: gagal upload", [
                    "id" => $record->id,
                    "path" => $relativePath,
                ]);
                $failed++;
            }
        }

        $this->info("Selesai. Sukses: {$success} | Gagal: {$failed}");
        Log::info("SyncFilesToDev selesai", compact("success", "failed"));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
