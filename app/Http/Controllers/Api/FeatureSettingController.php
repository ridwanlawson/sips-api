<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AllResource;
use App\Models\FeatureSetting;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @group Settings
 *
 * @subgroup Feature Settings
 * @subgroupDescription Sub Group untuk pengaturan fitur per menu, fcba, dan afdeling
 */
class FeatureSettingController extends Controller
{
    /**
     * Cek status fitur berdasarkan menu, feature, fcba, dan afdeling.
     *
     * Endpoint ini digunakan oleh frontend/Android untuk mengecek apakah suatu fitur diizinkan (Y) atau tidak (N).
     *
     * Prioritas pencarian:
     * 1. Exact match menu + feature + fcba + afdeling
     * 2. menu + feature + fcba + afdeling IS NULL (semua afdeling di fcba tsb)
     * 3. menu + feature + fcba IS NULL + afdeling IS NULL (global)
     * 4. Default Y jika tidak ada setting
     *
     * @queryParam menu string required Nama menu. Example: TPH
     * @queryParam feature string required Nama fitur. Example: add
     * @queryParam fcba string required Kode business unit. Example: MTE
     * @queryParam afdeling string required Kode afdeling. Example: AFD-04
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Status fitur",
     *  "data": {
     *      "menu": "TPH",
     *      "feature": "add",
     *      "fcba": "MTE",
     *      "afdeling": "AFD-04",
     *      "status": "Y"
     *  }
     * }
     */
    public function checkStatus(Request $request)
    {
        try {
            $request->validate([
                'menu'     => 'required|string',
                'feature'  => 'required|string',
                'fcba'     => 'required|string',
                'afdeling' => 'required|string',
            ]);

            $menu     = $request->query('menu');
            $feature  = $request->query('feature');
            $fcba     = $request->query('fcba');
            $afdeling = $request->query('afdeling');

            $setting = FeatureSetting::where('menu', $menu)
                ->where('feature', $feature)
                ->where('status', 'N')
                ->where(function ($q) use ($fcba, $afdeling) {
                    $q->where(function ($q2) use ($fcba, $afdeling) {
                        $q2->where('fcba', $fcba)
                           ->where('afdeling', $afdeling);
                    });
                    $q->orWhere(function ($q2) use ($fcba) {
                        $q2->where('fcba', $fcba)
                           ->whereNull('afdeling');
                    });
                    $q->orWhere(function ($q2) {
                        $q2->whereNull('fcba')
                           ->whereNull('afdeling');
                    });
                })
                ->orderByRaw("CASE
                    WHEN fcba IS NOT NULL AND afdeling IS NOT NULL THEN 0
                    WHEN fcba IS NOT NULL AND afdeling IS NULL THEN 1
                    ELSE 2
                END")
                ->first();

            $status = $setting ? 'N' : 'Y';

            return new AllResource(true, 'Status fitur', [
                'menu'     => $menu,
                'feature'  => $feature,
                'fcba'     => $fcba,
                'afdeling' => $afdeling,
                'status'   => $status,
            ]);
        } catch (\Exception $e) {
            Log::error('FeatureSetting checkStatus error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengecek status fitur.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List data Feature Settings.
     *
     * @queryParam menu string Optional. Filter berdasarkan menu. Example: TPH
     * @queryParam feature string Optional. Filter berdasarkan feature. Example: add
     * @queryParam fcba string Optional. Filter berdasarkan fcba. Example: MTE
     * @queryParam afdeling string Optional. Filter berdasarkan afdeling. Example: AFD-04
     * @queryParam status string Optional. Filter berdasarkan status Y/N. Example: N
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "List Data Feature Settings",
     *  "data": [...]
     * }
     */
    public function index(Request $request)
    {
        try {
            $query = FeatureSetting::query();

            if ($menu = $request->query('menu')) {
                $query->where('menu', $menu);
            }

            if ($feature = $request->query('feature')) {
                $query->where('feature', $feature);
            }

            if ($fcba = $request->query('fcba')) {
                $query->where('fcba', $fcba);
            }

            if ($afdeling = $request->query('afdeling')) {
                $query->where('afdeling', $afdeling);
            }

            if ($status = $request->query('status')) {
                $query->where('status', $status);
            }

            $data = $query->orderBy('id', 'desc')->get();

            if ($data->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data tidak ditemukan.',
                    'data'    => [],
                ], 404);
            }

            return new AllResource(true, 'List Data Feature Settings', $data);
        } catch (\Exception $e) {
            Log::error('FeatureSetting index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menampilkan detail Feature Setting berdasarkan id.
     *
     * @urlParam id integer required ID Feature Setting.
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Detail Data Feature Setting",
     *  "data": {...}
     * }
     */
    public function show($id)
    {
        try {
            $data = FeatureSetting::findOrFail($id);
            return new AllResource(true, 'Detail Data Feature Setting', $data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data Feature Setting tidak ditemukan.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('FeatureSetting show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menyimpan data Feature Setting baru.
     *
     * @bodyParam menu string required Nama menu. Example: TPH
     * @bodyParam feature string required Nama fitur. Example: add
     * @bodyParam fcba string optional Kode business unit (null = berlaku semua). Example: MTE
     * @bodyParam afdeling string optional Kode afdeling (null = berlaku semua). Example: AFD-04
     * @bodyParam status string required Status Y/N. Example: N
     *
     * @response 201 scenario="success" {
     *  "success": true,
     *  "message": "Data Feature Setting berhasil ditambahkan.",
     *  "data": {...}
     * }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'menu'     => 'required|string|max:50',
            'feature'  => 'required|string|max:30',
            'fcba'     => 'nullable|string|max:10',
            'afdeling' => 'nullable|string|max:20',
            'status'   => 'required|string|in:Y,N',
        ]);

        try {
            $exists = FeatureSetting::where('menu', $validated['menu'])
                ->where('feature', $validated['feature'])
                ->where('fcba', $validated['fcba'])
                ->where('afdeling', $validated['afdeling'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kombinasi menu, feature, fcba, dan afdeling sudah ada.',
                ], 422);
            }

            $validated['created_by'] = Auth::user()->username ?? Auth::user()->name;
            $validated['updated_by'] = Auth::user()->username ?? Auth::user()->name;

            $data = FeatureSetting::create($validated);

            return new AllResource(true, 'Data Feature Setting berhasil ditambahkan.', $data);
        } catch (QueryException $e) {
            Log::error('FeatureSetting store database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data.',
                'error'   => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('FeatureSetting store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mengubah data Feature Setting berdasarkan id.
     *
     * @urlParam id integer required ID Feature Setting.
     *
     * @bodyParam menu string optional Nama menu. Example: TPH
     * @bodyParam feature string optional Nama fitur. Example: add
     * @bodyParam fcba string optional Kode business unit. Example: MTE
     * @bodyParam afdeling string optional Kode afdeling. Example: AFD-04
     * @bodyParam status string optional Status Y/N. Example: N
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data Feature Setting berhasil diperbarui.",
     *  "data": {...}
     * }
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'menu'     => 'sometimes|string|max:50',
            'feature'  => 'sometimes|string|max:30',
            'fcba'     => 'nullable|string|max:10',
            'afdeling' => 'nullable|string|max:20',
            'status'   => 'sometimes|string|in:Y,N',
        ]);

        try {
            $data = FeatureSetting::findOrFail($id);

            if ($request->hasAny(['menu', 'feature', 'fcba', 'afdeling'])) {
                $exists = FeatureSetting::where('menu', $validated['menu'] ?? $data->menu)
                    ->where('feature', $validated['feature'] ?? $data->feature)
                    ->where('fcba', array_key_exists('fcba', $validated) ? $validated['fcba'] : $data->fcba)
                    ->where('afdeling', array_key_exists('afdeling', $validated) ? $validated['afdeling'] : $data->afdeling)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Kombinasi menu, feature, fcba, dan afdeling sudah ada.',
                    ], 422);
                }
            }

            $validated['updated_by'] = Auth::user()->username ?? Auth::user()->name;
            $data->update($validated);

            return new AllResource(true, 'Data Feature Setting berhasil diperbarui.', $data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data Feature Setting tidak ditemukan.',
            ], 404);
        } catch (QueryException $e) {
            Log::error('FeatureSetting update database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui data.',
                'error'   => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('FeatureSetting update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menghapus data Feature Setting.
     *
     * @urlParam id integer required ID Feature Setting.
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data Feature Setting berhasil dihapus.",
     *  "data": {...}
     * }
     */
    public function destroy($id)
    {
        try {
            $data = FeatureSetting::findOrFail($id);
            $data->delete();

            return new AllResource(true, 'Data Feature Setting berhasil dihapus.', $data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data Feature Setting tidak ditemukan.',
            ], 404);
        } catch (QueryException $e) {
            Log::error('FeatureSetting destroy database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data.',
                'error'   => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('FeatureSetting destroy error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
