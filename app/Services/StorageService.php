<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StorageService
{
    protected string $devUrl;

    protected string $devToken;

    protected int $timeout;

    public function __construct()
    {
        $this->devUrl = rtrim(config('app.dev_server_url', ''), '/');
        $this->devToken = config('app.internal_sync_token', '');
        $this->timeout = (int) config('app.dev_server_timeout', 5);
    }

    public function isDevOnline(): bool
    {
        try {
            $res = Http::timeout($this->timeout)->get(
                $this->devUrl.'/api/health',
            );

            return $res->successful();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Upload file lokal PROD ke DEV.
     *
     * @param  string  $localAbsPath  Path absolut file di PROD, contoh: public_path('file/attendance/...')
     * @param  string  $relativePath  Path relatif tujuan di DEV, contoh: 'file/attendance/images/mte/2025/01/01/foto.jpg'
     * @return string|null URL publik file di DEV, atau null jika gagal
     */
    public function uploadToDev(
        string $localAbsPath,
        string $relativePath,
    ): ?string {
        try {
            $res = Http::timeout(60)
                ->withHeaders(['X-Internal-Token' => $this->devToken])
                ->attach(
                    'file',
                    file_get_contents($localAbsPath),
                    basename($relativePath),
                )
                ->post($this->devUrl.'/api/internal/receive-file', [
                    'path' => $relativePath,
                ]);

            if ($res->successful()) {
                return $res->json('url');
            }

            Log::warning('StorageService: upload ke DEV gagal', [
                'status' => $res->status(),
                'path' => $relativePath,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('StorageService: exception saat upload', [
                'message' => $e->getMessage(),
                'path' => $localAbsPath,
            ]);

            return null;
        }
    }

    /**
     * Simpan file ke PROD lokal, lalu coba upload ke DEV.
     * Jika DEV online dan upload sukses → hapus dari PROD, return URL DEV.
     * Jika DEV offline atau gagal → biarkan di PROD, return URL PROD.
     *
     * @param  string  $relativePath  Path relatif tujuan (tanpa leading slash)
     * @return string URL publik final (DEV atau PROD)
     */
    public function storeFile(
        UploadedFile $file,
        string $relativePath,
    ): string {
        $directory = public_path(dirname($relativePath));

        if (! file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        $file->move($directory, basename($relativePath));
        // Kalau server ini DEV, tidak perlu sync ke mana-mana
        if (config('app.server_role') === 'dev') {
            return asset($relativePath);
        }

        $localAbsPath = public_path($relativePath);

        if ($this->isDevOnline()) {
            // Kalau server ini DEV, tidak relevan
            if (config('app.server_role') === 'dev') {
                return false;
            }
            $devUrl = $this->uploadToDev($localAbsPath, $relativePath);
            if ($devUrl) {
                @unlink($localAbsPath);

                return $devUrl;
            }
        }

        return asset($relativePath);
    }

    /**
     * Deteksi apakah URL masih tersimpan di PROD.
     */
    public function isStoredOnProd(?string $url): bool
    {
        if (! $url) {
            return false;
        }

        return str_starts_with($url, rtrim(config('app.url'), '/'));
    }

    /**
     * Konversi URL publik PROD ke path relatif untuk public_path().
     * Contoh: http://192.168.1.10/file/attendance/... → file/attendance/...
     */
    public function urlToRelativePath(string $url): string
    {
        $prodBase = rtrim(config('app.url'), '/');

        return ltrim(str_replace($prodBase, '', $url), '/');
    }

    /**
     * Ekstrak path relatif dari URL (PROD/DEV) atau path telanjang.
     */
    public function resolveRelativePath(string $urlOrPath): string
    {
        $path = str_contains($urlOrPath, '://')
            ? (string) parse_url($urlOrPath, PHP_URL_PATH)
            : $urlOrPath;

        return ltrim($path, '/');
    }

    /**
     * Hapus file yang sudah diupload (best-effort).
     * Menerima URL DEV/PROD atau path relatif.
     *
     * Aman: jika file masih direferensikan record DB lain, hapus di-skip.
     */
    public function deleteFile(?string $urlOrPath): bool
    {
        if (! $urlOrPath || $urlOrPath === '') {
            return false;
        }

        $relative = $this->resolveRelativePath($urlOrPath);

        if ($relative === '' || str_contains($relative, '..')) {
            return false;
        }

        if ($this->isPathReferenced($relative)) {
            Log::info('StorageService: file masih direferensikan DB, skip hapus', [
                'path' => $relative,
            ]);

            return false;
        }

        $devUrl = rtrim(config('app.dev_server_url', ''), '/');

        if ($devUrl !== '' && str_starts_with($urlOrPath, $devUrl)) {
            return $this->deleteFromDev($relative);
        }

        return $this->deleteLocal($relative);
    }

    /**
     * Cek apakah path relatif masih direferensikan record DB.
     * Jika cek gagal (koneksi/tabel), anggap terreferensi (aman: jangan hapus).
     */
    public function isPathReferenced(string $relativePath): bool
    {
        $needle = '%'.strtoupper(ltrim($relativePath, '/'));

        try {
            DB::connection('oracle')->selectOne('SELECT 1 FROM dual');
        } catch (\Throwable $e) {
            Log::warning('StorageService: koneksi oracle gagal, anggap terreferensi', [
                'message' => $e->getMessage(),
                'path' => $relativePath,
            ]);

            return true;
        }

        $tables = [
            ['SIPSMOBILE.ATTENDANCE', ['IMAGES', 'NO_BA_EXCA', 'DELETED_ATTACHMENT']],
            ['SIPSMOBILE.HARVESTING', ['IMAGES', 'NO_BA_EXCA', 'DELETED_ATTACHMENT']],
            ['SIPSMOBILE.PENGANGKUTAN', ['IMAGES', 'NO_BA_EXCA', 'DELETED_ATTACHMENT']],
            ['SIPSMOBILE.USERS', ['PHOTO']],
            ['SIPSMOBILE.EMPLOYEE', ['PHOTO']],
            ['SIPSMOBILE.BACKUP_JSON_FILES', ['URL']],
        ];

        foreach ($tables as [$table, $columns]) {
            foreach ($columns as $column) {
                try {
                    $row = DB::connection('oracle')->selectOne(
                        "SELECT 1 FROM {$table} WHERE UPPER({$column}) LIKE :needle AND ROWNUM = 1",
                        ['needle' => $needle],
                    );

                    if ($row) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    Log::warning('StorageService: cek referensi kolom gagal, dilewati', [
                        'table' => $table,
                        'column' => $column,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        return false;
    }

    /**
     * Hapus file lokal di public_path(). Bersihkan juga folder kosong induknya.
     */
    public function deleteLocal(string $relative): bool
    {
        $abs = public_path($relative);

        if (! is_file($abs)) {
            return false;
        }

        $ok = @unlink($abs);

        if ($ok) {
            $dir = dirname($abs);
            $publicDir = rtrim(public_path(), '/\\').DIRECTORY_SEPARATOR;

            while (strlen($dir) > strlen($publicDir) && @rmdir($dir)) {
                $dir = dirname($dir);
            }
        }

        return $ok;
    }

    /**
     * Minta server DEV menghapus file (HTTP DELETE ke /api/internal/delete-file).
     */
    public function deleteFromDev(string $relativePath): bool
    {
        try {
            $res = Http::timeout($this->timeout)
                ->withHeaders(['X-Internal-Token' => $this->devToken])
                ->delete($this->devUrl.'/api/internal/delete-file', [
                    'path' => $relativePath,
                ]);

            if ($res->successful()) {
                return true;
            }

            Log::warning('StorageService: hapus di DEV gagal', [
                'status' => $res->status(),
                'path' => $relativePath,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('StorageService: exception hapus di DEV', [
                'message' => $e->getMessage(),
                'path' => $relativePath,
            ]);

            return false;
        }
    }
}
