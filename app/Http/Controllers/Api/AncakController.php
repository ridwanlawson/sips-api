<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAncakRequest;
use App\Http\Requests\UpdateAncakRequest;
use App\Http\Resources\AllResource;
use App\Models\Ancak;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * @group Apps
 *
 * @subgroup Ancak
 * @subgroupDescription Sub Group untuk Ancak Management (Sub dari TPH)
 *
 */
class AncakController extends Controller
{
    /**
     * Memanggil data ancak dari SIPS Mobile.
     *
     * API ini digunakan untuk memanggil data ancak secara keseluruhan dengan dukungan filter dan pencarian.
     * Setiap ancak merupakan sub-unit dari TPH dan memiliki informasi luas tanah.
     * Data diurutkan berdasarkan FCBA, Afdeling, dan Nomor Ancak.
     *
     * @queryParam q string Optional. Search ancak berdasarkan noancak, fieldcode, atau afdeling. Example: 1A
     * @queryParam fcba string Optional. Filter ancak berdasarkan fcba. Example: MTE
     * @queryParam afdeling string Optional. Filter ancak berdasarkan afdeling. Example: AFD-01
     * @queryParam fieldcode string Optional. Filter ancak berdasarkan fieldcode. Example: A01A
     * @queryParam noancak string Optional. Filter ancak berdasarkan nomor ancak. Example: 1A
     * @queryParam status string Optional. Filter ancak berdasarkan status (active/inactive/suspended). Example: active
     * @queryParam tph_id integer Optional. Filter ancak berdasarkan TPH ID. Example: 1
     * @queryParam per_page integer Optional. Jumlah data per halaman. Default: 15. Example: 10
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "List Data Ancak",
     *  "data": [
     *      {
     *          "id": 1,
     *          "fcba": "MTE",
     *          "afdeling": "AFD-01",
     *          "fieldcode": "A01A",
     *          "noancak": "1A",
     *          "luas": "25.50",
     *          "tph_id": 5,
     *          "tph": null,
     *          "status": "active",
     *          "notes": "Ancak untuk panen reguler",
     *          "created_by": "admin",
     *          "updated_by": "admin",
     *          "created_at": "2025-12-12T10:00:00+08:00",
     *          "updated_at": "2025-12-12T10:00:00+08:00"
     *      }
     *  ],
     *  "meta": {
     *      "current_page": 1,
     *      "from": 1,
     *      "last_page": 1,
     *      "per_page": 15,
     *      "to": 1,
     *      "total": 1
     *  }
     * }
     */
    public function index(Request $request)
    {
        try {
            $query = Ancak::query();

            // Filter berdasarkan search parameter
            if ($search = $request->query('q')) {
                $query->where(function ($q) use ($search) {
                    $q->where('noancak', 'like', "%{$search}%")
                        ->orWhere('fieldcode', 'like', "%{$search}%")
                        ->orWhere('afdeling', 'like', "%{$search}%");
                });
            }

            // Filter berdasarkan fcba
            if ($fcba = $request->query('fcba')) {
                $query->where('fcba', '=', $fcba);
            }

            // Filter berdasarkan afdeling
            if ($afdeling = $request->query('afdeling')) {
                $query->where('afdeling', '=', $afdeling);
            }

            // Filter berdasarkan fieldcode
            if ($fieldcode = $request->query('fieldcode')) {
                $query->where('fieldcode', '=', $fieldcode);
            }

            // Filter berdasarkan noancak
            if ($noancak = $request->query('noancak')) {
                $query->where('noancak', '=', $noancak);
            }

            // Filter berdasarkan status
            if ($status = $request->query('status')) {
                $query->where('status', '=', $status);
            }

            // Filter berdasarkan tph_id
            if ($tph_id = $request->query('tph_id')) {
                $query->where('tph_id', '=', $tph_id);
            }

            $perPage = (int) $request->query('per_page', 15);

            $ancaks = $query->orderBy('fcba')
                ->orderBy('afdeling')
                ->orderBy('noancak')
                ->paginate($perPage);

            return new AllResource(true, 'List Data Ancak', $ancaks);
        } catch (\Exception $e) {
            Log::error('Ancak index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan data ancak berdasarkan id ancak.
     *
     * @urlParam id integer required ID Ancak. Example: 1
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Detail Data Ancak",
     *  "data": {
     *      "id": 1,
     *      "fcba": "MTE",
     *      "afdeling": "AFD-01",
     *      "fieldcode": "A01A",
     *      "noancak": "1A",
     *      "luas": "25.50",
     *      "tph_id": 5,
     *      "tph": null,
     *      "status": "active",
     *      "notes": "Ancak untuk panen reguler",
     *      "created_by": "admin",
     *      "updated_by": "admin",
     *      "created_at": "2025-12-12T10:00:00+08:00",
     *      "updated_at": "2025-12-12T10:00:00+08:00"
     *  }
     * }
     */
    public function show($id)
    {
        try {
            $ancak = Ancak::with('tph')->findOrFail($id);
            return new AllResource(true, 'Detail Data Ancak', $ancak);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data ancak tidak ditemukan.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Ancak show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyimpan data ancak ke dalam database SIPS Mobile.
     *
     * @bodyParam fcba string optional Kode FCBA. Example: MTE
     * @bodyParam afdeling string optional Kode afdeling/divisi. Example: AFD-01
     * @bodyParam fieldcode string optional Kode lapangan. Example: A01A
     * @bodyParam noancak string required Nomor ancak (unik). Example: 1A
     * @bodyParam luas double optional Luas ancak dalam hektar. Example: 25.50
     * @bodyParam tph_id integer optional ID TPH yang terkait. Example: 5
     * @bodyParam status string optional Status ancak (active/inactive/suspended). Default: active. Example: active
     * @bodyParam notes string optional Catatan tambahan. Example: Ancak untuk panen reguler
     * @bodyParam created_by string optional Nama user yang membuat. Example: admin
     *
     * @response 201 scenario="success" {
     *  "success": true,
     *  "message": "Data Ancak berhasil ditambahkan.",
     *  "data": {
     *      "id": 1,
     *      "fcba": "MTE",
     *      "afdeling": "AFD-01",
     *      "fieldcode": "A01A",
     *      "noancak": "1A",
     *      "luas": "25.50",
     *      "tph_id": 5,
     *      "tph": null,
     *      "status": "active",
     *      "notes": "Ancak untuk panen reguler",
     *      "created_by": "admin",
     *      "updated_by": null,
     *      "created_at": "2025-12-12T10:00:00+08:00",
     *      "updated_at": "2025-12-12T10:00:00+08:00"
     *  }
     * }
     */
    public function store(StoreAncakRequest $request)
    {
        try {
            $data = $request->validated();

            // Set created_by jika belum ada
            if (!isset($data['created_by'])) {
                $user = Auth::user();
                $data['created_by'] = $user ? $user->username : 'system';
            }

            $ancak = Ancak::create($data);

            return new AllResource(true, 'Data Ancak berhasil ditambahkan.', $ancak);
        } catch (QueryException $e) {
            Log::error('Ancak store database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data.',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('Ancak store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengubah data ancak berdasarkan id ancak.
     *
     * @urlParam id integer required ID Ancak. Example: 1
     *
     * @bodyParam fcba string optional Kode FCBA. Example: MTE
     * @bodyParam afdeling string optional Kode afdeling/divisi. Example: AFD-01
     * @bodyParam fieldcode string optional Kode lapangan. Example: A01A
     * @bodyParam noancak string optional Nomor ancak (unik). Example: 1A
     * @bodyParam luas double optional Luas ancak dalam hektar. Example: 25.50
     * @bodyParam tph_id integer optional ID TPH yang terkait. Example: 5
     * @bodyParam status string optional Status ancak. Example: active
     * @bodyParam notes string optional Catatan tambahan. Example: Updated
     * @bodyParam updated_by string optional Nama user yang mengupdate. Example: admin
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data Ancak berhasil diperbarui.",
     *  "data": {
     *      "id": 1,
     *      "fcba": "MTE",
     *      "afdeling": "AFD-01",
     *      "fieldcode": "A01A",
     *      "noancak": "1A",
     *      "luas": "25.50",
     *      "tph_id": 5,
     *      "tph": null,
     *      "status": "active",
     *      "notes": "Updated",
     *      "created_by": "admin",
     *      "updated_by": "admin",
     *      "created_at": "2025-12-12T10:00:00+08:00",
     *      "updated_at": "2025-12-12T11:00:00+08:00"
     *  }
     * }
     */
    public function update(UpdateAncakRequest $request, $id)
    {
        try {
            $ancak = Ancak::findOrFail($id);

            $data = $request->validated();

            // Set updated_by jika ada perubahan
            if (!isset($data['updated_by'])) {
                $user = Auth::user();
                $data['updated_by'] = $user ? $user->username : 'system';
            }

            $ancak->update($data);

            return new AllResource(true, 'Data Ancak berhasil diperbarui.', $ancak);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data ancak tidak ditemukan.'
            ], 404);
        } catch (QueryException $e) {
            Log::error('Ancak update database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui data.',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('Ancak update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menghapus data ancak berdasarkan id ancak.
     *
     * @urlParam id integer required ID Ancak. Example: 1
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data Ancak berhasil dihapus.",
     *  "data": {
     *      "id": 1,
     *      "fcba": "MTE",
     *      "afdeling": "AFD-01",
     *      "fieldcode": "A01A",
     *      "noancak": "1A",
     *      "luas": "25.50",
     *      "tph_id": 5,
     *      "tph": null,
     *      "status": "active",
     *      "notes": "Ancak untuk panen reguler",
     *      "created_by": "admin",
     *      "updated_by": "admin",
     *      "created_at": "2025-12-12T10:00:00+08:00",
     *      "updated_at": "2025-12-12T10:00:00+08:00"
     *  }
     * }
     */
    public function destroy($id)
    {
        try {
            $ancak = Ancak::findOrFail($id);
            $ancak->delete();

            return new AllResource(true, 'Data Ancak berhasil dihapus.', $ancak);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data ancak tidak ditemukan.'
            ], 404);
        } catch (QueryException $e) {
            Log::error('Ancak destroy database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data.',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('Ancak destroy error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
