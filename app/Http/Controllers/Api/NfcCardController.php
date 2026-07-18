<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AllResource;
use App\Models\NfcCard;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @group Settings
 *
 * @subgroup NFC Card
 * @subgroupDescription Sub Group untuk NFC Card Management
 *
 */
class NfcCardController extends Controller
{
    /**
     * Memanggil data NFC Card.
     *
     * API ini digunakan untuk memanggil data NFC Card secara keseluruhan. Secara default hanya menampilkan kartu aktif (status Y).
     * Untuk melihat kartu yang dinonaktifkan, gunakan parameter status=N.
     *
     * @queryParam fcba string Optional. Filter berdasarkan fcba. Example: MTE
     * @queryParam afdeling string Optional. Filter berdasarkan afdeling. Example: AFD-01
     * @queryParam status string Optional. Filter berdasarkan status (Y/N). Default: Y. Example: N
     * @queryParam ownership string Optional. Filter berdasarkan kepemilikan. Example: Perusahaan
     * @queryParam card_id string Optional. Filter berdasarkan card id. Example: CARD-001
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "List Data NFC Card",
     *  "data": [...]
     * }
     */
    public function index(Request $request)
    {
        try {
            $query = NfcCard::query();

            // Filter berdasarkan fcba
            if ($fcba = $request->query('fcba')) {
                $query->where('fcba', '=', $fcba);
            }

            // Filter berdasarkan afdeling
            if ($afdeling = $request->query('afdeling')) {
                $query->where('afdeling', '=', $afdeling);
            }

            // Filter berdasarkan status - default Y
            if ($status = $request->query('status')) {
                $query->where('status', '=', $status);
            } else {
                $query->where('status', '=', 'Y');
            }

            // Filter berdasarkan ownership
            if ($ownership = $request->query('ownership')) {
                $query->where('ownership', '=', $ownership);
            }

            // Filter berdasarkan card_id
            if ($cardId = $request->query('card_id')) {
                $query->where('card_id', '=', $cardId);
            }

            $data = $query->orderBy('id', 'desc')->get();

            if ($data->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data tidak ditemukan.',
                    'data' => []
                ], 404);
            }

            return new AllResource(true, 'List Data NFC Card', $data);
        } catch (\Exception $e) {
            Log::error('NfcCard index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan data NFC Card berdasarkan id.
     *
     * @urlParam id integer required ID NFC Card. Example: 1
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Detail Data NFC Card",
     *  "data": {...}
     * }
     */
    public function show($id)
    {
        try {
            $data = NfcCard::findOrFail($id);
            return new AllResource(true, 'Detail Data NFC Card', $data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data NFC Card tidak ditemukan.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('NfcCard show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyimpan data NFC Card baru.
     *
     * @bodyParam uid string required UID unik dari chip NFC. Example: NFC-001
     * @bodyParam card_id string optional Nomor identitas kartu. Example: CARD-001
     * @bodyParam ownership string optional Kepemilikan kartu. Example: Perusahaan
     * @bodyParam status string optional Status kartu (Y/N). Default: Y. Example: Y
     * @bodyParam notes string optional Catatan tambahan. Example: Kartu baru
     * @bodyParam fcba string optional Kode bisnis unit. Example: MTE
     * @bodyParam afdeling string optional Kode afdeling. Example: AFD-01
     * @bodyParam registered_at string optional Tanggal registrasi (format: YYYY-MM-DD HH:MM:SS). Example: 2026-07-17 10:00:00
     *
     * @response 201 scenario="success" {
     *  "success": true,
     *  "message": "Data NFC Card berhasil ditambahkan.",
     *  "data": {...}
     * }
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'uid' => 'required|string|unique:nfc_cards,uid',
            'card_id' => 'nullable|string',
            'ownership' => 'nullable|string',
            'status' => 'nullable|string|in:Y,N',
            'notes' => 'nullable|string',
            'fcba' => 'nullable|string',
            'afdeling' => 'nullable|string',
            'registered_at' => 'nullable|date',
        ]);

        try {
            $validated['created_by'] = Auth::user()->username ?? Auth::user()->name;
            $validated['status'] = $validated['status'] ?? 'Y';

            $data = NfcCard::create($validated);

            return new AllResource(true, 'Data NFC Card berhasil ditambahkan.', $data);
        } catch (QueryException $e) {
            Log::error('NfcCard store database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data.',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('NfcCard store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengubah data NFC Card berdasarkan id.
     *
     * @urlParam id integer required ID NFC Card. Example: 1
     *
     * @bodyParam uid string optional UID unik dari chip NFC. Example: NFC-001
     * @bodyParam card_id string optional Nomor identitas kartu. Example: CARD-001
     * @bodyParam ownership string optional Kepemilikan kartu. Example: Perusahaan
     * @bodyParam status string optional Status kartu (Y/N). Example: Y
     * @bodyParam notes string optional Catatan tambahan. Example: Kartu diperbarui
     * @bodyParam fcba string optional Kode bisnis unit. Example: MTE
     * @bodyParam afdeling string optional Kode afdeling. Example: AFD-01
     * @bodyParam registered_at string optional Tanggal registrasi. Example: 2026-07-17 10:00:00
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data NFC Card berhasil diperbarui.",
     *  "data": {...}
     * }
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'uid' => 'sometimes|string|unique:nfc_cards,uid,' . $id,
            'card_id' => 'nullable|string',
            'ownership' => 'nullable|string',
            'status' => 'nullable|string|in:Y,N',
            'notes' => 'nullable|string',
            'fcba' => 'nullable|string',
            'afdeling' => 'nullable|string',
            'registered_at' => 'nullable|date',
        ]);

        try {
            $data = NfcCard::findOrFail($id);
            $validated['updated_by'] = Auth::user()->username ?? Auth::user()->name;
            $data->update($validated);

            return new AllResource(true, 'Data NFC Card berhasil diperbarui.', $data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data NFC Card tidak ditemukan.'
            ], 404);
        } catch (QueryException $e) {
            Log::error('NfcCard update database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui data.',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('NfcCard update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menghapus data NFC Card (soft delete dengan status N).
     *
     * @urlParam id integer required ID NFC Card. Example: 1
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data NFC Card berhasil dihapus.",
     *  "data": {...}
     * }
     */
    public function destroy($id)
    {
        try {
            $data = NfcCard::findOrFail($id);
            $data->status = 'N';
            $data->updated_by = Auth::user()->username ?? Auth::user()->name;
            $data->save();

            $data->delete();

            return new AllResource(true, 'Data NFC Card berhasil dihapus.', $data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data NFC Card tidak ditemukan.'
            ], 404);
        } catch (QueryException $e) {
            Log::error('NfcCard destroy database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data.',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('NfcCard destroy error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
