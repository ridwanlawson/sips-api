<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\StorageService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SyncFilesToDev extends Command
{
    protected $signature = "sync:files-to-dev {--dry-run : Tampilkan tanpa eksekusi} {--cleanup-orphans : Hapus file PROD yang sudah pasti ada di DEV}";
    protected $description = "Sync file sementara di PROD ke DEV server (jalankan setiap tengah malam)";

    public function handle(StorageService $storage): int
    {
        if (!$this->isSafeToRun()) {
            $this->warn("Command ini hanya untuk server PROD. Dibatalkan.");
            return Command::SUCCESS;
        }

        $this->info("[" . now() . "] Mulai sync...");

        if (!$storage->isDevOnline()) {
            $this->warn("DEV server offline. Sync ditunda.");
            Log::warning("SyncFilesToDev: DEV offline, sync dibatalkan.");
            return Command::FAILURE;
        }

        $devUrl = rtrim(config("app.dev_server_url"), "/");
        $devPrefix = $devUrl . "%";
        $isDryRun = $this->option("dry-run");
        $totalSuccess = 0;
        $totalFailed = 0;

        $tables = [
            ["table" => "SIPSMOBILE.ATTENDANCE", "columns" => ["IMAGES", "NO_BA_EXCA", "DELETED_ATTACHMENT"]],
            ["table" => "SIPSMOBILE.HARVESTING", "columns" => ["IMAGES", "NO_BA_EXCA", "DELETED_ATTACHMENT"]],
            ["table" => "SIPSMOBILE.PENGANGKUTAN", "columns" => ["IMAGES", "NO_BA_EXCA", "DELETED_ATTACHMENT"]],
            ["table" => "SIPSMOBILE.USERS", "columns" => ["PHOTO"]],
            ["table" => "SIPSMOBILE.EMPLOYEE", "columns" => ["PHOTO"]],
            ["table" => "SIPSMOBILE.BACKUP_JSON_FILES", "columns" => ["URL"]],
        ];

        $tables = $this->ensureColumnsExist($tables);

        if (empty($tables)) {
            $this->warn("Tidak ada tabel/kolom yang valid di Oracle. Sync dibatalkan.");
            return Command::SUCCESS;
        }

        $refCounts = $this->collectNonDevReferenceCounts($tables, $devPrefix);

        foreach ($tables as $tbl) {
            $table = $tbl["table"];

            foreach ($tbl["columns"] as $column) {
                $this->info("--- {$table} :: {$column} ---");

                $records = $this->fetchPendingRecords($table, $column, $devPrefix);

                if (empty($records)) {
                    $this->line("  Tidak ada file di PROD untuk kolom ini.");
                    continue;
                }

                $this->info("  Ditemukan " . count($records) . " file.");

                foreach ($records as $record) {
                    $relativePath = $this->urlToRelativePath($record->file_url);
                    $localAbsPath = public_path($relativePath);

                    if (!file_exists($localAbsPath)) {
                        $this->warn("  File tidak ditemukan di disk: {$relativePath} (ID: {$record->id})");
                        $totalFailed++;
                        continue;
                    }

                    if ($isDryRun) {
                        $this->line("  [DRY-RUN] ID {$record->id} [{$column}] → {$relativePath}");
                        continue;
                    }

                    $devFileUrl = $storage->uploadToDev($localAbsPath, $relativePath);

                    if ($devFileUrl) {
                        $updated = $this->updateRecordUrl($table, $column, $record->id, $devFileUrl);

                        if (!$updated) {
                            $this->error("  ✗ ID {$record->id} [{$column}] update DB gagal");
                            Log::error("SyncFilesToDev: update DB gagal", [
                                "table" => $table,
                                "id" => $record->id,
                                "column" => $column,
                            ]);
                            $totalFailed++;
                            continue;
                        }

                        if ($this->decrementAndShouldUnlink($refCounts, $relativePath)) {
                            if (!@unlink($localAbsPath)) {
                                Log::warning("SyncFilesToDev: hapus file PROD gagal (permission?)", [
                                    "path" => $relativePath,
                                ]);
                                $this->warn("  ! ID {$record->id} [{$column}] diupload tapi file PROD tak bisa dihapus");
                            }
                        }

                        $this->info("  ✓ ID {$record->id} [{$column}] → {$devFileUrl}");
                        $totalSuccess++;
                    } else {
                        $this->error("  ✗ ID {$record->id} [{$column}] gagal diupload");
                        Log::error("SyncFilesToDev: gagal upload", [
                            "table" => $table,
                            "id" => $record->id,
                            "column" => $column,
                            "path" => $relativePath,
                        ]);
                        $totalFailed++;
                    }
                }
            }
        }

        $orphanSummary = "";
        if ($this->option("cleanup-orphans")) {
            $orphanSummary = $this->cleanupOrphans($tables, $isDryRun);
        }

        $summary = "Sukses: {$totalSuccess} | Gagal: {$totalFailed}";
        if ($orphanSummary) {
            $summary .= " | " . $orphanSummary;
        }

        $this->info("[" . now() . "] Selesai. " . $summary);
        Log::info("SyncFilesToDev selesai", [
            "success" => $totalSuccess,
            "failed" => $totalFailed,
        ]);

        return $totalFailed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function isSafeToRun(): bool
    {
        if (config("app.server_role") !== "prod") {
            return false;
        }

        $devUrl = rtrim((string) config("app.dev_server_url"), "/");
        if ($devUrl === "") {
            return false;
        }

        if ($devUrl === rtrim((string) config("app.url"), "/")) {
            return false;
        }

        return true;
    }

    private function ensureColumnsExist(array $tables): array
    {
        $valid = [];

        foreach ($tables as $tbl) {
            $tableName = str_replace("SIPSMOBILE.", "", $tbl["table"]);

            try {
                $cols = DB::connection("oracle")->select(
                    "SELECT column_name FROM all_tab_columns WHERE owner = 'SIPSMOBILE' AND table_name = :t",
                    ["t" => $tableName],
                );
            } catch (\Throwable $e) {
                Log::warning("SyncFilesToDev: cek kolom gagal", [
                    "table" => $tableName,
                    "message" => $e->getMessage(),
                ]);
                continue;
            }

            $existing = [];
            foreach ($cols as $col) {
                $existing[] = strtoupper($col->column_name);
            }

            $goodColumns = array_values(array_intersect($tbl["columns"], $existing));

            if (!empty($goodColumns)) {
                $valid[] = ["table" => $tbl["table"], "columns" => $goodColumns];
            } else {
                Log::warning("SyncFilesToDev: kolom tidak ditemukan, di-skip", [
                    "table" => $tableName,
                    "columns" => $tbl["columns"],
                ]);
            }
        }

        return $valid;
    }

    private function fetchPendingRecords(string $table, string $column, string $devPrefix): array
    {
        return DB::connection("oracle")->select(
            "SELECT ID, {$column} AS FILE_URL
             FROM {$table}
             WHERE {$column} IS NOT NULL
               AND {$column} NOT LIKE :dev_prefix",
            ["dev_prefix" => $devPrefix],
        );
    }

    private function updateRecordUrl(string $table, string $column, int $id, string $devUrl): bool
    {
        return DB::connection("oracle")->statement(
            "UPDATE {$table}
             SET {$column} = :url, UPDATED_AT = SYSDATE
             WHERE ID = :id",
            ["url" => $devUrl, "id" => $id],
        );
    }

    private function collectNonDevReferenceCounts(array $tables, string $devPrefix): array
    {
        $counts = [];

        foreach ($tables as $tbl) {
            foreach ($tbl["columns"] as $column) {
                $rows = $this->fetchPendingRecords($tbl["table"], $column, $devPrefix);
                foreach ($rows as $row) {
                    $path = $this->urlToRelativePath($row->file_url);
                    if (!isset($counts[$path])) {
                        $counts[$path] = 0;
                    }
                    $counts[$path]++;
                }
            }
        }

        return $counts;
    }

    private function decrementAndShouldUnlink(array &$counts, string $path): bool
    {
        if (!isset($counts[$path])) {
            return true;
        }

        $counts[$path]--;

        return $counts[$path] <= 0;
    }

    private function urlToRelativePath(string $url): string
    {
        $path = str_contains($url, "://")
            ? (string) parse_url($url, PHP_URL_PATH)
            : $url;

        return ltrim($path, "/");
    }

    private function cleanupOrphans(array $tables, bool $isDryRun): string
    {
        $referenced = [];

        foreach ($tables as $tbl) {
            foreach ($tbl["columns"] as $column) {
                $rows = DB::connection("oracle")->select(
                    "SELECT {$column} AS FILE_URL
                     FROM {$tbl["table"]}
                     WHERE {$column} IS NOT NULL",
                );

                foreach ($rows as $row) {
                    $path = $this->urlToRelativePath($row->file_url);
                    $isDev = false;

                    if (str_contains($row->file_url, "://")) {
                        $isDev = str_starts_with(
                            $row->file_url,
                            rtrim(config("app.dev_server_url"), "/"),
                        );
                    }

                    if (!isset($referenced[$path])) {
                        $referenced[$path] = ["dev" => false, "non_dev" => false];
                    }

                    if ($isDev) {
                        $referenced[$path]["dev"] = true;
                    } else {
                        $referenced[$path]["non_dev"] = true;
                    }
                }
            }
        }

        $baseDir = public_path("file");
        if (!is_dir($baseDir)) {
            return "";
        }

        $publicDir = str_replace("\\", "/", rtrim(public_path(), "/")) . "/";
        $deleted = 0;
        $skippedPending = 0;
        $untracked = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $absNorm = str_replace("\\", "/", $file->getPathname());

            if (!str_starts_with($absNorm, $publicDir)) {
                continue;
            }

            $relPath = ltrim(substr($absNorm, strlen($publicDir)), "/");

            if (str_starts_with($relPath, "file/apps/")) {
                continue;
            }

            if (!isset($referenced[$relPath])) {
                $untracked++;
                Log::warning("SyncFilesToDev: file untracked (tidak dihapus)", ["path" => $relPath]);
                continue;
            }

            if ($referenced[$relPath]["non_dev"]) {
                $skippedPending++;
                continue;
            }

            if ($isDryRun) {
                $this->line("  [DRY-RUN] cleanup: {$relPath}");
                continue;
            }

            if (@unlink($file->getPathname())) {
                $deleted++;
            } else {
                Log::warning("SyncFilesToDev: cleanup hapus gagal", ["path" => $relPath]);
            }
        }

        $summary = "Cleanup: hapus {$deleted} | skip-pending {$skippedPending} | untracked {$untracked}";
        $this->info($summary);

        return $summary;
    }
}
