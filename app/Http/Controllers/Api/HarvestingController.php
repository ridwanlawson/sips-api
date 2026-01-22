<?php

namespace App\Http\Controllers\Api;

use App\Models\Harvesting;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use App\Http\Resources\AllResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @group Apps
 * 
 * @subgroup Panen
 * @subgroupDescription Sub Group untuk Panen
 * 
 */
class HarvestingController extends Controller
{
    /**
     * Memanggil data Panen dari SIPS Mobile.
     *
     * API ini digunakan untuk memanggil data Panen secara keseluruhan. 
     * Namun, jika ingin melakukan filter pada data yang dipanggil, 
     * gunakan parameter pada URL berdasarkan _**Query Parameter**_.
     * Data diurutkan berdasarkan Tanggal terbaru, Bisnit Unit, Afdeling, dan Kode Karyawan.
     *
     * @queryParam nodokumen string Optional. Filter Panen berdasarkan No Dokumen. Example: SKJ-HOF/MTE/25/01/0001
     * @queryParam tanggal string Optional. Filter Panen berdasarkan tanggal. Harus dalam format YYYY-MM-DD. Example: 2025-11-01
     * @queryParam tanggal_end string Optional. Filter Panen berdasarkan rentang tanggal. Harus dalam format YYYY-MM-DD. Example: 2025-11-20
     * @queryParam kode_karyawan string Optional. Filter Panen berdasarkan kode karyawan. Example: 06-031014-231025-0438
     * @queryParam fcba string Optional. Filter Panen berdasarkan bisnis unit. Example: MTE
     * @queryParam afdeling string Optional. Filter Panen berdasarkan afdeling. Example: AFD-01
     * @queryParam tph string Optional. Filter Panen berdasarkan TPH. Example: TPH-101
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "List Data Panen",
     *  "data": [
     *      {
     *          "id": "1",
     *          "nodokumen": "SKJ-HOF/MTE/25/01/0001",
     *          "tanggal": "2024-12-19",
     *          "kode_karyawan": "06-031014-231025-0438",
     *          "nama_karyawan": "HENDRIKUS KLAU SERAN",
     *          "fcba": "MTE",
     *          "afdeling": "AFD-01",
     *          "tph": "TPH-101",
     *          "fieldcode": "A12",
     *          "output": "150",
     *          "mentah": "5",
     *          "overripe": "3",
     *          "busuk": "2",
     *          "busuk2": "0",
     *          "buahkecil": "1",
     *          "brondol": "8",
     *          "alasbrondol": "0",
     *          "tangkai_panjang": "0",
     *          "images": "",
     *          "id_device": "Xiaomi",
     *          "status_harvesting": "Planned",
     *          "card_id": "NFC 1234567890"
     *      }
     *  ]
     * }
     */
    public function index(Request $request)
    {
        try {
            // Ambil parameter dari query string
            $nodokumen = $request->query('nodokumen');
            $tanggal = $request->query('tanggal');
            $tanggalEnd     = $request->query('tanggal_end');
            $kode_karyawan = $request->query('kode_karyawan');
            $fcba = $request->query('fcba');
            $afdeling = $request->query('afdeling');
            $tph = $request->query('tph');
            $status_harvesting = $request->query('status_harvesting');

            $query = "
                SELECT
                    DISTINCT  
                    HARVESTING.ID,
                    HARVESTING.NODOKUMEN,
                    HARVESTING.TANGGAL,
                    HARVESTING.KODE_KARYAWAN_MANDOR1,
                    MANDOR1.FCNAME AS NAMA_KARYAWAN_MANDOR1,
                    HARVESTING.KODE_KARYAWAN_MANDOR_PANEN,
                    MANDOR_PANEN.FCNAME AS NAMA_KARYAWAN_MANDOR_PANEN,
                    HARVESTING.KODE_KARYAWAN_KERANI,
                    KERANI.FCNAME AS NAMA_KARYAWAN_KERANI,
                    HARVESTING.KODE_KARYAWAN,
                    KARYAWAN.FCNAME AS NAMA_KARYAWAN,
                    HARVESTING.NOANCAK,
                    HARVESTING.TPH,
                    HARVESTING.FIELDCODE,
                    HARVESTING.FCBA,
                    HARVESTING.AFDELING,
                    HARVESTING.OUTPUT,
                    HARVESTING.MENTAH,
                    HARVESTING.OVERRIPE,
                    HARVESTING.BUSUK,
                    HARVESTING.BUSUK2,
                    HARVESTING.BUAHKECIL,
                    HARVESTING.BRONDOL,
                    HARVESTING.ALASBRONDOL,
                    HARVESTING.TANGKAIPANJANG,
                    HARVESTING.STATUS_ASSISTENSI,
                    HARVESTING.STATUS_HARVESTING,
                    HARVESTING.IMAGES,
                    HARVESTING.ID_DEVICE,
                    HARVESTING.CARD_ID
                FROM
                    SIPSMOBILE.HARVESTING
                INNER JOIN
                    SIPSMOBILE.TPH
                ON
                    HARVESTING.TPH = TPH.NOTPH 
                    AND HARVESTING.FIELDCODE = TPH.FIELDCODE
                    AND HARVESTING.NOANCAK = TPH.ANCAKNO
                    AND HARVESTING.AFDELING = TPH.AFDELING
                    AND HARVESTING.FCBA = TPH.FCBA
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE MANDOR1
                ON
                    HARVESTING.KODE_KARYAWAN_MANDOR1 = MANDOR1.FCCODE
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE MANDOR_PANEN
                ON
                    HARVESTING.KODE_KARYAWAN_MANDOR_PANEN = MANDOR_PANEN.FCCODE
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE KERANI
                ON
                    HARVESTING.KODE_KARYAWAN_KERANI = KERANI.FCCODE
                INNER JOIN
                    SIPSMOBILE.EMPLOYEE KARYAWAN
                ON 
                    HARVESTING.KODE_KARYAWAN = KARYAWAN.FCCODE
                WHERE 
                    HARVESTING.TANGGAL IS NOT NULL
            ";

            $bindings = [];

            if ($nodokumen) {
                $query .= " AND NODOKUMEN = :nodokumen";
                $bindings['nodokumen'] = $nodokumen;
            }

            /**
             * LOGIKA FILTER TANGGAL:
             * - Jika tanggal & tanggal_end diisi -> rentang tanggal (BETWEEN)
             * - Jika hanya tanggal diisi        -> = tanggal
             * - Jika hanya tanggal_end diisi    -> = tanggal_end
             * - Jika dua-duanya kosong          -> tidak ada filter tanggal
             */
            if ($tanggal && $tanggalEnd) {
                // Optional: jaga-jaga kalau user kebalik isi (tanggal > tanggalEnd)
                $startDate = $tanggal;
                $endDate   = $tanggalEnd;

                if ($startDate > $endDate) {
                    $startDate = $tanggalEnd;
                    $endDate   = $tanggal;
                }

                $query .= " and TRUNC(HARVESTING.TANGGAL) between TO_DATE(:tanggal, 'YYYY-MM-DD') and TO_DATE(:tanggal_end, 'YYYY-MM-DD') ";
                $bindings['tanggal'] = $startDate;
                $bindings['tanggal_end']   = $endDate;
            } elseif ($tanggal) {
                $query .= " and TRUNC(HARVESTING.TANGGAL) = TO_DATE(:tanggal, 'YYYY-MM-DD') ";
                $bindings['tanggal'] = $tanggal;
            } elseif ($tanggalEnd) {
                $query .= " and TRUNC(HARVESTING.TANGGAL) = TO_DATE(:tanggal_end, 'YYYY-MM-DD') ";
                $bindings['tanggal_end'] = $tanggalEnd;
            }

            if ($kode_karyawan) {
                $query .= " AND HARVESTING.KODE_KARYAWAN = :kode_karyawan";
                $bindings['kode_karyawan'] = $kode_karyawan;
            }

            if ($tph) {
                $query .= " AND HARVESTING.TPH = :tph";
                $bindings['tph'] = $tph;
            }

            if ($afdeling) {
                $query .= " AND HARVESTING.AFDELING = :afdeling";
                $bindings['afdeling'] = $afdeling;
            }

            if ($fcba) {
                $query .= " AND HARVESTING.FCBA = :fcba";
                $bindings['fcba'] = $fcba;
            }

            if ($status_harvesting) {
                $query .= " AND HARVESTING.STATUS_HARVESTING = :status_harvesting";
                $bindings['status_harvesting'] = $status_harvesting;
            }

            // Tambahkan bagian akhir query
            $query .= "
                ORDER BY 
                    HARVESTING.FCBA,
                    HARVESTING.TANGGAL DESC,
                    HARVESTING.AFDELING,
                    HARVESTING.FIELDCODE,
                    HARVESTING.KODE_KARYAWAN
            ";

            // Jalankan query
            $datas = DB::connection('oracle')->select($query, $bindings);

            if (empty($datas)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data tidak ditemukan.',
                    'data' => []
                ], 404);
            }

            return new AllResource(true, 'List Data Panen', $datas);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyimpan data Panen ke dalam database SIPS Mobile.
     */
    public function store(Request $request)
    {
        // 1. BERSIHKAN FIELD ANGKA DULU
        $numericFields = [
            'output',
            'mentah',
            'overripe',
            'busuk',
            'busuk2',
            'buahkecil',
            'parteno',
            'brondol',
            'tangkaipanjang',
        ];

        foreach ($numericFields as $field) {
            $value = $request->input($field);

            // Kalau:
            // - tidak ada
            // - null
            // - string kosong
            // - string "null"
            // - ADA isinya tapi BUKAN angka (misal: " ", "abc")
            // → paksa jadi 0
            if ($value === null || $value === '' || $value === 'null' || !is_numeric($value)) {
                $request->merge([$field => 0]);
            }
        }

        // Validasi inputan
        $request->validate([
            'nodokumen' => 'required|string',
            'tanggal' => 'required|date_format:Y-m-d',
            'kode_karyawan_mandor1' => 'nullable|string|exists:employee,fccode',
            'kode_karyawan_mandor_panen' => 'nullable|string|exists:employee,fccode',
            'kode_karyawan_kerani' => 'nullable|string|exists:employee,fccode',
            'kode_karyawan' => 'required|string|exists:employee,fccode',
            'noancak' => 'required|string|exists:tph,ancakno',
            'tph' => 'required|string|exists:tph,notph',
            'fieldcode' => 'required|string|exists:tph,fieldcode',
            'afdeling' => 'required|string|exists:tph,afdeling',
            'fcba' => 'required|string|exists:tph,fcba',
            'output' => 'required|integer|min:0',
            'mentah' => 'nullable|integer|min:0',
            'overripe' => 'nullable|integer|min:0',
            'busuk' => 'nullable|integer|min:0',
            'busuk2' => 'nullable|integer|min:0',
            'buahkecil' => 'nullable|integer|min:0',
            'parteno' => 'nullable|integer|min:0',
            'brondol' => 'nullable|integer|min:0',
            'tangkaipanjang' => 'nullable|integer|min:0',
            'alasbrondol' => 'nullable|string',
            'status_assistensi' => 'nullable',
            'images' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'id_device' => 'nullable',
            'card_id' => 'nullable',
            'created_by' => 'nullable',
        ]);

        try {
            // Inisialisasi variabel path image (default null jika tidak ada file)
            $imagePath = null;

            // Jika ada file image yang diunggah
            if ($request->hasFile('images')) {
                $image = $request->file('images');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('file/harvesting_images'), $imageName); // Simpan di public/harvesting_images
                $imagePath = 'file/harvesting_images/' . $imageName; // Path yang disimpan di database
            }

            $imagePath = $imagePath ? asset($imagePath) : null;

            // Simpan data Harvesting ke dalam database
            $datas = Harvesting::create([
                'NODOKUMEN' => $request->nodokumen,
                'TANGGAL' => $request->tanggal,
                'KODE_KARYAWAN_MANDOR1' => $request->kode_karyawan_mandor1,
                'KODE_KARYAWAN_MANDOR_PANEN' => $request->kode_karyawan_mandor_panen,
                'KODE_KARYAWAN_KERANI' => $request->kode_karyawan_kerani,
                'KODE_KARYAWAN' => $request->kode_karyawan,
                'NOANCAK' => $request->noancak,
                'TPH' => $request->tph,
                'FIELDCODE' => $request->fieldcode,
                'AFDELING' => $request->afdeling,
                'FCBA' => $request->fcba,
                'OUTPUT' => $request->output,
                'MENTAH' => $request->mentah,
                'OVERRIPE' => $request->overripe,
                'BUSUK' => $request->busuk,
                'BUSUK2' => $request->busuk2,
                'BUAHKECIL' => $request->buahkecil,
                'PARTENO' => $request->parteno,
                'BRONDOL' => $request->brondol,
                'TANGKAIPANJANG' => $request->tangkaipanjang,
                'ALASBRONDOL' => $request->alasbrondol ?? "N",
                'STATUS_ASSISTENSI' => $request->status_assistensi,
                'STATUS_HARVESTING' => 'Planned',
                'IMAGES' => $imagePath, // Simpan path image jika ada
                'ID_DEVICE' => $request->id_device,
                'CARD_ID' => $request->card_id,
                'CREATED_BY' => Auth::user()->username,
            ]);

            // Kembalikan respons dengan data yang baru saja disimpan
            return new AllResource(true, 'Data Panen berhasil ditambahkan.', $datas);
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
     * Menampilkan data Panen berdasarkan id Panen dari SIPS Mobile.
     *
     * @urlParam id integer required ID Panen.
     */
    public function show(string $id)
    {
        try {
            $query = "
                SELECT 
                    HARVESTING.ID,
                    HARVESTING.NODOKUMEN,
                    HARVESTING.TANGGAL,
                    HARVESTING.KODE_KARYAWAN_MANDOR1,
                    MANDOR1.FCNAME AS NAMA_KARYAWAN_MANDOR1,
                    HARVESTING.KODE_KARYAWAN_MANDOR_PANEN,
                    MANDOR_PANEN.FCNAME AS NAMA_KARYAWAN_MANDOR_PANEN,
                    HARVESTING.KODE_KARYAWAN_KERANI,
                    KERANI.FCNAME AS NAMA_KARYAWAN_KERANI,
                    HARVESTING.KODE_KARYAWAN,
                    KARYAWAN.FCNAME AS NAMA_KARYAWAN,
                    KARYAWAN.SECTIONNAME AS AFDELING,
                    KARYAWAN.GANGCODE,
                    HARVESTING.NOANCAK,
                    HARVESTING.TPH,
                    HARVESTING.AFDELING,
                    HARVESTING.FCBA,
                    HARVESTING.FIELDCODE,
                    HARVESTING.OUTPUT,
                    HARVESTING.MENTAH,
                    HARVESTING.OVERRIPE,
                    HARVESTING.BUSUK,
                    HARVESTING.BUSUK2,
                    HARVESTING.BUAHKECIL,
                    HARVESTING.BRONDOL,
                    HARVESTING.ALASBRONDOL,
                    HARVESTING.TANGKAIPANJANG,
                    HARVESTING.STATUS_ASSISTENSI,
                    HARVESTING.STATUS_HARVESTING,
                    HARVESTING.IMAGES,
                    HARVESTING.ID_DEVICE,
                    HARVESTING.CARD_ID,
                    HARVESTING.CREATED_BY,
                    HARVESTING.CREATED_AT,
                    HARVESTING.UPDATED_BY,
                    HARVESTING.UPDATED_AT
                FROM
                    SIPSMOBILE.HARVESTING
                INNER JOIN
                    SIPSMOBILE.TPH
                ON
                    HARVESTING.TPH = TPH.NOTPH 
                    AND HARVESTING.FIELDCODE = TPH.FIELDCODE
                    AND HARVESTING.AFDELING = TPH.AFDELING
                    AND HARVESTING.FCBA = TPH.FCBA
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE MANDOR1
                ON
                    HARVESTING.KODE_KARYAWAN_MANDOR1 = MANDOR1.FCCODE
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE MANDOR_PANEN
                ON
                    HARVESTING.KODE_KARYAWAN_MANDOR_PANEN = MANDOR_PANEN.FCCODE
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE KERANI
                ON
                    HARVESTING.KODE_KARYAWAN_KERANI = KERANI.FCCODE
                INNER JOIN
                    SIPSMOBILE.EMPLOYEE KARYAWAN
                ON 
                    HARVESTING.KODE_KARYAWAN = KARYAWAN.FCCODE
                WHERE 
                    HARVESTING.ID = :id
            ";

            // Jalankan query
            $data = DB::connection('oracle')->selectOne($query, ['id' => $id]);

            if (empty($data)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data tidak ditemukan.',
                    'data' => []
                ], 404);
            }

            // Jika data ditemukan, kembalikan data
            return new AllResource(true, 'Detail Data Panen', $data);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengubah data Panen berdasarkan id Panen.
     *
     * @urlParam id integer required ID Panen.
     */
    public function update(Request $request, string $id)
    {
        // 1. BERSIHKAN FIELD ANGKA DULU
        $numericFields = [
            'output',
            'mentah',
            'overripe',
            'busuk',
            'busuk2',
            'buahkecil',
            'parteno',
            'brondol',
            'tangkaipanjang',
        ];

        foreach ($numericFields as $field) {
            $value = $request->input($field);
            // Kalau:
            // - tidak ada
            // - null
            // - string kosong
            // - string "null"
            // - ADA isinya tapi BUKAN angka (misal: " ", "abc")
            // → paksa jadi 0
            if ($value === null || $value === '' || $value === 'null' || !is_numeric($value)) {
                $request->merge([$field => 0]);
            }
        }

        // Validasi input
        $validated = $request->validate([
            'kode_karyawan_mandor1' => 'nullable|string|exists:employee,fccode',
            'kode_karyawan_mandor_panen' => 'nullable|string|exists:employee,fccode',
            'kode_karyawan_kerani' => 'nullable|string|exists:employee,fccode',
            'kode_karyawan' => 'required|string|exists:employee,fccode',
            'noancak' => 'required|string|exists:tph,ancakno',
            'tph' => 'required|string|exists:tph,notph',
            'fieldcode' => 'required|string|exists:tph,fieldcode',
            'afdeling' => 'required|string|exists:tph,afdeling',
            'fcba' => 'required|string|exists:tph,fcba',
            'output' => 'required|numeric|min:0',
            'mentah' => 'nullable|numeric|min:0',
            'overripe' => 'nullable|numeric|min:0',
            'busuk' => 'nullable|numeric|min:0',
            'busuk2' => 'nullable|numeric|min:0',
            'buahkecil' => 'nullable|numeric|min:0',
            'parteno' => 'nullable|numeric|min:0',
            'brondol' => 'nullable|numeric|min:0',
            'tangkaipanjang' => 'nullable|numeric|min:0',
            'alasbrondol' => 'nullable|string',
            'status_assistensi' => 'nullable',
            'images' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ]);

        try {
            // Cari data berdasarkan ID
            $datas = Harvesting::findOrFail($id);

            // Jika data tidak ditemukan
            if (!$datas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Panen tidak ditemukan'
                ], 404);
            }

            $imagePath = $datas->images; // Default gunakan gambar lama

            // Jika ada file image yang diunggah
            if (!empty($request->hasFile('images'))) {
                $image = $request->file('images');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('file/harvesting_images'), $imageName); // Simpan di public/harvesting_images
                $imagePath = 'file/harvesting_images/' . $imageName; // Path yang disimpan di database
                $imagePath = $imagePath ? asset($imagePath) : null;
            }

            // Menyusun data untuk update
            $updateData = [
                $validated['kode_karyawan_mandor1'] ?? null,        // 1
                $validated['kode_karyawan_mandor_panen'] ?? null,   // 2
                $validated['kode_karyawan_kerani'] ?? null,         // 3
                $validated['kode_karyawan'],                        // 4
                $validated['noancak'],                              // 5
                $validated['tph'],                                  // 6
                $validated['fieldcode'],                            // 7
                $validated['afdeling'],                             // 8
                $validated['fcba'],                                 // 9
                $validated['output'],                               // 10
                $validated['mentah'] ?? null,                       // 11
                $validated['overripe'] ?? null,                     // 12
                $validated['busuk'] ?? null,                        // 13
                $validated['busuk2'] ?? null,                       // 14
                $validated['buahkecil'] ?? null,                    // 15
                $validated['parteno'] ?? null,                      // 16
                $validated['brondol'] ?? null,                      // 17
                $validated['tangkaipanjang'] ?? null,               // 19
                $validated['alasbrondol'] ?? "N",                   // 18
                $validated['status_assistensi'] ?? null,            // 20
                $imagePath,                                         // 21
                Auth::user()->username,                             // 22
                $id,                                                // (ID untuk WHERE)
            ];

            // Update menggunakan query manual
            DB::update(
                "UPDATE \"SIPSMOBILE\".\"HARVESTING\" 
                SET 
                    \"KODE_KARYAWAN_MANDOR1\" = ?, 
                    \"KODE_KARYAWAN_MANDOR_PANEN\" = ?, 
                    \"KODE_KARYAWAN_KERANI\" = ?, 
                    \"KODE_KARYAWAN\" = ?, 
                    \"NOANCAK\" = ?, 
                    \"TPH\" = ?, 
                    \"FIELDCODE\" = ?, 
                    \"AFDELING\" = ?, 
                    \"FCBA\" = ?, 
                    \"OUTPUT\" = ?, 
                    \"MENTAH\" = ?, 
                    \"OVERRIPE\" = ?, 
                    \"BUSUK\" = ?, 
                    \"BUSUK2\" = ?, 
                    \"BUAHKECIL\" = ?, 
                    \"PARTENO\" = ?, 
                    \"BRONDOL\" = ?, 
                    \"TANGKAIPANJANG\" = ?, 
                    \"ALASBRONDOL\" = ?, 
                    \"STATUS_ASSISTENSI\" = ?, 
                    \"IMAGES\" = ?, 
                    \"UPDATED_BY\" = ?, 
                    \"UPDATED_AT\" = SYSDATE
                WHERE \"ID\" = ?",
                $updateData
            );

            $datas = Harvesting::findOrFail($id);

            // Berhasil diupdate
            return response()->json([
                'success' => true,
                'message' => 'Data Panen berhasil diperbarui.',
                'data' => $datas,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data Panen tidak ditemukan.',
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
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
     * Approved atau Reject status_harvesting (STATUS_HARVESTING) berdasarkan id Harvesting.
     *
     * @urlParam id integer required ID Harvesting.
     */
    public function updateStatus(Request $request, string $id)
    {
        // Validasi input status yang diizinkan
        $validated = $request->validate([
            'status_harvesting' => 'required|string|in:Planned,Reject,Approved',
        ]);

        try {
            // Cari data berdasarkan ID
            $datas = Harvesting::findOrFail($id);

            // Update status menggunakan query manual (konsisten dengan update lain)
            DB::update(
                "UPDATE \"SIPSMOBILE\".\"HARVESTING\" \n                SET \"STATUS_HARVESTING\" = ?, \"UPDATED_BY\" = ?, \"UPDATED_AT\" = SYSDATE\n                WHERE \"ID\" = ?",
                [$validated['status_harvesting'], Auth::user()->username, $id]
            );

            // Ambil kembali data yang sudah diupdate
            $datas = Harvesting::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Status Harvesting berhasil diperbarui.',
                'data' => $datas,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data Harvesting tidak ditemukan.',
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupdate status harvesting.',
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
     * Menghapus data Panen berdasarkan id Panen.
     *
     * @urlParam id integer required ID Panen.
     */
    public function destroy(string $id)
    {
        try {
            $datas = Harvesting::findOrFail($id);
            $datas->delete();
            return new AllResource(true, 'Data Panen berhasil dihapus.', $datas);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data Panen tidak ditemukan.',
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
