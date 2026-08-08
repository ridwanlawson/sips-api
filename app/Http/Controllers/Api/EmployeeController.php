<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AllResource;
use App\Models\Karyawan;
use App\Services\StorageService;
use App\Traits\FileCleanupTrait;
use App\Traits\ImageOptimizerTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @group Apps
 *
 * @subgroup Karyawan
 *
 * @subgroupDescription Sub Group untuk Karyawan
 */
class EmployeeController extends Controller
{
    use FileCleanupTrait;
    use ImageOptimizerTrait;

    /**
     * Memanggil data karyawan dari SIPS Mobile.
     *
     * API ini digunakan untuk memanggil data karyawan secara keseluruhan. Tetapi jika ingin melakukan filter pada data yang dipanggil, buatlah parameter pada Url berdasarkan _**Query Parameter**_
     *
     * @queryParam fcba string Optional. Filter karyawan berdasarkan fcba. Example: MTE
     * @queryParam sectionname string Optional. Filter karyawan berdasarkan sectionname. Example: AFD-01
     * @queryParam gangcode string Optional. Filter karyawan berdasarkan gangcode. Example: PN011
     * @queryParam noancak string Optional. Filter karyawan berdasarkan noancak. Example: 1
     * @queryParam fctype string Optional. Filter karyawan berdasarkan fctype. Example: W
     * @queryParam fccompanycode string Optional. Filter karyawan berdasarkan fccompanycode. Example: PT.SKJ
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "List Data Karyawan",
     *  "data": [
     *      {
     *          "id": 2447,
     *          "fccode": "06-011128-240520-0642",
     *          "fcname": "RANDIANUS SERAN",
     *          "sectionname": "AFD-01",
     *          "gangcode": "PN011",
     *          "fcba": "MTE",
     *          "noancak": "1",
     *          "created_by": null,
     *          "updated_by": null,
     *          "created_at": null,
     *          "updated_at": null,
     *          "fctype": "W",
     *          "fccompanycode": "PT.SKJ"
     *      }
     *  ]
     * }
     */
    public function index(Request $request)
    {
        try {
            $fcba = $request->query('fcba');
            $sectionname = $request->query('sectionname');
            $gangcode = $request->query('gangcode');
            $noancak = $request->query('noancak');
            $fctype = $request->query('fctype');
            $fccompanycode = $request->query('fccompanycode');

            $datas = DB::table('V_EMPLOYEE_FULL');

            if ($fcba) {
                $datas->where('FCBA', $fcba);
            }

            if ($sectionname) {
                $datas->where('SECTIONNAME', $sectionname);
            }

            if ($gangcode) {
                $datas->where('GANGCODE', $gangcode);
            }

            if ($noancak) {
                $datas->where('NOANCAK', $noancak);
            }

            // 🔥 FILTER DARI ORACLE FUNCTION
            if ($fctype) {
                $datas->where('FCTYPE', $fctype);
            }

            if ($fccompanycode) {
                $datas->where('FCCOMPANYCODE', $fccompanycode);
            }

            $datas = $datas
                ->orderBy('FCBA')
                ->orderBy('SECTIONNAME')
                ->orderBy('GANGCODE')
                ->orderBy('NOANCAK')
                ->get();

            return new AllResource(true, 'List Data Karyawan', $datas);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menyimpan data karyawan ke dalam database SIPS Mobile.
     */
    public function store(Request $request)
    {
        // Validasi input
        $validated = $request->validate([
            'fccode' => 'required|string|exists:sips_production.Employee,fccode',
            'fcname' => 'required|string|exists:sips_production.Employee,fcname',
            'sectionname' => 'required|string|max:255',
            'gangcode' => 'required|string|max:255',
            'fcba' => 'required|string|exists:sips_production.Employee,fcba',
            'noancak' => 'nullable|string',
            'photo' => 'nullable|image|mimes:webp,jpg,jpeg,png|max:2048',
            'created_by' => 'nullable',
            'updated_by' => 'nullable',
            'face_recognition' => 'nullable',
            'fr_registered_at' => 'nullable|date',
        ]);

        try {
            $this->uploadedFiles = [];

            // Inisialisasi variabel path image (default null jika tidak ada file)
            $imagePath = null;

            // Jika ada file image yang diunggah
            if ($request->hasFile('photo')) {
                $storage = app(StorageService::class);
                $folderPath = 'file/employee_photo';
                $relativePath = $this->optimizeAndSaveImage(
                    $request->file('photo'),
                    $folderPath,
                );
                $localAbsPath = public_path($relativePath);

                if ($storage->isDevOnline()) {
                    $devUrl = $storage->uploadToDev(
                        $localAbsPath,
                        $relativePath,
                    );
                    if ($devUrl) {
                        $imagePath = $devUrl;
                        @unlink($localAbsPath);
                    } else {
                        $imagePath = asset($relativePath);
                    }
                } else {
                    $imagePath = asset($relativePath);
                }
            }

            $this->trackUploadedFile($imagePath);

            $validated['photo'] = $imagePath;
            $validated['created_by'] = Auth::user()->username;

            // Menyimpan data ke database
            $datas = Karyawan::create($validated);

            // Mengembalikan respon sukses
            return new AllResource(true, 'Data Karyawan berhasil ditambahkan.', $datas);
        } catch (\Exception $e) {
            // Menangkap error dan mengembalikan pesan yang mudah dipahami oleh user
            $this->cleanupUploadedFiles();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.',
                'error' => $e->getMessage(), // Tambahkan pesan error teknis jika perlu
            ], 500);
        }
    }

    /**
     * Menampilkan data karyawan berdasarkan id karyawan dari SIPS Mobile.
     *
     * @urlParam id integer required ID Karyawan.
     */
    public function show($id)
    {
        Log::info('HTTP Method: '.request()->method());
        try {
            $datas = Karyawan::findOrFail($id);

            return new AllResource(true, 'Detail Data Karyawan', $datas);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data karyawan tidak ditemukan.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mengubah data karyawan berdasarkan id karyawan.
     *
     * @urlParam id integer required ID Karyawan.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'fccode' => 'required|string|exists:sips_production.Employee,fccode',
            'fcname' => 'required|string|exists:sips_production.Employee,fcname',
            'sectionname' => 'required|string|max:255',
            'gangcode' => 'required|string|max:255',
            'fcba' => 'required|string|exists:sips_production.Employee,fcba',
            'noancak' => 'required|string',
            'face_recognition' => 'nullable',
            'fr_registered_at' => 'nullable|date',
            'aktif' => 'nullable|string',
            'photo' => 'nullable|image|mimes:webp,jpg,jpeg,png|max:2048',
            'updated_by' => 'nullable',
        ]);

        try {
            $this->uploadedFiles = [];

            $datas = Karyawan::findOrFail($id);

            // Jika data tidak ditemukan
            if (! $datas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Karyawan tidak ditemukan',
                ], 404);
            }

            $imagePath = $datas->images; // Default gunakan gambar lama

            // Jika ada file image yang diunggah
            if (! empty($request->hasFile('photo'))) {
                $storage = app(StorageService::class);
                $folderPath = 'file/employee_photo';
                $relativePath = $this->optimizeAndSaveImage(
                    $request->file('photo'),
                    $folderPath,
                );
                $localAbsPath = public_path($relativePath);

                if ($storage->isDevOnline()) {
                    $devUrl = $storage->uploadToDev(
                        $localAbsPath,
                        $relativePath,
                    );
                    if ($devUrl) {
                        $imagePath = $devUrl;
                        @unlink($localAbsPath);
                    } else {
                        $imagePath = asset($relativePath);
                    }
                } else {
                    $imagePath = asset($relativePath);
                }

                $validated['photo'] = $imagePath;

                $this->trackUploadedFile($imagePath);
            }

            $validated['updated_by'] = Auth::user()->username;

            $datas->update($validated);

            return new AllResource(true, 'Data Karyawan berhasil diperbarui.', $datas);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data karyawan tidak ditemukan.',
            ], 404);
        } catch (QueryException $e) {
            $this->cleanupUploadedFiles();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupdate data.',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            $this->cleanupUploadedFiles();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menghapus data karyawan berdasarkan id karyawan.
     *
     * @urlParam id integer required ID Karyawan.
     */
    public function destroy($id)
    {
        Log::info('Destroy method called with ID: '.$id);
        Log::info('HTTP Method: '.request()->method());
        try {
            $datas = Karyawan::findOrFail($id);
            $datas->delete();

            return new AllResource(true, 'Data Karyawan berhasil dihapus.', $datas);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data karyawan tidak ditemukan.',
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data.',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
