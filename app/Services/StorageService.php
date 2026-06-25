<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StorageService
{
    protected string $devUrl;
    protected string $devToken;
    protected int $timeout;

    public function __construct()
    {
        $this->devUrl = rtrim(config("app.dev_server_url", ""), "/");
        $this->devToken = config("app.internal_sync_token", "");
        $this->timeout = (int) config("app.dev_server_timeout", 5);
    }

    public function isDevOnline(): bool
    {
        try {
            $res = Http::timeout($this->timeout)->get(
                $this->devUrl . "/api/health",
            );
            return $res->successful();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Upload file lokal PROD ke DEV.
     *
     * @param string $localAbsPath  Path absolut file di PROD, contoh: public_path('file/attendance/...')
     * @param string $relativePath  Path relatif tujuan di DEV, contoh: 'file/attendance/images/mte/2025/01/01/foto.jpg'
     * @return string|null          URL publik file di DEV, atau null jika gagal
     */
    public function uploadToDev(
        string $localAbsPath,
        string $relativePath,
    ): ?string {
        try {
            $res = Http::timeout(60)
                ->withHeaders(["X-Internal-Token" => $this->devToken])
                ->attach(
                    "file",
                    file_get_contents($localAbsPath),
                    basename($relativePath),
                )
                ->post($this->devUrl . "/api/internal/receive-file", [
                    "path" => $relativePath,
                ]);

            if ($res->successful()) {
                return $res->json("url");
            }

            Log::warning("StorageService: upload ke DEV gagal", [
                "status" => $res->status(),
                "path" => $relativePath,
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error("StorageService: exception saat upload", [
                "message" => $e->getMessage(),
                "path" => $localAbsPath,
            ]);
            return null;
        }
    }

    /**
     * Simpan file ke PROD lokal, lalu coba upload ke DEV.
     * Jika DEV online dan upload sukses → hapus dari PROD, return URL DEV.
     * Jika DEV offline atau gagal → biarkan di PROD, return URL PROD.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $relativePath  Path relatif tujuan (tanpa leading slash)
     * @return string               URL publik final (DEV atau PROD)
     */
    public function storeFile(
        \Illuminate\Http\UploadedFile $file,
        string $relativePath,
    ): string {
        $directory = public_path(dirname($relativePath));

        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        $file->move($directory, basename($relativePath));
        // Kalau server ini DEV, tidak perlu sync ke mana-mana
        if (config("app.server_role") === "dev") {
            return asset($relativePath);
        }

        $localAbsPath = public_path($relativePath);

        if ($this->isDevOnline()) {
            // Kalau server ini DEV, tidak relevan
            if (config("app.server_role") === "dev") {
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
        if (!$url) {
            return false;
        }
        return str_starts_with($url, rtrim(config("app.url"), "/"));
    }

    /**
     * Konversi URL publik PROD ke path relatif untuk public_path().
     * Contoh: http://192.168.1.10/file/attendance/... → file/attendance/...
     */
    public function urlToRelativePath(string $url): string
    {
        $prodBase = rtrim(config("app.url"), "/");
        return ltrim(str_replace($prodBase, "", $url), "/");
    }
}
