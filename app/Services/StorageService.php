<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StorageService
{
    protected string $devUrl;
    protected string $devToken;
    protected int    $timeout;

    public function __construct()
    {
        $this->devUrl   = rtrim(config('app.dev_server_url', ''), '/');
        $this->devToken = config('app.internal_sync_token', '');
        $this->timeout  = (int) config('app.dev_server_timeout', 5);
    }

    public function isDevOnline(): bool
    {
        try {
            $res = Http::timeout($this->timeout)
                ->get($this->devUrl . '/api/health');
            return $res->successful();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Upload file lokal PROD ke DEV.
     * $localAbsPath  = path absolut file di PROD (public_path(...))
     * $relativePath  = path relatif tujuan di DEV (misal: file/attendance/images/mte/2025/01/01/foto.jpg)
     * Return URL publik di DEV, atau null jika gagal.
     */
    public function uploadToDev(string $localAbsPath, string $relativePath): ?string
    {
        try {
            $res = Http::timeout(60)
                ->withHeaders(['X-Internal-Token' => $this->devToken])
                ->attach('file', file_get_contents($localAbsPath), basename($relativePath))
                ->post($this->devUrl . '/api/internal/receive-file', [
                    'path' => $relativePath,
                ]);

            if ($res->successful()) {
                return $res->json('url');
            }

            Log::warning('StorageService: upload ke DEV gagal', [
                'status' => $res->status(),
                'path'   => $relativePath,
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('StorageService: exception saat upload', [
                'message' => $e->getMessage(),
                'path'    => $localAbsPath,
            ]);
            return null;
        }
    }

    /**
     * Deteksi apakah URL gambar tersimpan di PROD (bukan DEV).
     */
    public function isStoredOnProd(?string $imageUrl): bool
    {
        if (!$imageUrl) return false;
        $prodBase = rtrim(config('app.url'), '/');
        return str_starts_with($imageUrl, $prodBase);
    }

    /**
     * Konversi URL publik PROD ? path relatif untuk public_path().
     * Contoh: http://192.168.1.10/file/attendance/... ? file/attendance/...
     */
    public function urlToRelativePath(string $imageUrl): string
    {
        $prodBase = rtrim(config('app.url'), '/');
        return ltrim(str_replace($prodBase, '', $imageUrl), '/');
    }
}