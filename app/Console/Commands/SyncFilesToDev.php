<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\StorageService;

class SyncFilesToDev extends Command
{
    protected $signature = "sync:files-to-dev {--dry-run : Tampilkan tanpa eksekusi}";
    protected $description = "Sync file sementara di PROD ke DEV server (jalankan setiap tengah malam)";

    public function handle(StorageService $storage): int
    {
        // Command ini hanya relevan dijalankan dari PROD
        if (config("app.server_role") !== "prod") {
            $this->warn("Command ini hanya untuk server PROD. Dibatalkan.");
            return Command::SUCCESS;
        }

        $this->info("[" . now() . "] Mulai sync...");

        if (!$storage->isDevOnline()) {
            $this->warn("DEV server offline. Sync ditunda.");
            Log::warning("SyncFilesToDev: DEV offline, sync dibatalkan.");
            return Command::FAILURE;
        }

        $prodBase = rtrim(config("app.url"), "/");
        $isDryRun = $this->option("dry-run");
        $totalSuccess = 0;
        $totalFailed = 0;

        // Kolom yang perlu dicek dan disync
        $columns = ["IMAGES", "NO_BA_EXCA", "DELETED_ATTACHMENT"];

        foreach ($columns as $column) {
            $this->info("--- Sync kolom: {$column} ---");

            $records = DB::connection("oracle")->select(
                "
                SELECT ID, {$column} AS FILE_URL
                FROM SIPSMOBILE.ATTENDANCE
                WHERE {$column} IS NOT NULL
                  AND {$column} LIKE :prod_prefix
                  AND DELETED_AT IS NULL
            ",
                ["prod_prefix" => $prodBase . "%"],
            );

            if (empty($records)) {
                $this->line("  Tidak ada file di PROD untuk kolom {$column}.");
                continue;
            }

            $this->info("  Ditemukan " . count($records) . " file.");

            foreach ($records as $record) {
                $relativePath = ltrim(
                    str_replace($prodBase, "", $record->file_url),
                    "/",
                );
                $localAbsPath = public_path($relativePath);

                if (!file_exists($localAbsPath)) {
                    $this->warn(
                        "  File tidak ditemukan di disk: {$relativePath} (ID: {$record->id})",
                    );
                    $totalFailed++;
                    continue;
                }

                if ($isDryRun) {
                    $this->line(
                        "  [DRY-RUN] ID {$record->id} kolom {$column} → {$relativePath}",
                    );
                    continue;
                }

                $devUrl = $storage->uploadToDev($localAbsPath, $relativePath);

                if ($devUrl) {
                    DB::connection("oracle")->statement(
                        "
                        UPDATE SIPSMOBILE.ATTENDANCE
                        SET {$column}  = :url,
                            UPDATED_AT = SYSDATE
                        WHERE ID = :id
                    ",
                        [
                            "url" => $devUrl,
                            "id" => $record->id,
                        ],
                    );

                    @unlink($localAbsPath);

                    $this->info("  ✓ ID {$record->id} [{$column}] → {$devUrl}");
                    $totalSuccess++;
                } else {
                    $this->error(
                        "  ✗ ID {$record->id} [{$column}] gagal diupload",
                    );
                    Log::error("SyncFilesToDev: gagal upload", [
                        "id" => $record->id,
                        "column" => $column,
                        "path" => $relativePath,
                    ]);
                    $totalFailed++;
                }
            }
        }

        $this->info(
            "[" .
                now() .
                "] Selesai. Sukses: {$totalSuccess} | Gagal: {$totalFailed}",
        );
        Log::info("SyncFilesToDev selesai", [
            "success" => $totalSuccess,
            "failed" => $totalFailed,
        ]);

        return $totalFailed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
