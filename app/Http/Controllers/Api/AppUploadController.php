<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AppUploadController extends Controller
{

    private $storagePath = 'file/apps';
    private $allowedExtensions = ['apk', 'ipa'];
    private $maxFileSize = 200 * 1024 * 1024; // 200MB


    /*
    |--------------------------------------------------------------------------
    | Upload App
    |--------------------------------------------------------------------------
    */

    /**
     * Upload aplikasi
     *
     * Endpoint ini digunakan untuk upload file aplikasi mobile
     * yang akan digunakan oleh sistem update aplikasi.
     *
     * @group App Update
     * @unauthenticated
     *
     * @bodyParam platform string required Platform aplikasi. Example: android
     * @bodyParam version string required Versi aplikasi. Example: 1.0.0
     * @bodyParam file file required File aplikasi yang akan diupload.
     * @bodyParam force_update boolean Optional. Apakah update wajib. Example: false
     * @bodyParam min_version string Optional. Versi minimum aplikasi. Example: 1.0.0
     * @bodyParam changelog string Optional. Catatan perubahan aplikasi. Example: Bug fix
     *
     * @response 200 {
     *  "success": true,
     *  "data": {
     *      "id": 1,
     *      "platform": "android",
     *      "version": "1.0.0",
     *      "file_name": "app-1.0.0-1710000000.apk",
     *      "file_path": "file/apps/android/app-1.0.0-1710000000.apk",
     *      "file_size": 120000000,
     *      "file_extension": "apk",
     *      "force_update": false,
     *      "changelog": "Bug fix",
     *      "created_at": "2026-03-09"
     *  }
     * }
     *
     */
    public function upload_apk(Request $request)
    {
        set_time_limit(300); // 5 menit
        Log::info('Upload APK dipanggil', [
            'platform' => $request->input('platform'),
            'version' => $request->input('version'),
            'app_name' => $request->input('app_name'),
            'has_file' => $request->hasFile('file'),
            'files' => $request->file('file') ? $request->file('file')->getClientOriginalName() : 'TIDAK ADA FILE',
            'all_input' => $request->all(),
        ]);

        if (!$request->hasFile('file')) {
            return response()->json(['success' => false, 'message' => 'File tidak diterima'], 400);
        }

        if ($request->has('force_update')) {
            $request->merge([
                'force_update' => filter_var($request->force_update, FILTER_VALIDATE_BOOLEAN)
            ]);
        }

        $validated = $request->validate([
            'app_name' => 'required|string',
            'platform' => 'required|in:android,ios',
            'version' => 'required|string',
            'file' => 'required|file|max:' . ($this->maxFileSize / 1024),
            'force_update' => 'nullable|boolean',
            'min_version' => 'nullable|string',
            'changelog' => 'nullable|string'
        ]);

        $file = $request->file('file');

        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, $this->allowedExtensions)) {

            return response()->json([
                'success' => false,
                'message' => 'File extension not allowed'
            ]);
        }

        $path = public_path($this->storagePath . '/' . $validated['app_name'] . '/' . $validated['platform']);

        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        $fileName = 'app-' . $validated['version'] . '-' . time() . '.' . $extension;

        $fileSize = $file->getSize();

        $file->move($path, $fileName);

        $filePath = $this->storagePath . '/' . $validated['app_name'] . '/' . $validated['platform'] . '/' . $fileName;

        $app = AppUpload::create([
            'app_name' => $request->app_name ?? 'sipsmobile',
            'platform' => $validated['platform'],
            'version' => $validated['version'],
            'min_version' => $request->min_version,
            'force_update' => $request->force_update ?? false,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'file_extension' => $extension,
            'changelog' => $request->changelog,
            'uploaded_by' => Auth::user()->username ?? 'system'
        ]);

        return response()->json([
            'success' => true,
            'data' => $app
        ]);
    }



    /*
    |--------------------------------------------------------------------------
    | Check Update
    |--------------------------------------------------------------------------
    */

    /**
     * Check update aplikasi
     *
     * Digunakan oleh mobile app untuk mengecek versi terbaru.
     *
     * @group App Update
     * @unauthenticated
     *
     * @bodyParam platform string required Platform aplikasi. Example: android
     * @bodyParam version string optional Versi aplikasi yang diinginkan user. Example: 1.0.0
     *
     * @response 200 {
     *  "app_name": "sipsmobile",
     *  "update": true,
     *  "latest_version": "1.1.0",
     *  "force_update": false,
     *  "download_url": "https://domain.com/file/apps/android/app-1.1.0.apk",
     *  "file_size": 120000000,
     *  "changelog": "Bug fix & improvement"
     * }
     */
    public function checkUpdate(Request $request)
    {

        $request->validate([
            'app_name' => 'required|string',
            'platform' => 'required',
            'version' => 'nullable',
        ]);

        $appName = strtolower(str_replace(' ', '', $request->app_name));
        $platform = strtolower(str_replace(' ', '', $request->platform));

        if ($request->filled('version')) {
            $latest = AppUpload::where('version', $request->version)
                ->whereRaw('LOWER(REPLACE(platform, \' \', \'\')) = ?', [$platform])
                ->whereRaw('LOWER(REPLACE(app_name, \' \', \'\')) = ?', [$appName])
                ->first();
        } else {
            // Jika tidak ada ID, ambil versi terbaru berdasarkan platform
            $latest = AppUpload::whereRaw('LOWER(REPLACE(platform, \' \', \'\')) = ?', [$platform])
                ->whereRaw('LOWER(REPLACE(app_name, \' \', \'\')) = ?', [$appName])
                ->orderBy('version', 'desc')
                ->first();
        }

        if (!$latest) {
            return response()->json([
                'update' => $latest
            ]);
        }

        // $updateAvailable = version_compare($latest->version, $request->version, '>');

        // if (!$updateAvailable) {

        //     return response()->json([
        //         'update' => $latest
        //     ]);
        // }

        return response()->json([
            'update' => true,
            'app_name' => $latest->app_name,
            'tanggal' => $latest->created_at->toDateString(),
            'latest_version' => $latest->version,
            'force_update' => $latest->force_update,
            'download_url' => url($latest->file_path),
            'file_size' => $latest->file_size,
            'changelog' => $latest->changelog
        ]);
    }



    /*
    |--------------------------------------------------------------------------
    | List Versions
    |--------------------------------------------------------------------------
    */

    /**
     * List semua versi aplikasi
     *
     * @group App Update
     * @unauthenticated
     */

    public function list()
    {

        $uploads = AppUpload::orderBy('version', 'desc')->get();

        // Add full URL to file_path for each upload
        foreach ($uploads as $upload) {
            $upload->file_path = url($upload->file_path);
        }

        return response()->json([
            'success' => true,
            'data' => $uploads
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Version
    |--------------------------------------------------------------------------
    */

    /**
     * Hapus versi aplikasi
     *
     * @group App Update
     * @unauthenticated
     *
     * @urlParam id integer required ID aplikasi. Example: 1
     */

    public function delete($id)
    {

        $app = AppUpload::findOrFail($id);

        $path = public_path($app->file_path);

        if (File::exists($path)) {
            File::delete($path);
        }

        $app->delete();

        return response()->json([
            'success' => true
        ]);
    }
}
