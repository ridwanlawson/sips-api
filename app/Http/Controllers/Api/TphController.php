<?php

namespace App\Http\Controllers\Api;

use App\Models\Tph;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\AllResource;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * @group Apps
 * 
 * @subgroup TPH
 * @subgroupDescription Sub Group untuk TPH
 * 
 */
class TphController extends Controller
{

    /**
     * Memanggil data TPH dari SIPS Mobile.
     *
     * API ini digunakan untuk memanggil data TPH secara keseluruhan. Tetapi jika ingin melakukan filter pada data yang dipanggil, buatlah parameter pada Url berdasarkan _**Query Parameter**_
     *
     * @queryParam notph string Optional. Filter TPH berdasarkan notph. Example: 1
     * @queryParam fieldcode string Optional. Filter TPH berdasarkan fieldcode. Example: A01A
     * @queryParam ancakno string Optional. Filter TPH berdasarkan ancakno. Example: 1A
     * @queryParam typetph integer Optional. Filter TPH berdasarkan typetph 1. Normal TPH; 2. Sharing TPH; 3. Temporary TPH (didalam blok); 4. Pooling TPH (tempat kumpul TBS Double Handling). Example: 1
     * @queryParam status string Optional. Filter TPH berdasarkan status. Example: TRUE
     * @queryParam fcba string Optional. Filter TPH berdasarkan fcba. Example: MTE
     * @queryParam afdeling string Optional. Filter TPH berdasarkan afdeling. Example: AFD-04
     * @queryParam ha string Optional. Filter TPH berdasarkan ha. Example: 2
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "List Data TPH",
     *  "data": [
     *      {
     *          "id": "1",
     *          "notph": "1",
     *          "fieldcode": "A01A",
     *          "ancakno": "1A",
     *          "typetph": "1",
     *          "status": "TRUE",
     *          "location": "2.334993396214831, 117.95918991166465",
     *          "fcba": "MTE",
     *          "division": "AFD-04",
     *          "ha": "2"
     *      }
     *  ]
     * }
     */
    public function index(Request $request)
    {
        try {
            // Ambil parameter dari query string
            $notph = $request->query('notph');
            $fieldcode = $request->query('fieldcode');
            $ancakno = $request->query('ancakno');
            $typetph = $request->query('typetph');
            $status = $request->query('status');
            $fcba = $request->query('fcba');
            $afdeling = $request->query('afdeling');
            $ha = $request->query('ha');

            $query = "
                select 
                    TPH.ID,
                    TPH.NOTPH, 
                    TPH.FIELDCODE, 
                    TPH.ANCAKNO, 
                    TPH.TYPETPH, 
                    TPH.STATUS, 
                    TPH.LOCATION, 
                    TPH.FCBA, 
                    TPH.AFDELING,
                    TPH.HA
                from 
                    SIPSMOBILE.TPH
                inner join 
                    IPLASPROD.FIELD 
                on 
                    TPH.FIELDCODE = FIELD.FCCODE 
                    and TPH.FCBA = FIELD.FCBA 
                where 
                    TPH.FCBA IS NOT NULL";

            // Parameter binding
            $bindings = [];

            // Filter berdasarkan parameter
            if ($notph) {
                $query .= " and TPH.NOTPH = :notph";
                $bindings['notph'] = $notph;
            }

            if ($fieldcode) {
                $query .= " and TPH.FIELDCODE = :fieldcode";
                $bindings['fieldcode'] = $fieldcode;
            }

            if ($ancakno) {
                $query .= " and tph.ANCAKNO = :ancakno";
                $bindings['ancakno'] = $ancakno;
            }

            if ($typetph) {
                $query .= " and tph.TYPETPH = :typetph";
                $bindings['typetph'] = $typetph;
            }

            if ($status) {
                $query .= " and tph.STATUS = :status";
                $bindings['status'] = $status;
            }

            if ($fcba) {
                $query .= " and tph.FCBA = :fcba";
                $bindings['fcba'] = $fcba;
            }

            if ($afdeling) {
                $query .= " and tph.AFDELING = :afdeling";
                $bindings['afdeling'] = $afdeling;
            }

            if ($ha) {
                $query .= " and tph.ha = :ha";
                $bindings['ha'] = $ha;
            }

            // Tambahkan bagian akhir query
            $query .= "
                order by 
                    tph.FCBA,
                    tph.AFDELING,
                    tph.NOTPH,
                    tph.ANCAKNO
            ";

            // Jalankan query
            $datas = DB::connection('oracle')->select($query, $bindings);

            // Jika data kosong
            if (empty($datas)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data tidak ditemukan.',
                    'data' => []
                ], 404);
            }

            return new AllResource(true, 'List Data TPH', $datas);
        } catch (\Exception $e) {
            // Tangani kesalahan yang mungkin terjadi
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage() // Tambahkan pesan error teknis jika diperlukan
            ], 500);
        }
    }


    /**
     * Menyimpan data TPH ke dalam database SIPS Mobile.
     */
    public function store(Request $request)
    {
        // Validasi input
        $validated = $request->validate([
            'notph' => 'required|string',
            'fieldcode' => 'required|string|exists:sips_production.field,fccode',
            'ancakno' => 'required|string',
            'fcba' => 'required|string|exists:sips_production.field,fcba',
            'afdeling' => 'required|string|exists:sips_production.field,division',
            'typetph' => 'required|integer',
            'status' => 'required|string',
            'location' => 'nullable',
            'ha' => 'required|numeric',
            'created_by' => 'nullable',
        ]);

        try {

            $validated['created_by'] = Auth::user()->username;

            // Menyimpan data ke database
            $datas = Tph::create($validated);

            // Mengembalikan respon sukses
            return new AllResource(true, 'Data TPH berhasil ditambahkan.', $datas);
        } catch (\Exception $e) {
            // Menangkap error dan mengembalikan pesan yang mudah dipahami oleh user
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.',
                'error' => $e->getMessage() // Tambahkan pesan error teknis jika perlu
            ], 500);
        }
    }

    /**
     * Menampilkan data TPH berdasarkan id TPH dari SIPS Mobile.
     *
     * @urlParam id integer required ID TPH.
     */
    public function show(string $id)
    {
        try {
            $datas = Tph::findOrFail($id);
            return new AllResource(true, 'Detail Data TPH', $datas);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data TPH tidak ditemukan.',
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
     * Mengubah data TPH berdasarkan id TPH.
     *
     * @urlParam id integer required ID TPH.
     */
    public function update(Request $request, string $id)
    {
        // Validasi input
        $validated = $request->validate([
            'notph' => 'required|string',
            'fieldcode' => 'required|string|exists:sips_production.field,fccode',
            'ancakno' => 'required|string',
            'fcba' => 'required|string|exists:sips_production.field,fcba',
            'afdeling' => 'required|string|exists:sips_production.field,division',
            'typetph' => 'required|string',
            'status' => 'required|string',
            'location' => 'nullable',
            'ha' => 'required|numeric',
            'updated_by' => 'nullable',
        ]);

        try {

            $datas = Tph::findOrFail($id);

            // Jika data tidak ditemukan
            if (!$datas) {
                return response()->json([
                    'success' => false,
                    'message' => 'TPH tidak ditemukan'
                ], 404);
            }

            $validated['updated_by'] = Auth::user()->username;

            $datas->update($validated);

            return new AllResource(true, 'Data TPH berhasil diperbarui.', $datas);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data TPH tidak ditemukan.',
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupdate data.',
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

    /**
     * Menghapus data TPH berdasarkan id TPH.
     *
     * @urlParam id integer required ID TPH.
     */
    public function destroy(string $id)
    {
        try {
            $datas = Tph::findOrFail($id);
            $datas->delete();
            return new AllResource(true, 'Data TPH berhasil dihapus.', $datas);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data TPH tidak ditemukan.',
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
