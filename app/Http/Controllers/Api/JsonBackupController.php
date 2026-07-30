<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AllResource;
use App\Models\BackupJsonFile;
use App\Services\StorageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * @group Backup
 */
class JsonBackupController extends Controller
{
    protected StorageService $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Upload file JSON ke folder backup.
     *
     * Endpoint ini digunakan untuk mengupload file .json yang akan disimpan
     * di folder berdasarkan kegiatan (attendance, harvest, transport), fcba,
     * afdeling, tahun, bulan, dan tanggal. Metadata file disimpan di database.
     *
     * @bodyParam file file required File JSON yang akan diupload.
     * @bodyParam activity string required Kegiatan: attendance, harvest, transport. Example: attendance
     * @bodyParam fcba string required Kode FCBA. Example: MTE
     * @bodyParam afdeling string required Kode afdeling. Example: II
     * @bodyParam tanggal date required Tanggal (format: YYYY-MM-DD). Example: 2025-01-15
     *
     * @response 200 {
     *  "success": true,
     *  "message": "File JSON berhasil diupload.",
     *  "data": {
     *      "id": 1,
     *      "activity": "attendance",
     *      "fcba": "MTE",
     *      "afdeling": "II",
     *      "tanggal": "2025-01-15",
     *      "year": "2025",
     *      "month": "01",
     *      "date": "15",
     *      "file_name": "attendance_20250115_142530123_MTE_II.json",
     *      "file_path": "file/json/attendance/MTE/II/2025/01/15/attendance_20250115_142530123_MTE_II.json",
     *      "file_size": 1024,
     *      "url": "http://domain.com/file/json/attendance/MTE/II/2025/01/15/attendance_20250115_142530123_MTE_II.json",
     *      "uploaded_by": "andrew",
     *      "created_at": "2026-07-30T10:00:00.000000Z"
     *  }
     * }
     * @response 422 {
     *  "success": false,
     *  "message": "Validasi gagal.",
     *  "errors": {
     *      "file": ["File harus berupa JSON."]
     *  }
     * }
     */
    public function upload(Request $request)
    {
        try {
            $validated = $request->validate([
                'file' => 'required|file|mimes:json',
                'activity' => 'required|in:attendance,harvest,transport',
                'fcba' => 'required|string|max:50',
                'afdeling' => 'required|string|max:50',
                'tanggal' => 'required|date',
            ]);

            $file = $request->file('file');

            $date = Carbon::parse($validated['tanggal']);
            $year = $date->format('Y');
            $month = $date->format('m');
            $day = $date->format('d');
            $now = now();
            $hour = $now->format('H');
            $minute = $now->format('i');
            $second = $now->format('s');
            $millisecond = $now->format('v');

            $fileName = sprintf(
                '%s_%s%s%s_%s%s%s%s_%s_%s.json',
                $validated['activity'],
                $year,
                $month,
                $day,
                $hour,
                $minute,
                $second,
                $millisecond,
                $validated['fcba'],
                $validated['afdeling']
            );

            $relativePath = sprintf(
                'file/json/%s/%s/%s/%s/%s/%s/%s',
                $validated['activity'],
                $validated['fcba'],
                $validated['afdeling'],
                $year,
                $month,
                $day,
                $fileName
            );

            $fileSize = $file->getSize();
            $url = $this->storageService->storeFile($file, $relativePath);

            $backupFile = BackupJsonFile::create([
                'activity' => $validated['activity'],
                'fcba' => $validated['fcba'],
                'afdeling' => $validated['afdeling'],
                'tanggal' => $validated['tanggal'],
                'year' => $year,
                'month' => $month,
                'date' => $day,
                'file_name' => $fileName,
                'file_path' => $relativePath,
                'file_size' => $fileSize,
                'url' => $url,
                'uploaded_by' => Auth::user()->username ?? 'system',
            ]);

            return new AllResource(
                true,
                'File JSON berhasil diupload.',
                $backupFile
            );
        } catch (\Exception $e) {
            Log::error('Backup JSON upload error: '.$e->getMessage(), [
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupload file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List file JSON backup dengan filter dinamis.
     *
     * @queryParam activity string optional Filter berdasarkan kegiatan. Example: attendance
     * @queryParam fcba string optional Filter berdasarkan kode FCBA. Example: MTE
     * @queryParam afdeling string optional Filter berdasarkan kode afdeling. Example: II
     * @queryParam tanggal date optional Filter berdasarkan tanggal (YYYY-MM-DD). Example: 2025-01-15
     * @queryParam year string optional Filter berdasarkan tahun. Example: 2025
     * @queryParam month string optional Filter berdasarkan bulan. Example: 01
     * @queryParam date string optional Filter berdasarkan tanggal. Example: 15
     *
     * @response 200 {
     *  "success": true,
     *  "message": "Daftar file JSON berhasil diambil.",
     *  "data": [
     *      {
     *          "id": 1,
     *          "activity": "attendance",
     *          "fcba": "MTE",
     *          "afdeling": "II",
     *          "tanggal": "2025-01-15",
     *          "file_name": "attendance_20250115_MTE_II.json",
     *          "file_size": 1024,
     *          "url": "http://domain.com/file/json/attendance/MTE/II/2025/01/15/attendance_20250115_MTE_II.json",
     *          "uploaded_by": "andrew",
     *          "created_at": "2026-07-30T10:00:00.000000Z"
     *      }
     *  ]
     * }
     */
    public function index(Request $request)
    {
        try {
            $query = BackupJsonFile::query();

            if ($request->filled('activity')) {
                $query->where('activity', $request->input('activity'));
            }

            if ($request->filled('fcba')) {
                $query->where('fcba', $request->input('fcba'));
            }

            if ($request->filled('afdeling')) {
                $query->where('afdeling', $request->input('afdeling'));
            }

            if ($request->filled('tanggal')) {
                $query->whereDate('tanggal', $request->input('tanggal'));
            }

            if ($request->filled('year')) {
                $query->where('year', $request->input('year'));
            }

            if ($request->filled('month')) {
                $query->where('month', $request->input('month'));
            }

            if ($request->filled('date')) {
                $query->where('date', $request->input('date'));
            }

            $data = $query->orderBy('created_at', 'desc')->get();

            return new AllResource(
                true,
                'Daftar file JSON berhasil diambil.',
                $data
            );
        } catch (\Exception $e) {
            Log::error('Backup JSON index error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download file JSON berdasarkan ID.
     *
     * @urlParam id integer required ID file backup. Example: 1
     *
     * @response 200 file
     * @response 404 {
     *  "success": false,
     *  "message": "File tidak ditemukan."
     * }
     */
    public function download($id)
    {
        try {
            $backupFile = BackupJsonFile::find($id);

            if (! $backupFile) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan.',
                ], 404);
            }

            $filePath = public_path($backupFile->file_path);

            if (File::exists($filePath)) {
                return response()->download($filePath);
            }

            if ($backupFile->url) {
                $response = Http::timeout(30)->get($backupFile->url);

                if ($response->successful()) {
                    $tempPath = tempnam(sys_get_temp_dir(), 'backup_').'.'.pathinfo($backupFile->file_name, PATHINFO_EXTENSION);
                    file_put_contents($tempPath, $response->body());

                    return response()->download($tempPath, $backupFile->file_name)->deleteFileAfterSend(true);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan di storage.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Backup JSON download error: '.$e->getMessage(), [
                'id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengunduh file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Hapus file JSON berdasarkan ID.
     *
     * @urlParam id integer required ID file backup. Example: 1
     *
     * @response 200 {
     *  "success": true,
     *  "message": "File JSON berhasil dihapus.",
     *  "data": null
     * }
     * @response 404 {
     *  "success": false,
     *  "message": "File tidak ditemukan."
     * }
     */
    public function destroy($id)
    {
        try {
            $backupFile = BackupJsonFile::find($id);

            if (! $backupFile) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak ditemukan.',
                ], 404);
            }

            $filePath = public_path($backupFile->file_path);

            if (File::exists($filePath)) {
                File::delete($filePath);
            }

            $backupFile->delete();

            return new AllResource(
                true,
                'File JSON berhasil dihapus.',
                null
            );
        } catch (\Exception $e) {
            Log::error('Backup JSON destroy error: '.$e->getMessage(), [
                'id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus file.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
