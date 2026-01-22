<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengangkutan;
use Illuminate\Http\Request;
use App\Http\Resources\AllResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

/**
 * @group Apps
 *
 * @subgroup Pengangkutan
 * @subgroupDescription Sub Group untuk Pengangkutan
 *
 */
class PengangkutanController extends Controller
{
    /**
     * Memanggil data Pengangkutan dari SIPS Mobile.
     *
     * API ini digunakan untuk memanggil data Pengangkutan secara keseluruhan. 
     * Namun, jika ingin melakukan filter pada data yang dipanggil, 
     * gunakan parameter pada URL berdasarkan _**Query Parameter**_.
     * Data diurutkan berdasarkan Tanggal terbaru, Afdeling, dan Kode Karyawan.
     *
     * @queryParam nopengangkutan string Optional. Filter Pengangkutan berdasarkan No Pengangkutan. Example: DRC2010101101
     * @queryParam nospb string Optional. Filter Pengangkutan berdasarkan No SPB. Example: SPB2024001259
     * @queryParam nodokumen string Optional. Filter Pengangkutan berdasarkan No Dokumen. Example: SKJ-HOF/MTE/25/11/0001
     * @queryParam tanggal string Optional. Filter Pengangkutan berdasarkan tanggal. Harus dalam format YYYY-MM-DD. Example: 2025-11-01
     * @queryParam tanggal_end string Optional. Filter Pengangkutan berdasarkan rentang tanggal. Harus dalam format YYYY-MM-DD. Example: 2025-11-20
     * @queryParam kode_karyawan_kerani string Optional. Filter Pengangkutan berdasarkan Kode Karyawan Kerani Transport. Example: 06-030922-240201-0531
     * @queryParam kode_karyawan_driver string Optional. Filter Pengangkutan berdasarkan Kode Karyawan Kerani Driver. Example: 06-830717-190901-0112
     * @queryParam tkbm1 string Optional. Filter Pengangkutan berdasarkan kode karyawan tenaga kerja bongkar muat Pertama. Example: 06-830717-190901-0113
     * @queryParam tkbm2 string Optional. Filter Pengangkutan berdasarkan kode karyawan tenaga kerja bongkar muat Kedua. Example: 06-830717-190901-0114
     * @queryParam tkbm3 string Optional. Filter Pengangkutan berdasarkan kode karyawan tenaga kerja bongkar muat Ketiga. Example: 06-830717-190901-0115
     * @queryParam tkbm4 string Optional. Filter Pengangkutan berdasarkan kode karyawan tenaga kerja bongkar muat Keempat. Example: 06-830717-190901-0116
     * @queryParam tkbm5 string Optional. Filter Pengangkutan berdasarkan kode karyawan tenaga kerja bongkar muat Kelima. Example: 06-830717-190901-0117
     * @queryParam type_pengangkutan integer Optional. Filter Pengangkutan berdasarkan type pengangkutan salah satu dari 1 (LANGSIR) atau 2 (DIRECT). Example: 1
     * @queryParam kode_kendaraan string Optional. Filter Pengangkutan berdasarkan Kode Kendaraan. Example: DT70
     * @queryParam fcba string Optional. Filter Pengangkutan berdasarkan FCBA. Example: MTE
     * @queryParam pabrik_tujuan string Optional. Filter Pengangkutan berdasarkan tujuan tergantung type_pengangkutan jika Direct Maka akan diarahkan ke Business Unit dengan type M jika Langsir maka akan diarahkan ke TPH dengan tipe langsir kodenya (4). Example: DOM 
     * @queryParam afdeling string Optional. Filter Pengangkutan berdasarkan afdeling. Example: AFD-01
     * @queryParam tph string Optional. Filter Pengangkutan berdasarkan TPH. Example: 1
     * @queryParam fieldcode string Optional. Filter Pengangkutan berdasarkan FIELDCODE. Example: A02
     * @queryParam status_pengangkutan string Optional. Filter Pengangkutan berdasarkan Status Pengangkutan salah satu dari Planned, AuthorizedOnProgress, Approved. Example: Planned
     * @queryParam flag string Optional. Filter Pengangkutan berdasarkan Status Upload Pengangkutan yang sudah diangkut akan berstatus Y jika belum maka N. Example: Y
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "List Data Pengangkutan",
     *  "data": [
     *      {
     *          "id": "5",
     *          "nopengangkutan": "DRC2010101101",
     *          "nospb": "SPB2024001259",
     *          "nodokumen": "002",
     *          "tanggal": "2024-12-31 00:00:00",
     *          "kode_karyawan_kerani": "06-030922-240201-0531",
     *          "nama_karyawan_kerani": "LEONARDUS DIFAN ALFANTO",
     *          "kode_karyawan_driver": "06-830717-190901-0112",
     *          "nama_karyawan_driver": "NURSIDA",
     *          "tkbm1": "06-830717-190901-0113",
     *          "nama_tkbm1": "ANON",
     *          "tkbm2": "06-830717-190901-0114",
     *          "nama_tkbm2": "ONAN",
     *          "tkbm3": "06-830717-190901-0115",
     *          "nama_tkbm3": "JASU",
     *          "tkbm4": "06-830717-190901-0116",
     *          "nama_tkbm4": "BUJA",
     *          "tkbm5": "06-830717-190901-0117",
     *          "nama_tkbm5": "TOYO",
     *          "type_pengangkutan": 1,
     *          "kode_kendaraan": "DT70",
     *          "nama_kendaraan": "Dump Truck 70",
     *          "fcba": "MTE",
     *          "pabrik_tujuan": "DOM",
     *          "afdeling": "AFD-01",
     *          "tph": "1",
     *          "fieldcode": "A01A",
     *          "totaljanjang": "190",
     *          "output": "155",
     *          "janjangnormal": "160",
     *          "brondolan": "2",
     *          "status_pengangkutan": "Planned",
     *          "card_id": "NFC 1234567890",
     *          "flag": "Y",
     *          "images": "http://172.16.5.199:82/file/pengangkutan_images/1735532659_Screenshot 2024-12-27 104602.png"
     *      }
     *  ]
     * }
     */
    public function index(Request $request)
    {
        try {
            // Ambil parameter dari query string
            $nopengangkutan = $request->query('nopengangkutan');
            $nospb = $request->query('nospb');
            $nodokumen = $request->query('nodokumen');
            $tanggal = $request->query('tanggal');
            $tanggalEnd     = $request->query('tanggal_end');
            $kode_karyawan_kerani = $request->query('kode_karyawan_kerani');
            $kode_karyawan_driver = $request->query('kode_karyawan_driver');
            $tkbm1 = $request->query('tkbm1');
            $tkbm2 = $request->query('tkbm2');
            $tkbm3 = $request->query('tkbm3');
            $tkbm4 = $request->query('tkbm4');
            $tkbm5 = $request->query('tkbm5');
            $type_pengangkutan = $request->query('type_pengangkutan');
            $kode_kendaraan = $request->query('kode_kendaraan');
            $afdeling = $request->query('afdeling');
            $fcba = $request->query('fcba');
            $pabrik_tujuan = $request->query('pabrik_tujuan');
            $tph = $request->query('tph');
            $fieldcode = $request->query('fieldcode');
            $status_pengangkutan = $request->query('status_pengangkutan');
            $flag = $request->query('flag');

            $query = "
                SELECT 
                    DISTINCT 
                    PENGANGKUTAN.ID,
                    PENGANGKUTAN.NOPENGANGKUTAN,
                    PENGANGKUTAN.NOSPB,
                    PENGANGKUTAN.NODOKUMEN,
                    PENGANGKUTAN.TANGGAL,
                    PENGANGKUTAN.KODE_KARYAWAN_KERANI,
                    KERANI.FCNAME AS NAMA_KARYAWAN_KERANI,
                    PENGANGKUTAN.KODE_KARYAWAN_DRIVER,
                    DRIVER.FCNAME AS NAMA_KARYAWAN_DRIVER,
                    PENGANGKUTAN.TKBM1,
                    TKBM1.FCNAME AS NAMA_TKBM1,
                    PENGANGKUTAN.TKBM2,
                    TKBM2.FCNAME AS NAMA_TKBM2,
                    PENGANGKUTAN.TKBM3,
                    TKBM3.FCNAME AS NAMA_TKBM3,
                    PENGANGKUTAN.TKBM4,
                    TKBM4.FCNAME AS NAMA_TKBM4,
                    PENGANGKUTAN.TKBM5,
                    TKBM5.FCNAME AS NAMA_TKBM5,
                    PENGANGKUTAN.KODE_KENDARAAN,
                    PENGANGKUTAN.TYPE_PENGANGKUTAN,
                    KENDARAAN.FCNAME NAMA_KENDARAAN,
                    PENGANGKUTAN.FCBA,
                    PENGANGKUTAN.PABRIK_TUJUAN,
                    PENGANGKUTAN.AFDELING,
                    PENGANGKUTAN.TPH,
                    PENGANGKUTAN.FIELDCODE,
                    PENGANGKUTAN.TOTALJANJANG,
                    PENGANGKUTAN.OUTPUT,
                    PENGANGKUTAN.JANJANGNORMAL,
                    PENGANGKUTAN.BRONDOLAN,
                    PENGANGKUTAN.STATUS_PENGANGKUTAN,
                    PENGANGKUTAN.IMAGES,
                    PENGANGKUTAN.CARD_ID,
                    PENGANGKUTAN.FLAG
                FROM
                    SIPSMOBILE.PENGANGKUTAN
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE KERANI
                ON
                    PENGANGKUTAN.KODE_KARYAWAN_KERANI = KERANI.FCCODE 
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE DRIVER
                ON
                    PENGANGKUTAN.KODE_KARYAWAN_DRIVER = DRIVER.FCCODE 
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE TKBM1
                ON
                    PENGANGKUTAN.TKBM1 = TKBM1.FCCODE
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE TKBM2
                ON
                    PENGANGKUTAN.TKBM2 = TKBM2.FCCODE
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE TKBM3
                ON
                    PENGANGKUTAN.TKBM3 = TKBM3.FCCODE
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE TKBM4
                ON
                    PENGANGKUTAN.TKBM4 = TKBM4.FCCODE
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE TKBM5
                ON
                    PENGANGKUTAN.TKBM5 = TKBM5.FCCODE
                LEFT JOIN
                    IPLASPROD.VEHICLE KENDARAAN
                ON 
                    PENGANGKUTAN.KODE_KENDARAAN = KENDARAAN.FCCODE 
                WHERE 
                    PENGANGKUTAN.TANGGAL IS NOT NULL
            ";

            $bindings = [];

            // Filter berdasarkan parameter
            if ($nopengangkutan) {
                $query .= " AND NOPENGANGKUTAN = :nopengangkutan";
                $bindings['nopengangkutan'] = $nopengangkutan;
            }
            if ($nospb) {
                $query .= " AND NOSPB = :nospb";
                $bindings['nospb'] = $nospb;
            }

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

                $query .= " and TRUNC(PENGANGKUTAN.TANGGAL) between TO_DATE(:tanggal, 'YYYY-MM-DD') and TO_DATE(:tanggal_end, 'YYYY-MM-DD') ";
                $bindings['tanggal'] = $startDate;
                $bindings['tanggal_end']   = $endDate;
            } elseif ($tanggal) {
                $query .= " and TRUNC(PENGANGKUTAN.TANGGAL) = TO_DATE(:tanggal, 'YYYY-MM-DD') ";
                $bindings['tanggal'] = $tanggal;
            } elseif ($tanggalEnd) {
                $query .= " and TRUNC(PENGANGKUTAN.TANGGAL) = TO_DATE(:tanggal_end, 'YYYY-MM-DD') ";
                $bindings['tanggal_end'] = $tanggalEnd;
            }

            if ($kode_kendaraan) {
                $query .= " AND KODE_KENDARAAN = :kode_kendaraan";
                $bindings['kode_kendaraan'] = $kode_kendaraan;
            }

            if ($kode_karyawan_kerani) {
                $query .= " AND KODE_KARYAWAN_KERANI = :kode_karyawan_kerani";
                $bindings['kode_karyawan_kerani'] = $kode_karyawan_kerani;
            }

            if ($kode_karyawan_driver) {
                $query .= " AND KODE_KARYAWAN_DRIVER = :kode_karyawan_driver";
                $bindings['kode_karyawan_driver'] = $kode_karyawan_driver;
            }

            if ($tkbm1) {
                $query .= " AND TKBM1 = :tkbm1";
                $bindings['tkbm1'] = $tkbm1;
            }

            if ($tkbm2) {
                $query .= " AND TKBM2 = :tkbm2";
                $bindings['tkbm2'] = $tkbm2;
            }

            if ($tkbm3) {
                $query .= " AND TKBM3 = :tkbm3";
                $bindings['tkbm3'] = $tkbm3;
            }

            if ($tkbm4) {
                $query .= " AND TKBM4 = :tkbm4";
                $bindings['tkbm4'] = $tkbm4;
            }

            if ($tkbm5) {
                $query .= " AND TKBM5 = :tkbm5";
                $bindings['tkbm5'] = $tkbm5;
            }

            if ($type_pengangkutan) {
                $query .= " AND TYPE_PENGANGKUTAN = :type_pengangkutan";
                $bindings['type_pengangkutan'] = $type_pengangkutan;
            }

            if ($fcba) {
                $query .= " AND PENGANGKUTAN.FCBA = :fcba";
                $bindings['fcba'] = $fcba;
            }

            if ($pabrik_tujuan) {
                $query .= " AND PENGANGKUTAN.PABRIK_TUJUAN = :pabrik_tujuan";
                $bindings['pabrik_tujuan'] = $pabrik_tujuan;
            }

            if ($afdeling) {
                $query .= " AND PENGANGKUTAN.AFDELING = :afdeling";
                $bindings['afdeling'] = $afdeling;
            }

            if ($tph) {
                $query .= " AND PENGANGKUTAN.TPH = :tph";
                $bindings['tph'] = $tph;
            }

            if ($fieldcode) {
                $query .= " AND PENGANGKUTAN.FIELDCODE = :fieldcode";
                $bindings['fieldcode'] = $fieldcode;
            }

            if ($status_pengangkutan) {
                $query .= " AND PENGANGKUTAN.STATUS_PENGANGKUTAN = :status_pengangkutan";
                $bindings['status_pengangkutan'] = $status_pengangkutan;
            }

            if ($flag) {
                $query .= " AND PENGANGKUTAN.FLAG = :flag";
                $bindings['flag'] = $flag;
            }

            // Tambahkan bagian akhir query
            $query .= "
                ORDER BY 
                    PENGANGKUTAN.TANGGAL DESC,
                    PENGANGKUTAN.NOPENGANGKUTAN DESC,
                    PENGANGKUTAN.NOSPB DESC,
                    PENGANGKUTAN.NODOKUMEN DESC
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

            return new AllResource(true, 'List Data Pengangkutan', $datas);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyimpan data Pengangkutan ke dalam database SIPS Mobile.
     */
    public function store(Request $request)
    {
        // Validasi inputan
        $request->validate([
            'nopengangkutan' => 'required|string',
            'nospb' => 'required|string',
            'nodokumen' => 'required|string',
            'tanggal' => 'required|date_format:Y-m-d',
            'kode_karyawan_kerani' => 'required|string|exists:employee,fccode',
            'kode_karyawan_driver' => 'required|string|exists:employee,fccode',
            'tkbm1' => 'required|string|exists:employee,fccode',
            'tkbm2' => 'nullable|string|exists:employee,fccode',
            'tkbm3' => 'nullable|string|exists:employee,fccode',
            'tkbm4' => 'nullable|string|exists:employee,fccode',
            'tkbm5' => 'nullable|string|exists:employee,fccode',
            'type_pengangkutan' => 'required|integer',
            'kode_kendaraan' => 'required|string',
            'tph' => 'nullable|required_if:type_pengangkutan,1|string|exists:tph,notph',
            'fieldcode' => 'nullable|required_if:type_pengangkutan,1|string|exists:tph,fieldcode',
            'afdeling' => 'nullable|required_if:type_pengangkutan,1|string|exists:sips_production.field,division',
            'fcba' => 'nullable|required_if:type_pengangkutan,1|string|exists:sips_production.field,fcba',
            'pabrik_tujuan' => 'required|string',
            'totaljanjang' => 'required|numeric',
            'output' => 'required|numeric',
            'janjangnormal' => 'required|numeric',
            'brondolan' => 'nullable|numeric',
            'images' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
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
                $image->move(public_path('file/pengangkutan_images'), $imageName); // Simpan di public/pengangkutan_images
                $imagePath = 'file/pengangkutan_images/' . $imageName; // Path yang disimpan di database
            }

            $imagePath = $imagePath ? asset($imagePath) : null;

            // Jika afdeling dan fcba kosong, ambil dari pabrik_tujuan
            if (empty($afdeling) && empty($fcba) && !empty($pabrik_tujuan) && !empty($nopengangkutan)) {
                $afdelingFcbaData = $this->getAfdelingFcbaFromPabrikTujuan($nopengangkutan);
                if ($afdelingFcbaData) {
                    $afdeling = $afdelingFcbaData->afdeling;
                    $fcba = $afdelingFcbaData->fcba;
                }
            }

            // Simpan data Pengangkutan ke dalam database
            $datas = Pengangkutan::create([
                'NOPENGANGKUTAN' => $request->nopengangkutan,
                'NOSPB' => $request->nospb,
                'NODOKUMEN' => $request->nodokumen,
                'TANGGAL' => $request->tanggal,
                'KODE_KARYAWAN_KERANI' => $request->kode_karyawan_kerani,
                'KODE_KARYAWAN_DRIVER' => $request->kode_karyawan_driver,
                'TKBM1' => $request->tkbm1,
                'TKBM2' => $request->tkbm2,
                'TKBM3' => $request->tkbm3,
                'TKBM4' => $request->tkbm4,
                'TKBM5' => $request->tkbm5,
                'TYPE_PENGANGKUTAN' => $request->type_pengangkutan,
                'KODE_KENDARAAN' => $request->kode_kendaraan,
                'TPH' => $request->tph,
                'FIELDCODE' => $request->fieldcode,
                'AFDELING' => $request->afdeling,
                'FCBA' => $request->fcba,
                'PABRIK_TUJUAN' => $request->pabrik_tujuan,
                'TOTALJANJANG' => $request->totaljanjang,
                'OUTPUT' => $request->output,
                'JANJANGNORMAL' => $request->janjangnormal,
                'BRONDOLAN' => $request->brondolan,
                'STATUS_PENGANGKUTAN' => 'Planned',
                'IMAGES' => $imagePath, // Simpan path image jika ada
                'CARD_ID' => $request->card_id,
                'FLAG' => 'N',
                'CREATED_BY' => Auth::user()->username,
            ]);

            // Kembalikan respons dengan data yang baru saja disimpan
            return new AllResource(true, 'Data Pengangkutan berhasil ditambahkan.', $datas);
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
     * Menampilkan data Pengangkutan berdasarkan id Pengangkutan dari SIPS Mobile.
     *
     * @urlParam id integer required ID Pengangkutan.
     */
    public function show(string $id)
    {
        try {
            $query = "
                SELECT 
                    PENGANGKUTAN.NOPENGANGKUTAN,
                    PENGANGKUTAN.ID,
                    PENGANGKUTAN.NOSPB,
                    PENGANGKUTAN.NODOKUMEN,
                    PENGANGKUTAN.TANGGAL,
                    PENGANGKUTAN.KODE_KARYAWAN_KERANI,
                    KERANI.FCNAME AS NAMA_KARYAWAN_KERANI,
                    PENGANGKUTAN.KODE_KARYAWAN_DRIVER,
                    DRIVER.FCNAME AS NAMA_KARYAWAN_DRIVER,
                    PENGANGKUTAN.TKBM1,
                    TKBM1.FCNAME AS NAMA_TKBM1,
                    PENGANGKUTAN.TKBM2,
                    TKBM2.FCNAME AS NAMA_TKBM2,
                    PENGANGKUTAN.TKBM3,
                    TKBM3.FCNAME AS NAMA_TKBM3,
                    PENGANGKUTAN.TKBM4,
                    TKBM4.FCNAME AS NAMA_TKBM4,
                    PENGANGKUTAN.TKBM5,
                    TKBM5.FCNAME AS NAMA_TKBM5,
                    PENGANGKUTAN.TYPE_PENGANGKUTAN,
                    PENGANGKUTAN.KODE_KENDARAAN,
                    KENDARAAN.FCNAME NAMA_KENDARAAN,
                    PENGANGKUTAN.TPH,
                    PENGANGKUTAN.AFDELING,
                    PENGANGKUTAN.FCBA,
                    PENGANGKUTAN.PABRIK_TUJUAN,
                    PENGANGKUTAN.FIELDCODE,
                    PENGANGKUTAN.TOTALJANJANG,
                    PENGANGKUTAN.OUTPUT,
                    PENGANGKUTAN.JANJANGNORMAL,
                    PENGANGKUTAN.BRONDOLAN,
                    PENGANGKUTAN.STATUS_PENGANGKUTAN,
                    PENGANGKUTAN.IMAGES,
                    PENGANGKUTAN.FLAG,
                    PENGANGKUTAN.CARD_ID
                FROM
                    SIPSMOBILE.PENGANGKUTAN
                INNER JOIN
                    SIPSMOBILE.TPH
                ON
                    PENGANGKUTAN.TPH = TPH.NOTPH 
                    AND PENGANGKUTAN.FIELDCODE = TPH.FIELDCODE
                    AND PENGANGKUTAN.AFDELING = TPH.AFDELING
                    AND PENGANGKUTAN.FCBA = TPH.FCBA
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE KERANI
                ON
                    PENGANGKUTAN.KODE_KARYAWAN_KERANI = KERANI.FCCODE 
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE DRIVER
                ON
                    PENGANGKUTAN.KODE_KARYAWAN_DRIVER = DRIVER.FCCODE 
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE TKBM1
                ON
                    PENGANGKUTAN.TKBM1 = TKBM1.FCCODE
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE TKBM2
                ON
                    PENGANGKUTAN.TKBM2 = TKBM2.FCCODE
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE TKBM3
                ON
                    PENGANGKUTAN.TKBM3 = TKBM3.FCCODE
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE TKBM4
                ON
                    PENGANGKUTAN.TKBM4 = TKBM4.FCCODE
                LEFT JOIN
                    SIPSMOBILE.EMPLOYEE TKBM5
                ON
                    PENGANGKUTAN.TKBM5 = TKBM5.FCCODE
                LEFT JOIN
                    IPLASPROD.VEHICLE KENDARAAN
                ON 
                    PENGANGKUTAN.KODE_KENDARAAN = KENDARAAN.FCCODE 
                WHERE 
                    PENGANGKUTAN.ID = :id
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
            return new AllResource(true, 'Detail Data Pengangkutan', $data);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengubah data Pengangkutan berdasarkan id Pengangkutan.
     *
     * @urlParam id integer required ID Pengangkutan.
     */
    public function update(Request $request, string $id)
    {
        // Validasi input
        $validated = $request->validate([
            'kode_karyawan_kerani' => 'required|string|exists:employee,fccode',
            'kode_karyawan_driver' => 'required|string|exists:employee,fccode',
            'tkbm1' => 'required|string|exists:employee,fccode',
            'tkbm2' => 'nullable|string|exists:employee,fccode',
            'tkbm3' => 'nullable|string|exists:employee,fccode',
            'tkbm4' => 'nullable|string|exists:employee,fccode',
            'tkbm5' => 'nullable|string|exists:employee,fccode',
            'type_pengangkutan' => 'required|integer',
            'kode_kendaraan' => 'required|string',
            'tph' => 'required|string|exists:tph,notph',
            'fieldcode' => 'required|string|exists:tph,fieldcode',
            'afdeling' => 'required|string|exists:sips_production.field,division',
            'fcba' => 'required|string|exists:sips_production.field,fcba',
            'pabrik_tujuan' => 'required|string',
            'totaljanjang' => 'required|integer',
            'output' => 'required|integer',
            'janjangnormal' => 'required|integer',
            'brondolan' => 'nullable|integer',
            'images' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ]);

        try {
            // Cari data berdasarkan ID
            $datas = Pengangkutan::findOrFail($id);

            // Jika data tidak ditemukan
            if (!$datas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengangkutan tidak ditemukan'
                ], 404);
            }

            $imagePath = $datas->images; // Default gunakan gambar lama

            // Jika ada file image yang diunggah
            if (!empty($request->hasFile('images'))) {
                $image = $request->file('images');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('file/pengangkutan_images'), $imageName); // Simpan di public/pengangkutan_images
                $imagePath = 'file/pengangkutan_images/' . $imageName; // Path yang disimpan di database
                $imagePath = $imagePath ? asset($imagePath) : null;
            }

            // Menyusun data untuk update
            $updateData = [
                $validated['kode_karyawan_kerani'] ?? null,         // 1
                $validated['kode_karyawan_driver'] ?? null,         // 2
                $validated['tkbm1'] ?? null,                        // 3
                $validated['tkbm2'] ?? null,                        // 4
                $validated['tkbm3'] ?? null,                        // 5
                $validated['tkbm4'] ?? null,                        // 6
                $validated['tkbm5'] ?? null,                        // 7
                $validated['type_pengangkutan'] ?? null,            // 8
                $validated['kode_kendaraan'] ?? null,               // 9
                $validated['tph'] ?? null,                          // 10
                $validated['fieldcode'] ?? null,                    // 11
                $validated['afdeling'] ?? null,                     // 12
                $validated['fcba'] ?? null,                         // 13
                $validated['pabrik_tujuan'] ?? null,                // 14
                $validated['totaljanjang'] ?? null,                 // 15
                $validated['output'] ?? null,                       // 16
                $validated['janjangnormal'] ?? null,                // 17
                $validated['brondolan'] ?? null,                    // 18
                $imagePath,                                         // 19
                Auth::user()->username,                             // 20
                $id,                                                // (ID untuk WHERE)
            ];

            // Update menggunakan query manual
            DB::update(
                "UPDATE \"SIPSMOBILE\".\"PENGANGKUTAN\" 
                SET 
                    \"KODE_KARYAWAN_KERANI\" = ?, 
                    \"KODE_KARYAWAN_DRIVER\" = ?, 
                    \"TKBM1\" = ?, 
                    \"TKBM2\" = ?, 
                    \"TKBM3\" = ?, 
                    \"TKBM4\" = ?, 
                    \"TKBM5\" = ?, 
                    \"TYPE_PENGANGKUTAN\" = ?, 
                    \"KODE_KENDARAAN\" = ?, 
                    \"TPH\" = ?, 
                    \"FIELDCODE\" = ?, 
                    \"AFDELING\" = ?, 
                    \"FCBA\" = ?,  
                    \"PABRIK_TUJUAN\" = ?,  
                    \"TOTALJANJANG\" = ?, 
                    \"OUTPUT\" = ?, 
                    \"JANJANGNORMAL\" = ?, 
                    \"BRONDOLAN\" = ?, 
                    \"IMAGES\" = ?, 
                    \"UPDATED_BY\" = ?, 
                    \"UPDATED_AT\" = SYSDATE
                WHERE \"ID\" = ?",
                $updateData
            );

            $datas = Pengangkutan::findOrFail($id);

            // Berhasil diupdate
            return response()->json([
                'success' => true,
                'message' => 'Data Pengangkutan berhasil diperbarui.',
                'data' => $datas,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data Pengangkutan tidak ditemukan.',
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
     * Approved atau Reject status_pengangkutan (STATUS_PENGANGKUTAN) berdasarkan id Pengangkutan.
     *
     * @urlParam id integer required ID Pengangkutan.
     */
    public function updateStatus(Request $request, string $id)
    {
        // Validasi input status yang diizinkan
        $validated = $request->validate([
            'status_pengangkutan' => 'required|string|in:Planned,Reject,Approved',
        ]);

        try {
            // Cari data berdasarkan ID
            $datas = Pengangkutan::findOrFail($id);

            // Update status menggunakan query manual (konsisten dengan update lain)
            DB::update(
                "UPDATE \"SIPSMOBILE\".\"PENGANGKUTAN\" \n                SET \"STATUS_PENGANGKUTAN\" = ?, \"UPDATED_BY\" = ?, \"UPDATED_AT\" = SYSDATE\n                WHERE \"ID\" = ?",
                [$validated['status_pengangkutan'], Auth::user()->username, $id]
            );

            // Ambil kembali data yang sudah diupdate
            $datas = Pengangkutan::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Status Pengangkutan berhasil diperbarui.',
                'data' => $datas,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data Pengangkutan tidak ditemukan.',
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupdate status pengangkutan.',
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
     * Menghapus data Pengangkutan berdasarkan id Pengangkutan.
     *
     * @urlParam id integer required ID Pengangkutan.
     */
    public function destroy(string $id)
    {
        try {
            $datas = Pengangkutan::findOrFail($id);
            $datas->delete();
            return new AllResource(true, 'Data Pengangkutan berhasil dihapus.', $datas);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data Pengangkutan tidak ditemukan.',
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

    /**
     * Helper function untuk mengambil afdeling dan fcba berdasarkan nopengangkutan
     * Digunakan ketika parameter afdeling dan fcba kosong
     */
    private function getAfdelingFcbaFromPabrikTujuan(string $nopengangkutan)
    {
        try {
            $query = "
                SELECT 
                    AFDELING,
                    FCBA
                FROM
                    SIPSMOBILE.PENGANGKUTAN
                WHERE 
                    NOPENGANGKUTAN = :nopengangkutan
            ";

            $data = DB::connection('oracle')->selectOne($query, ['nopengangkutan' => $nopengangkutan]);

            return $data;
        } catch (\Exception $e) {
            return null;
        }
    }
}
