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

        $devOnline = $storage->isDevOnline();
        if (!$devOnline) {
            $this->warn("DEV server offline. Fase upload di-skip, cleanup tetap jalan.");
            Log::warning("SyncFilesToDev: DEV offline, fase upload di-skip.");
        }

        $devUrl = rtrim(config("app.dev_server_url"), "/");
        $devPrefix = $devUrl . "%";
        $isDryRun = $this->option("dry-run");

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

        if ($devOnline) {
            [$totalSuccess, $totalFailed] = $this->syncPendingFiles(
                $storage,
                $tables,
                $devPrefix,
                $isDryRun,
            );
        } else {
            $totalSuccess = 0;
            $totalFailed = 0;
            $this->warn("  DEV offline, file tertunda di PROD tidak diupload.");
        }

        $orphanSummary = "";
        if ($this->option("cleanup-orphans")) {
            $orphanSummary = $this->cleanupOrphans(
                $tables,
                $isDryRun,
                $storage,
                $devOnline,
            );
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

        return $totalFailed > 0 || !$devOnline
            ? Command::FAILURE
            : Command::SUCCESS;
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

    /**
     * Fase upload utama: upload file PROD yang belum ada di DEV ke DEV,
     * update URL di DB, lalu hapus file PROD (anti-premature-delete).
     *
     * @return array{0:int,1:int} [totalSuccess, totalFailed]
     */
    private function syncPendingFiles(
        StorageService $storage,
        array $tables,
        string $devPrefix,
        bool $isDryRun,
    ): array {
        $refCounts = $this->collectNonDevReferenceCounts($tables, $devPrefix);

        $totalSuccess = 0;
        $totalFailed = 0;

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

        return [$totalSuccess, $totalFailed];
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

    private function cleanupOrphans(
        array $tables,
        bool $isDryRun,
        StorageService $storage,
        bool $devOnline,
    ): string {
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
        $reconcileUploaded = 0;
        $reconcileDuplicate = 0;
        $reconcileAmbiguous = 0;
        $reconcileFailed = 0;
        $reconcileSkipped = 0;

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
                $status = $this->reconcileUntrackedFile(
                    $relPath,
                    $isDryRun,
                    $storage,
                    $devOnline,
                );

                switch ($status) {
                    case "uploaded":
                        $reconcileUploaded++;
                        break;
                    case "duplicate":
                        $reconcileDuplicate++;
                        break;
                    case "ambiguous":
                        $reconcileAmbiguous++;
                        break;
                    case "failed":
                        $reconcileFailed++;
                        break;
                    case "skipped":
                        $reconcileSkipped++;
                        break;
                    default:
                        $untracked++;
                        Log::warning(
                            "SyncFilesToDev: file untracked (tidak dihapus)",
                            ["path" => $relPath],
                        );
                        break;
                }
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

        $removedDirs = $this->removeEmptyDirs($baseDir, $isDryRun);

        $summary = "Cleanup: hapus {$deleted} | skip-pending {$skippedPending} | untracked {$untracked}";
        $summary .= " | reconcile: upload {$reconcileUploaded} | duplikat {$reconcileDuplicate} | ambigu {$reconcileAmbiguous} | gagal {$reconcileFailed} | skip {$reconcileSkipped}";
        $summary .= " | folder-kosong dihapus {$removedDirs}";
        $this->info($summary);

        return $summary;
    }

    /**
     * Coba cocokkan file disk yang tidak punya referensi DB dengan record-nya.
     */
    private function reconcileUntrackedFile(
        string $relPath,
        bool $isDryRun,
        StorageService $storage,
        bool $devOnline,
    ): string {
        if (str_starts_with($relPath, "file/harvesting/images/")) {
            return $this->reconcileByNodokumen(
                "SIPSMOBILE.HARVESTING",
                $relPath,
                $isDryRun,
                $storage,
                $devOnline,
            );
        }

        if (str_starts_with($relPath, "file/pengangkutan/images/")) {
            return $this->reconcileByNodokumen(
                "SIPSMOBILE.PENGANGKUTAN",
                $relPath,
                $isDryRun,
                $storage,
                $devOnline,
            );
        }

        if (str_starts_with($relPath, "file/attendance/images/")) {
            return $this->reconcileAttendance(
                $relPath,
                $isDryRun,
                $storage,
                $devOnline,
            );
        }

        return "untracked";
    }

    /**
     * Reconcile harvesting/pengangkutan: cocokkan slug NODOKUMEN dari nama file.
     */
    private function reconcileByNodokumen(
        string $table,
        string $relPath,
        bool $isDryRun,
        StorageService $storage,
        bool $devOnline,
    ): string {
        $slug = $this->extractNodokumenSlug(basename($relPath));
        if ($slug === "") {
            return "untracked";
        }

        $devPrefix = rtrim(config("app.dev_server_url"), "/") . "%";

        $rows = DB::connection("oracle")->select(
            "SELECT ID, IMAGES
             FROM {$table}
             WHERE (IMAGES IS NULL OR IMAGES LIKE :dev_prefix)
               AND LTRIM(RTRIM(REGEXP_REPLACE(NODOKUMEN, '[^[:alnum:]]+', '_'), '_'), '_') = :slug",
            ["dev_prefix" => $devPrefix, "slug" => $slug],
        );

        if (count($rows) === 0) {
            Log::warning(
                "SyncFilesToDev: reconcile tanpa kandidat",
                ["table" => $table, "path" => $relPath, "slug" => $slug],
            );
            return "untracked";
        }

        if (count($rows) > 1) {
            Log::warning(
                "SyncFilesToDev: reconcile ambigu (>1 kandidat)",
                ["table" => $table, "path" => $relPath, "slug" => $slug],
            );
            return "ambiguous";
        }

        return $this->reconcileUploadOrDelete(
            $table,
            $relPath,
            $rows[0],
            $isDryRun,
            $storage,
            $devOnline,
        );
    }

    /**
     * Reconcile attendance: cocokkan kode karyawan dari nama file + tanggal dari folder.
     */
    private function reconcileAttendance(
        string $relPath,
        bool $isDryRun,
        StorageService $storage,
        bool $devOnline,
    ): string {
        $basename = basename($relPath);
        if (!preg_match('/(\d{2}-\d{6}-\d{6}-\d{4})/', $basename, $m)) {
            return "untracked";
        }
        $kode = $m[1];

        $parts = explode("/", $relPath);
        if (count($parts) < 8) {
            return "untracked";
        }
        $tanggal = sprintf(
            "%04d-%02d-%02d",
            (int) $parts[4],
            (int) $parts[5],
            (int) $parts[6],
        );

        $devPrefix = rtrim(config("app.dev_server_url"), "/") . "%";

        $rows = DB::connection("oracle")->select(
            "SELECT ID, IMAGES
             FROM SIPSMOBILE.ATTENDANCE
             WHERE KODE_KARYAWAN = :kode
               AND TRUNC(TANGGAL) = TO_DATE(:tanggal, 'YYYY-MM-DD')
               AND (IMAGES IS NULL OR IMAGES LIKE :dev_prefix)",
            ["kode" => $kode, "tanggal" => $tanggal, "dev_prefix" => $devPrefix],
        );

        if (count($rows) === 0) {
            Log::warning(
                "SyncFilesToDev: reconcile tanpa kandidat",
                ["table" => "SIPSMOBILE.ATTENDANCE", "path" => $relPath],
            );
            return "untracked";
        }

        if (count($rows) > 1) {
            Log::warning(
                "SyncFilesToDev: reconcile ambigu (>1 kandidat)",
                ["table" => "SIPSMOBILE.ATTENDANCE", "path" => $relPath],
            );
            return "ambiguous";
        }

        return $this->reconcileUploadOrDelete(
            "SIPSMOBILE.ATTENDANCE",
            $relPath,
            $rows[0],
            $isDryRun,
            $storage,
            $devOnline,
        );
    }

    /**
     * Eksekusi untuk record yang sudah cocok:
     * - IMAGES null      -> upload ke DEV, update DB, hapus file
     * - IMAGES URL DEV   -> file duplikat lama, hapus saja
     */
    private function reconcileUploadOrDelete(
        string $table,
        string $relPath,
        object $row,
        bool $isDryRun,
        StorageService $storage,
        bool $devOnline,
    ): string {
        $localAbsPath = public_path($relPath);

        if ($row->images !== null) {
            if ($isDryRun) {
                $this->line("  [DRY-RUN] reconcile duplikat: {$relPath} (ID {$row->id})");
                return "duplicate";
            }

            if (@unlink($localAbsPath)) {
                return "duplicate";
            }

            Log::warning("SyncFilesToDev: reconcile hapus duplikat gagal", [
                "table" => $table,
                "id" => $row->id,
                "path" => $relPath,
            ]);
            return "duplicate";
        }

        if (!$devOnline) {
            $this->line("  - reconcile skip (DEV offline): {$relPath} (ID {$row->id})");
            return "skipped";
        }

        if ($isDryRun) {
            $this->line("  [DRY-RUN] reconcile upload: {$relPath} (ID {$row->id})");
            return "uploaded";
        }

        $devFileUrl = $storage->uploadToDev($localAbsPath, $relPath);

        if (!$devFileUrl) {
            Log::error("SyncFilesToDev: reconcile gagal upload", [
                "table" => $table,
                "id" => $row->id,
                "path" => $relPath,
            ]);
            return "failed";
        }

        $updated = $this->updateRecordUrl($table, "IMAGES", $row->id, $devFileUrl);

        if (!$updated) {
            Log::error("SyncFilesToDev: reconcile update DB gagal", [
                "table" => $table,
                "id" => $row->id,
                "path" => $relPath,
            ]);
            return "failed";
        }

        @unlink($localAbsPath);
        $this->info("  ✓ reconcile ID {$row->id} [{$table}] → {$devFileUrl}");

        return "uploaded";
    }

    /**
     * Ambil slug NODOKUMEN dari nama file:
     * "1785580467_MTE_AFD_01C_GW_010826_0003_photo.jpg" -> "MTE_AFD_01C_GW_010826_0003"
     */
    private function extractNodokumenSlug(string $basename): string
    {
        $name = (string) pathinfo($basename, PATHINFO_FILENAME);
        $name = (string) preg_replace('/^\d+_/', "", $name);
        $name = (string) preg_replace('/_photo$/', "", $name);

        return trim((string) preg_replace('/[^A-Za-z0-9]+/', "_", $name), "_");
    }

    /**
     * Hapus folder kosong di bawah public/file (bottom-up).
     * Selalu skip file/apps dan folder induk public/file.
     */
    private function removeEmptyDirs(string $baseDir, bool $isDryRun): int
    {
        $removed = 0;
        $publicDir = str_replace("\\", "/", rtrim(public_path(), "/")) . "/";

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $entry) {
            if (!$entry->isDir()) {
                continue;
            }

            $absNorm = str_replace("\\", "/", $entry->getPathname());

            if (!str_starts_with($absNorm, $publicDir)) {
                continue;
            }

            $relDir = ltrim(substr($absNorm, strlen($publicDir)), "/");

            if ($relDir === "file" || str_starts_with($relDir, "file/apps")) {
                continue;
            }

            $contents = array_diff(scandir($entry->getPathname()) ?: [], [".", ".."]);

            if (count($contents) !== 0) {
                continue;
            }

            if ($isDryRun) {
                $this->line("  [DRY-RUN] cleanup folder kosong: {$relDir}/");
                $removed++;
                continue;
            }

            if (@rmdir($entry->getPathname())) {
                $removed++;
            } else {
                Log::warning("SyncFilesToDev: hapus folder kosong gagal", [
                    "path" => $relDir,
                ]);
            }
        }

        return $removed;
    }
}
