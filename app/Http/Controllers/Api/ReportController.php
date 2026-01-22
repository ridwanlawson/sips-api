<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\AllResource;
use App\Models\Field;
use App\Models\Employee;
use App\Models\Tph;
use App\Models\Vehicle;
use App\Models\Attendance;
use App\Models\Harvesting;
use App\Models\Pengangkutan;


/**
 * @group Report
 * 
 * 
 */
class ReportController extends Controller
{
    /**
     * Memanggil data hasil panen.
     *
     * API ini digunakan untuk memperlihatkan hasil panen pada report di Android SIPS Mobile
     * Namun, jika ingin melakukan filter pada data yang dipanggil, 
     * gunakan parameter pada URL berdasarkan _**Query Parameter**_.
     * Data diurutkan berdasarkan No dokumen, tanggal, status, bisnis unit (fcba), afdeling, tph, blok
     *
     * @queryParam nodokumen string Optional. Filter Hasil Panen berdasarkan No Dokumen. Example: SKJ-PNN/MTE/07/25/001
     * @queryParam tanggaldari string Optional. Filter Hasil Panen berdasarkan rentang tanggal, parameter ini diisi tanggal dari. Harus dalam format YYYY-MM-DD. Example: 2024-12-19
     * @queryParam tanggalsampai string Optional. Filter Hasil Panen berdasarkan rentang tanggal, parameter ini diisi tanggal sampai. Harus dalam format YYYY-MM-DD. Example: 2024-12-20
     * @queryParam tph string Optional. Filter Hasil Panen berdasarkan Kode TPH. Example: 1A02
     * @queryParam blok string Optional. Filter Hasil Panen berdasarkan Blok. Example: A02
     * @queryParam afdeling string Optional. Filter Hasil Panen berdasarkan afdeling. Example: AFD-01
     * @queryParam fcba string Optional. Filter Hasil Panen berdasarkan Bisnis Unit (FCBA). Example: MTE
     * @queryParam status string Optional. Filter Hasil Panen berdasarkan Status Hasil Panen salah satu dari SELISIH,BELUM,SELESAI. Example: SELISIH
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "List Data Hasil Panen",
     *  "data": [
     *      {
     *          "id": "1",
     *          "nodokumen": "SPB2024001259",
     *          "tanggal": "2024-12-31 00:00:00",
     *          "tph": "1A02",
     *          "blok": "A02",
     *          "afdeling": "AFD-01",
     *          "fcba": "MTE",
     *          "janjang": "180",
     *          "status": "SELISIH",
     *          "informasi": "SELISIH : -195 JJG"
     *      }
     *  ]
     * }
     */
    public function hasil_panen(Request $request)
    {
        try {
            // Ambil parameter dari query string
            $nodokumen = $request->query('nodokumen');
            $tanggaldari = $request->query('tanggaldari');
            $tanggalsampai = $request->query('tanggalsampai');
            $tph = $request->query('tph');
            $blok = $request->query('blok');
            $afdeling = $request->query('afdeling');
            $fcba = $request->query('fcba');
            $status = $request->query('status');

            $query = "
                SELECT
                    *
                FROM
                    (
                    SELECT
                        h.ID,
                        h.NODOKUMEN,
                        h.TANGGAL,
                        h.TPH,
                        h.FIELDCODE BLOK,
                        h.AFDELING,
                        h.FCBA,
                        SUM(h.OUTPUT) JJG,
                        CASE
                            WHEN SUM(p.OUTPUT) IS NULL THEN 'BELUM'
                            WHEN SUM(p.OUTPUT) IS NOT NULL
                            AND (SUM(h.OUTPUT) - SUM(p.OUTPUT)) <> 0 THEN 'SELISIH'
                            ELSE 'SELESAI'
                        END STATUS,
                        CASE
                            WHEN SUM(p.OUTPUT) IS NULL THEN 'BELUM DIANGKUT'
                            WHEN SUM(p.OUTPUT) IS NOT NULL
                            AND (SUM(h.OUTPUT) - SUM(p.OUTPUT)) <> 0 THEN 'SELISIH : ' || (SUM(h.OUTPUT) - SUM(p.OUTPUT)) || ' JJG'
                            ELSE 'SELESAI DIANGKUT'
                        END INFORMASI
                    FROM
                        SIPSMOBILE.HARVESTING h
                    LEFT JOIN SIPSMOBILE.TPH t ON
                        h.TPH = t.NOTPH
                        AND h.FIELDCODE = t.FIELDCODE
                        AND h.AFDELING = t.AFDELING
                        AND h.FCBA = t.FCBA
                    LEFT JOIN SIPSMOBILE.EMPLOYEE e ON
                        h.KODE_KARYAWAN = e.FCCODE
                    LEFT JOIN (
                        SELECT
                            NODOKUMEN,
                            SUM(OUTPUT) OUTPUT
                        FROM
                            SIPSMOBILE.PENGANGKUTAN p
                        GROUP BY
                            NODOKUMEN) p ON
                        h.NODOKUMEN = p.NODOKUMEN
                    GROUP BY
                        h.ID,
                        h.NODOKUMEN,
                        h.TANGGAL,
                        h.TPH,
                        h.FIELDCODE,
                        h.AFDELING,
                        h.FCBA,
                        h.KODE_KARYAWAN,
                        e.FCNAME,
                        h.OUTPUT
                    ) DATA
                WHERE
                    NODOKUMEN IS NOT NULL
            ";

            $bindings = [];

            // Filter berdasarkan parameter
            if ($nodokumen) {
                $query .= " AND NODOKUMEN = :nodokumen";
                $bindings['nodokumen'] = $nodokumen;
            }

            if ($tanggaldari && $tanggalsampai) {
                $query .= " AND TANGGAL BETWEEN TO_DATE(:tanggaldari, 'YYYY-MM-DD') AND TO_DATE(:tanggalsampai, 'YYYY-MM-DD')";
                $bindings['tanggaldari'] = $tanggaldari;
                $bindings['tanggalsampai'] = $tanggalsampai;
            }

            if ($tph) {
                $query .= " AND TPH = :tph";
                $bindings['tph'] = $tph;
            }

            if ($blok) {
                $query .= " AND BLOK = :blok";
                $bindings['blok'] = $blok;
            }

            if ($afdeling) {
                $query .= " AND AFDELING = :afdeling";
                $bindings['afdeling'] = $afdeling;
            }

            if ($fcba) {
                $query .= " AND FCBA = :fcba";
                $bindings['fcba'] = $fcba;
            }

            if ($status) {
                $query .= " AND STATUS = :status";
                $bindings['status'] = $status;
            }

            // Tambahkan bagian akhir query
            $query .= "
                ORDER BY
                    NODOKUMEN,
                    TANGGAL,
                    STATUS, 
                    TPH, 
                    BLOK
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

            return response()->json([
                'success' => true,
                'message' => 'List Data Hasil Panen',
                'data' => $datas
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Memanggil data hasil langsir.
     *
     * API ini digunakan untuk memperlihatkan hasil langsir pada report di Android SIPS Mobile
     * Namun, jika ingin melakukan filter pada data yang dipanggil, 
     * gunakan parameter pada URL berdasarkan _**Query Parameter**_.
     * Data diurutkan berdasarkan No dokumen, tanggal, status, bisnis unit (fcba), afdeling, tph, blok
     *
     * @queryParam tanggaldari string Optional. Filter Hasil Langsir berdasarkan rentang tanggal, parameter ini diisi tanggal dari. Harus dalam format YYYY-MM-DD. Example: 2025-11-01
     * @queryParam tanggalsampai string Optional. Filter Hasil Langsir berdasarkan rentang tanggal, parameter ini diisi tanggal sampai. Harus dalam format YYYY-MM-DD. Example: 2025-12-20
     * @queryParam nopengangkutan string Optional. Filter Hasil Langsir berdasarkan No Pengangkutan. Example: LGS2010101101
     * @queryParam nodokumen string Optional. Filter Hasil Langsir berdasarkan No Dokumen. Example: SKJ-HOF/MTE/25/02/0001
     * @queryParam kode_kendaraan string Optional. Filter Hasil Langsir berdasarkan kode_kendaraan. Example: DT-R-5818-MSE
     * @queryParam fcba string Optional. Filter Hasil Langsir berdasarkan Bisnis Unit (FCBA). Example: MTE
     * @queryParam afdeling string Optional. Filter Hasil Langsir berdasarkan afdeling. Example: AFD-01
     * @queryParam tujuan string Optional. Filter Hasil Langsir berdasarkan tujuan. Example: DOM
     * @queryParam status string Optional. Filter Hasil Langsir berdasarkan Status Hasil Langsir salah satu dari SELISIH,BELUM,SELESAI. Example: SELISIH
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "List Data Hasil Langsir",
     *  "data": [
     *      {
     *          "id": "1",
     *          "tanggal": "2024-12-31 00:00:00",
     *          "nopengangkutan": "LGS2010101101",
     *          "nodokumen": "SKJ-HOF/MTE/25/02/0001",
     *          "kode_kendaraan": "DT-R-5818-MSE",
     *          "nama_kendaraan": "Dump Truck Rental KT 5818 MSE",
     *          "type_pengangkutan": "1",
     *          "fcba": "MTE",
     *          "afdeling": "AFD-01",
     *          "tujuan": "26",
     *          "janjang": "180",
     *          "janjang_diangkut": "190",
     *          "sisa": "-10",
     *          "status": "SELISIH",
     *          "informasi": "SELISIH : -10 JJG"
     *      }
     *  ]
     * }
     */
    public function hasil_langsir(Request $request)
    {
        try {
            // Ambil parameter dari query string
            $nodokumen = $request->query('nodokumen');
            $nopengangkutan = $request->query('nopengangkutan');
            $tanggaldari = $request->query('tanggaldari');
            $tanggalsampai = $request->query('tanggalsampai');
            $kode_kendaraan = $request->query('kode_kendaraan');
            $afdeling = $request->query('afdeling');
            $fcba = $request->query('fcba');
            $tujuan = $request->query('tujuan');
            $status = $request->query('status');

            $query = "
                SELECT
                    *
                FROM
                    (
                    SELECT 
                        PENGANGKUTAN.TANGGAL,
                        PENGANGKUTAN.NOPENGANGKUTAN,
                        PENGANGKUTAN.NODOKUMEN,
                        PENGANGKUTAN.KODE_KENDARAAN,
                        KENDARAAN.FCNAME NAMA_KENDARAAN,
                        PENGANGKUTAN.TYPE_PENGANGKUTAN,
                        PENGANGKUTAN.FCBA,
                        PENGANGKUTAN.AFDELING,
                        PENGANGKUTAN.PABRIK_TUJUAN TUJUAN,
                        NVL(SUM(PENGANGKUTAN.OUTPUT),0) JANJANG,
                        NVL(OUTPUT_PENGANGKUTAN, 0) JANJANG_DIANGKUT,
                        NVL(SUM(PENGANGKUTAN.OUTPUT),0) - NVL(OUTPUT_PENGANGKUTAN, 0) SISA,
                        CASE
                            WHEN (NVL(SUM(PENGANGKUTAN.OUTPUT),0) - NVL(OUTPUT_PENGANGKUTAN, 0)) > 0 THEN 'BELUM'
                            WHEN (NVL(SUM(PENGANGKUTAN.OUTPUT),0) - NVL(OUTPUT_PENGANGKUTAN, 0)) < 0 THEN 'SELISIH'
                            ELSE 'SELESAI'
                        END STATUS,
                        CASE
                            WHEN (NVL(SUM(PENGANGKUTAN.OUTPUT),0) - NVL(OUTPUT_PENGANGKUTAN, 0)) > 0 THEN 'BELUM SELESAI DIANGKUT'
                            WHEN (NVL(SUM(PENGANGKUTAN.OUTPUT),0) - NVL(OUTPUT_PENGANGKUTAN, 0)) < 0 THEN 'SELISIH : ' || (NVL(SUM(PENGANGKUTAN.OUTPUT),0) - NVL(OUTPUT_PENGANGKUTAN, 0)) || ' JJG'
                            ELSE 'SELESAI DIANGKUT'
                        END INFORMASI
                    FROM
                        SIPSMOBILE.PENGANGKUTAN
                    LEFT JOIN
                        (SELECT DISTINCT FCCODE, FCNAME FROM IPLASPROD.VEHICLE) KENDARAAN
                    ON 
                        PENGANGKUTAN.KODE_KENDARAAN = KENDARAAN.FCCODE    
                    LEFT JOIN (
                        SELECT
                            NODOKUMEN,
                            SUM(OUTPUT) OUTPUT_PENGANGKUTAN
                        FROM
                            SIPSMOBILE.PENGANGKUTAN p
                        WHERE TYPE_PENGANGKUTAN = 2
                        GROUP BY
                            NODOKUMEN
                        ) p ON
                        PENGANGKUTAN.NODOKUMEN = p.NODOKUMEN
                    WHERE 
                        PENGANGKUTAN.TYPE_PENGANGKUTAN = 1
                    GROUP BY 
                        PENGANGKUTAN.TANGGAL,
                        PENGANGKUTAN.NOPENGANGKUTAN,
                        PENGANGKUTAN.NODOKUMEN,
                        PENGANGKUTAN.KODE_KENDARAAN,
                        KENDARAAN.FCNAME,
                        PENGANGKUTAN.TYPE_PENGANGKUTAN,
                        PENGANGKUTAN.FCBA,
                        PENGANGKUTAN.AFDELING,
                        PENGANGKUTAN.PABRIK_TUJUAN,
                        OUTPUT_PENGANGKUTAN
                    ORDER BY 
                        PENGANGKUTAN.TANGGAL DESC,
                        PENGANGKUTAN.NOPENGANGKUTAN DESC,
                        PENGANGKUTAN.NODOKUMEN DESC
                    ) DATA
                WHERE
                    NODOKUMEN IS NOT NULL
            ";

            $bindings = [];

            // Filter berdasarkan parameter
            if ($tanggaldari && $tanggalsampai) {
                // Optional: jaga-jaga kalau user kebalik isi (tanggaldari > tanggalsampai)
                $startDate = $tanggaldari;
                $endDate   = $tanggalsampai;

                if ($startDate > $endDate) {
                    $startDate = $tanggalsampai;
                    $endDate   = $tanggaldari;
                }

                $query .= " and TRUNC(TANGGAL) between TO_DATE(:tanggaldari, 'YYYY-MM-DD') and TO_DATE(:tanggalsampai, 'YYYY-MM-DD') ";
                $bindings['tanggaldari'] = $startDate;
                $bindings['tanggalsampai']   = $endDate;
            } elseif ($tanggaldari) {
                $query .= " and TRUNC(TANGGAL) = TO_DATE(:tanggaldari, 'YYYY-MM-DD') ";
                $bindings['tanggaldari'] = $tanggaldari;
            } elseif ($tanggalsampai) {
                $query .= " and TRUNC(TANGGAL) = TO_DATE(:tanggalsampai, 'YYYY-MM-DD') ";
                $bindings['tanggalsampai'] = $tanggalsampai;
            }

            if ($nopengangkutan) {
                $query .= " AND NOPENGANGKUTAN = :nopengangkutan";
                $bindings['nopengangkutan'] = $nopengangkutan;
            }

            if ($nodokumen) {
                $query .= " AND NODOKUMEN = :nodokumen";
                $bindings['nodokumen'] = $nodokumen;
            }

            if ($kode_kendaraan) {
                $query .= " AND KODE_KENDARAAN = :kode_kendaraan";
                $bindings['kode_kendaraan'] = $kode_kendaraan;
            }

            if ($fcba) {
                $query .= " AND FCBA = :fcba";
                $bindings['fcba'] = $fcba;
            }

            if ($afdeling) {
                $query .= " AND AFDELING = :afdeling";
                $bindings['afdeling'] = $afdeling;
            }

            if ($tujuan) {
                $query .= " AND TUJUAN = :tujuan";
                $bindings['tujuan'] = $tujuan;
            }

            if ($status) {
                $query .= " AND STATUS = :status";
                $bindings['status'] = $status;
            }

            // Tambahkan bagian akhir query
            $query .= "
                ORDER BY 
                    TANGGAL DESC,
                    NOPENGANGKUTAN DESC,
                    NODOKUMEN DESC
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

            return response()->json([
                'success' => true,
                'message' => 'List Data Hasil Panen',
                'data' => $datas
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Memanggil data hasil pengangkutan.
     *
     * API ini digunakan untuk memanggil data Pengangkutan secara keseluruhan yang dikelompokkan berdasarkan no pengangkutan. 
     * Namun, jika ingin melakukan filter pada data yang dipanggil, 
     * gunakan parameter pada URL berdasarkan _**Query Parameter**_.
     * Data diurutkan berdasarkan Tanggal terbaru, Afdeling, dan Kode Karyawan.
     *
     * @queryParam nopengangkutan string Optional. Filter Pengangkutan berdasarkan No Pengangkutan. Example: DRC2010101101
     * @queryParam nospb string Optional. Filter Pengangkutan berdasarkan No SPB. Example: SPB2024001259
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
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "List Data Pengangkutan",
     *  "data": [
     *      {
     *          "id": "5",
     *          "nopengangkutan": "DRC2010101101",
     *          "nospb": "SPB2024001259",
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
     *          "brondolan": "2"
     *      }
     *  ]
     * }
     */
    public function hasil_pengangkutan(Request $request)
    {
        try {
            // Ambil parameter dari query string
            $nopengangkutan = $request->query('nopengangkutan');
            $nospb = $request->query('nospb');
            $tanggal = $request->query('tanggal');
            $tanggalEnd     = $request->query('tanggal_end');
            $kode_karyawan_kerani = $request->query('kode_karyawan_kerani');
            $kode_karyawan_driver = $request->query('kode_karyawan_driver');
            $tkbm1 = $request->query('tkbm1');
            $tkbm2 = $request->query('tkbm2');
            $tkbm3 = $request->query('tkbm3');
            $tkbm4 = $request->query('tkbm4');
            $tkbm5 = $request->query('tkbm5');
            $kode_kendaraan = $request->query('kode_kendaraan');
            $type_pengangkutan = $request->query('type_pengangkutan');
            $fcba = $request->query('fcba');
            $afdeling = $request->query('afdeling');
            $pabrik_tujuan = $request->query('pabrik_tujuan');

            $query = "
                SELECT 
                    PENGANGKUTAN.NOPENGANGKUTAN,
                    PENGANGKUTAN.NOSPB,
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
                    PENGANGKUTAN.AFDELING,
                    PENGANGKUTAN.PABRIK_TUJUAN,
                    NVL(SUM(PENGANGKUTAN.TOTALJANJANG),0) TOTALJANJANG,
                    NVL(SUM(PENGANGKUTAN.OUTPUT),0) OUTPUT,
                    NVL(SUM(PENGANGKUTAN.JANJANGNORMAL),0) JANJANGNORMAL,
                    NVL(SUM(PENGANGKUTAN.BRONDOLAN),0) BRONDOLAN
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
                    (SELECT DISTINCT FCCODE, FCNAME FROM IPLASPROD.VEHICLE) KENDARAAN
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

            // Tambahkan bagian akhir query
            $query .= "   
                GROUP BY 
                    PENGANGKUTAN.NOPENGANGKUTAN,
                    PENGANGKUTAN.NOSPB,
                    PENGANGKUTAN.TANGGAL,
                    PENGANGKUTAN.KODE_KARYAWAN_KERANI,
                    KERANI.FCNAME,
                    PENGANGKUTAN.KODE_KARYAWAN_DRIVER,
                    DRIVER.FCNAME,
                    PENGANGKUTAN.TKBM1,
                    TKBM1.FCNAME,
                    PENGANGKUTAN.TKBM2,
                    TKBM2.FCNAME,
                    PENGANGKUTAN.TKBM3,
                    TKBM3.FCNAME,
                    PENGANGKUTAN.TKBM4,
                    TKBM4.FCNAME,
                    PENGANGKUTAN.TKBM5,
                    TKBM5.FCNAME,
                    PENGANGKUTAN.KODE_KENDARAAN,
                    PENGANGKUTAN.TYPE_PENGANGKUTAN,
                    KENDARAAN.FCNAME,
                    PENGANGKUTAN.FCBA,
                    PENGANGKUTAN.AFDELING,
                    PENGANGKUTAN.PABRIK_TUJUAN
                ORDER BY 
                    PENGANGKUTAN.TANGGAL DESC,
                    PENGANGKUTAN.NOPENGANGKUTAN DESC,
                    PENGANGKUTAN.NOSPB DESC
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
     * Memanggil data Attendance GAD / Attendance GAD Temp from SIPS Mobile.
     *
     * API ini digunakan untuk memanggil SIPS Mobile untuk dimasukkan ke Attendance GAD / Attendance GAD Temp. 
     * Namun, jika ingin melakukan filter pada data yang dipanggil, 
     * gunakan parameter pada URL berdasarkan _**Query Parameter**_.
     * Data diurutkan berdasarkan Tanggal terbaru, FCBA, Afdeling, Gang, dan Kode Karyawan.
     *
     * @queryParam totalcount string Optional. Filter Attendance berdasarkan nilai lebih dari totalcount. Example: 2
     * @queryParam tanggal string Optional. Filter Attendance berdasarkan tanggal. Harus dalam format YYYY-MM-DD. Example: 2025-11-01
     * @queryParam tanggal_end string Optional. Filter Attendance berdasarkan rentang tanggal. Harus dalam format YYYY-MM-DD. Example: 2025-11-20
     * @queryParam fcba string Optional. Filter Attendance berdasarkan FCBA. Example: MTE
     * @queryParam afdeling string Optional. Filter Attendance berdasarkan afdeling. Example: AFD-01
     * @queryParam gangcode string Optional. Filter Attendance berdasarkan gangcode. Example: PN013
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "List Data Attendance GAD / Attendance GAD Temp",
     *  "data": [
     *      {
     *      	"totalcount" : 4,
     *      	"id" : 229,
     *      	"afdeling" : "AFD-01",
     *      	"gangcode" : "PN013",
     *      	"fddate" : "2025-10-31 17:00:00",
     *      	"supervision_1" : null,
     *      	"supervision_2" : null,
     *      	"supervision_3" : null,
     *      	"supervision_4" : null,
     *      	"supervision_5" : null,
     *      	"employeecode" : "03-891219-180801-0116",
     *      	"attendance" : "KJ",
     *      	"jobcode" : "505030101",
     *      	"locationtype" : "FF",
     *      	"locationcode" : "I45P",
     *      	"mandays" : 0.25,
     *      	"othrs" : 0,
     *      	"rate" : 0,
     *      	"unit" : 25,
     *      	"output" : 0.425,
     *      	"reference" : null,
     *      	"remarks" : "SIPS MOBILE",
     *      	"fcentry" : "andrew",
     *      	"fcedit" : "adrianus",
     *      	"fcip" : null,
     *      	"fcba" : "MTE",
     *      	"lastupdate" : "2026-01-08 03:46:54",
     *      	"lasttime" : "10:46",
     *      	"linenokey" : 1441715,
     *      	"overtime_hours" : 0,
     *      	"type_overtime" : 0,
     *      	"chargejob" : null,
     *      	"chargetype" : null,
     *      	"chargecode" : null,
     *      	"bucket" : null,
     *      	"spbno" : null,
     *      	"kg_brondolan" : 1.75,
     *      	"rowstate" : "Approved",
     *      	"document_classification" : 501,
     *      	"basis_bm" : 0,
     *      	"kg_janjang" : 384.75,
     *      	"bjr" : 15.39,
     *      	"documentno" : 229,
     *      	"sourcetime" : "2025-10-31 19:56:04",
     *      	"fieldcode" : "I45"
     *      }
     *  ]
     * }
     */
    public function upload_attendance(Request $request)
    {
        try {
            // Ambil parameter dari query string
            $totalcount = $request->query('totalcount');
            $tanggal = $request->query('tanggal');
            $tanggalEnd = $request->query('tanggal_end');
            $fcba = $request->query('fcba');
            $afdeling = $request->query('afdeling');
            $gangcode = $request->query('gangcode');

            $query = "
                SELECT
                    * 
                FROM
                    (
                    SELECT 
                        COUNT(*) OVER (PARTITION BY h.TANGGAL, h.FIELDCODE, h.KODE_KARYAWAN) TOTALCOUNT,
                        a.ID,
                        a.GANG GANGCODE,
                        a.SECTION AFDELING,
                        a.TANGGAL FDDATE,
                        h.KODE_KARYAWAN_MANDOR1 SUPERVISION_1,
                        h.KODE_KARYAWAN_MANDOR_PANEN SUPERVISION_2,
                        h.KODE_KARYAWAN_KERANI SUPERVISION_3,
                        '' SUPERVISION_4,
                        '' SUPERVISION_5,
                        a.KODE_KARYAWAN EMPLOYEECODE,
                        a.ATTENDANCE,
                        CASE 
                            WHEN ATTENDANCE_TYPE = 'REGULAR' THEN (SELECT DISTINCT FCCODE FROM IPLASPROD.JOB WHERE FCNAME LIKE '%POTONG BUAH%')
                            ELSE (SELECT mit.FCCODE FROM IPLASPROD.MAPPING_INTRACO_TAB mit WHERE FCBA = a.FCBA_DESTINATION)
                        END JOBCODE,
                        CASE 
                            WHEN ATTENDANCE_TYPE = 'REGULAR' THEN (SELECT DISTINCT JOB_CATEGORY FROM IPLASPROD.JOB WHERE FCNAME LIKE '%POTONG BUAH%')
                            ELSE (SELECT IPLASPROD.JOB_API.GET_JOB_CATEGORY(mit.FCCODE, 'HOF') TT FROM IPLASPROD.MAPPING_INTRACO_TAB mit WHERE FCBA = a.FCBA)
                        END LOCATIONTYPE,
                        CASE 
                            WHEN ATTENDANCE_TYPE = 'REGULAR' THEN f.FCCODE
                            ELSE 'BS'
                        END LOCATIONCODE,
                        CASE 
                            WHEN TO_NUMBER(NVL(a.MANDAYS, 0)) = 0 THEN 0
                            ELSE TO_NUMBER(a.MANDAYS) / COUNT(*) OVER (PARTITION BY h.TANGGAL, h.KODE_KARYAWAN)
                        END	MANDAYS,
                        0 OTHRS,
                        0 RATE,
                        CASE 
                            WHEN TO_NUMBER(OUTPUT) = 0 THEN 0
                            ELSE TO_NUMBER(OUTPUT) / COUNT(*) OVER (PARTITION BY h.TANGGAL, h.FIELDCODE, h.KODE_KARYAWAN)
                        END	UNIT,
                        CASE 
                            WHEN TO_NUMBER(LUAS) = 0 THEN 0
                            ELSE TO_NUMBER(LUAS) / COUNT(*) OVER (PARTITION BY h.TANGGAL, h.FIELDCODE, h.KODE_KARYAWAN)
                        END OUTPUT,
                        '' REFERENCE, 
                        'SIPS MOBILE' REMARKS, 
                        a.CREATED_BY FCENTRY,
                        a.UPDATED_BY FCEDIT,
                        a.MAC_ADDRESS FCIP,
                        a.FCBA, 
                        SYSDATE LASTUPDATE,
                        TO_CHAR(SYSDATE, 'HH24:MI') LASTTIME,
                        bm.base_max + ROW_NUMBER() OVER (ORDER BY a.TANGGAL, a.KODE_KARYAWAN) AS LINENOKEY,
                        0 OVERTIME_HOURS,
                        0 TYPE_OVERTIME,
                        CASE 
                            WHEN ATTENDANCE_TYPE <> 'REGULAR' THEN (SELECT DISTINCT FCCODE FROM IPLASPROD.JOB WHERE FCNAME LIKE '%POTONG BUAH%')
                            ELSE ''
                        END CHARGEJOB,
                        CASE 
                            WHEN ATTENDANCE_TYPE <> 'REGULAR' THEN (SELECT DISTINCT JOB_CATEGORY FROM IPLASPROD.JOB WHERE FCNAME LIKE '%POTONG BUAH%')
                            ELSE ''
                        END CHARGETYPE,
                        CASE 
                            WHEN ATTENDANCE_TYPE <> 'REGULAR' THEN f.FCCODE
                            ELSE ''
                        END CHARGECODE,
                        '' BUCKET,
                        '' SPBNO,
                        CASE 
                            WHEN TO_NUMBER(h.BRONDOL) = 0 THEN 0
                            ELSE TO_NUMBER(h.BRONDOL) / COUNT(*) OVER (PARTITION BY h.TANGGAL, h.FIELDCODE, h.KODE_KARYAWAN)
                        END	KG_BRONDOLAN,
                        'Approved' ROWSTATE,
                        501 DOCUMENT_CLASSIFICATION,
                        0 BASIS_BM,
                        CASE 
                            WHEN TO_NUMBER(OUTPUT) = 0 THEN 0
                            ELSE (TO_NUMBER(OUTPUT) / COUNT(*) OVER (PARTITION BY h.TANGGAL, h.FIELDCODE, h.KODE_KARYAWAN)) * f.PRESSEMESTER_ABW
                        END	KG_JANJANG,
                        f.PRESSEMESTER_ABW BJR,
                        a.ID DOCUMENTNO,
                        a.CREATED_AT SOURCETIME,
                        0 JANJANG,
                        'SIPS MOBILE GENERATE' GENERATE,
                        SYSDATE GENERATETIME,
                        h.FIELDCODE
                    FROM
                        SIPSMOBILE.ATTENDANCE a
                    INNER JOIN 
                        (
                        SELECT
                            h.TANGGAL,
                            h.KODE_KARYAWAN_MANDOR1,
                            h.KODE_KARYAWAN_MANDOR_PANEN,
                            h.KODE_KARYAWAN_KERANI,
                            h.KODE_KARYAWAN,
                            h.FIELDCODE,
                            h.AFDELING,
                            h.FCBA,
                            SUM(a.LUAS) LUAS,
                            SUM(OUTPUT) OUTPUT,
                            SUM(MENTAH) MENTAH,
                            SUM(OVERRIPE) OVERRIPE,
                            SUM(BUSUK) BUSUK,
                            SUM(BUSUK2) BUSUK2,
                            SUM(BUAHKECIL) BUAHKECIL,
                            SUM(PARTENO) PARTENO,
                            SUM(BRONDOL) BRONDOL,
                            SUM(TANGKAIPANJANG) TANGKAIPANJANG
                        FROM
                            SIPSMOBILE.HARVESTING h
                        LEFT JOIN SIPSMOBILE.ANCAKS a ON
                            h.NOANCAK = a.NOANCAK
                            AND h.FIELDCODE = a.FIELDCODE
                            AND h.AFDELING = a.AFDELING
                            AND h.FCBA = a.FCBA
                        GROUP BY
                            h.TANGGAL,
                            h.KODE_KARYAWAN_MANDOR1,
                            h.KODE_KARYAWAN_MANDOR_PANEN,
                            h.KODE_KARYAWAN_KERANI,
                            h.KODE_KARYAWAN,
                            h.FIELDCODE,
                            h.AFDELING,
                            h.FCBA
                        ) h
                        ON
                        a.FCBA = h.FCBA
                        AND a.TANGGAL = h.TANGGAL
                        AND a.KODE_KARYAWAN = h.KODE_KARYAWAN
                    CROSS JOIN
                        (SELECT NVL(MAX(LINENOKEY), 0) AS base_max FROM IPLASPROD.ATTENDANCE_GAD) bm
                    LEFT JOIN 
                        IPLASPROD.FIELD f 
                        ON
                        f.FIELD_INFO_02 = h.FIELDCODE AND f.FCBA = h.FCBA AND ACTIVATION = 'Y'
                    )
                WHERE 
                    NOT EXISTS (SELECT 1 FROM IPLASPROD.ATTENDANCE_GAD agt WHERE agt.DOCUMENTNO = ID)
            ";

            $bindings = [];

            // Filter berdasarkan parameter
            if ($totalcount) {
                $query .= " AND TOTALCOUNT > :totalcount";
                $bindings['totalcount'] = $totalcount;
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

                $query .= " and TRUNC(FDDATE) between TO_DATE(:tanggal, 'YYYY-MM-DD') and TO_DATE(:tanggal_end, 'YYYY-MM-DD') ";
                $bindings['tanggal'] = $startDate;
                $bindings['tanggal_end']   = $endDate;
            } elseif ($tanggal) {
                $query .= " and TRUNC(FDDATE) = TO_DATE(:tanggal, 'YYYY-MM-DD') ";
                $bindings['tanggal'] = $tanggal;
            } elseif ($tanggalEnd) {
                $query .= " and TRUNC(FDDATE) = TO_DATE(:tanggal_end, 'YYYY-MM-DD') ";
                $bindings['tanggal_end'] = $tanggalEnd;
            }

            if ($fcba) {
                $query .= " AND FCBA = :fcba";
                $bindings['fcba'] = $fcba;
            }

            if ($afdeling) {
                $query .= " AND AFDELING = :afdeling";
                $bindings['afdeling'] = $afdeling;
            }

            if ($gangcode) {
                $query .= " AND GANGCODE = :gangcode";
                $bindings['gangcode'] = $gangcode;
            }

            // Tambahkan bagian akhir query
            $query .= "
                ORDER BY 
                    FDDATE,
                    FCBA,
                    AFDELING,
                    GANGCODE,
                    EMPLOYEECODE,
                    LOCATIONCODE
            ";

            // Jalankan query
            $datas = DB::connection('oracle')->select($query, $bindings);

            // 🔥 NORMALISASI FORMAT ANGKA ORACLE
            foreach ($datas as &$row) {
                foreach ($row as $key => $value) {
                    if (is_numeric($value)) {
                        $row->$key = $this->formatNumber($value);
                    }
                }
            }

            if (empty($datas)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data tidak ditemukan.',
                    'data' => []
                ], 404);
            }

            return new AllResource(true, 'List Data Attendance GAD / Attendance GAD Temp', $datas);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function formatNumber($value)
    {
        if ($value === null) return null;

        // Paksa ke float agar bisa dibulatkan
        $num = (float) $value;

        // Bulatkan ke 3 digit desimal
        $num = round($num, 3);

        // Konversi ke string tanpa notasi scientific
        $v = number_format($num, 3, '.', '');

        // Hilangkan trailing zero: 1.500 → 1.5 , 10.000 → 10
        $v = rtrim(rtrim($v, '0'), '.');

        // Tambahkan 0 jika mulai dengan titik
        if (str_starts_with($v, '.')) {
            $v = '0' . $v;
        }

        return $v;
    }
}
