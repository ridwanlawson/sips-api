<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\AllResource;

/**
 * @group Uploads
 *
 */
class UploadController extends Controller
{
    /**
     * Upload Attendance ke database SIPS.
     *
     * Endpoint ini digunakan untuk mengirim data attendance dari SIPS Mobile ke tabel IPLASPROD.ATTENDANCE_GAD dan IPLASPROD.ATTENDANCE_GAD_TEMP.
     * Data harus dikirim sebagai array dengan struktur sesuai format GET /report/upload-attendance.
     *
     * @bodyParam data array required Array data attendance yang akan diinsert ke tabel ATTENDANCE_GAD dan ATTENDANCE_GAD_TEMP.
     * @bodyParam data[].gangcode string required Kode gang. Example: PN011
     * @bodyParam data[].fddate string required Tanggal attendance (format: YYYY-MM-DD HH:MM:SS). Example: 2025-11-08 00:00:00
     * @bodyParam data[].supervision_1 string optional Kode mandor panen 1. Example: null
     * @bodyParam data[].supervision_2 string optional Kode mandor panen 2. Example: null
     * @bodyParam data[].supervision_3 string optional Kode kerani. Example: null
     * @bodyParam data[].supervision_4 string optional Supervisi 4. Example: null
     * @bodyParam data[].supervision_5 string optional Supervisi 5. Example: null
     * @bodyParam data[].employeecode string required Kode karyawan. Example: 06-770323-130910-0047
     * @bodyParam data[].attendance string required Status attendance. Example: KJ
     * @bodyParam data[].jobcode string optional Kode pekerjaan. Example: 505030101
     * @bodyParam data[].locationtype string optional Tipe lokasi. Example: FF
     * @bodyParam data[].locationcode string optional Kode lokasi. Example: I45P
     * @bodyParam data[].mandays float optional Mandays. Example: 0.1666666666666667
     * @bodyParam data[].othrs float optional Jam lembur. Example: 0
     * @bodyParam data[].rate float optional Tarif. Example: 0
     * @bodyParam data[].unit float optional Unit kerja. Example: 25
     * @bodyParam data[].output float optional Output. Example: 0.425
     * @bodyParam data[].reference string optional Referensi. Example: null
     * @bodyParam data[].remarks string optional Keterangan. Example: SIPS MOBILE
     * @bodyParam data[].fcentry string optional Dibuat oleh. Example: andrew
     * @bodyParam data[].fcedit string optional Diubah oleh. Example: null
     * @bodyParam data[].fcip string optional IP address. Example: null
     * @bodyParam data[].fcba string required Kode FCBA. Example: MTE
     * @bodyParam data[].lastupdate string optional Waktu update terakhir. Example: 2026-01-09 14:20:27
     * @bodyParam data[].lasttime string optional Jam update terakhir. Example: 14:20
     * @bodyParam data[].linenokey integer required Key unik baris. Example: 1442813
     * @bodyParam data[].overtime_hours float optional Jam lembur. Example: 0
     * @bodyParam data[].type_overtime integer optional Tipe lembur. Example: 0
     * @bodyParam data[].chargejob string optional Job yang dibebankan. Example: null
     * @bodyParam data[].chargetype string optional Tipe yang dibebankan. Example: null
     * @bodyParam data[].chargecode string optional Kode yang dibebankan. Example: null
     * @bodyParam data[].bucket string optional Bucket. Example: null
     * @bodyParam data[].spbno string optional No SPB. Example: null
     * @bodyParam data[].kg_janjang float optional Berat janjang (kg). Example: 384.75
     * @bodyParam data[].kg_brondolan float optional Berat brondolan (kg). Example: 1.75
     * @bodyParam data[].rowstate string optional Status baris. Example: Approved
     * @bodyParam data[].document_classification integer optional Klasifikasi dokumen. Example: 501
     * @bodyParam data[].basis_bm integer optional Basis BM. Example: 0
     * @bodyParam data[].bjr float optional BJR. Example: 15.39
     * @bodyParam data[].documentno integer required Nomor dokumen. Example: 368
     * @bodyParam data[].sourcetime string optional Waktu sumber data. Example: 2025-11-09 00:24:37
     * @bodyParam data[].janjang float optional Janjang (untuk ATTENDANCE_GAD_TEMP). Example: 0
     * @bodyParam data[].generate string optional Informasi generate (untuk ATTENDANCE_GAD_TEMP). Example: SIPS MOBILE GENERATE
     * @bodyParam data[].generatetime string optional Waktu generate (untuk ATTENDANCE_GAD_TEMP). Example: 2026-01-09 14:20:27
     * @bodyParam data[].fieldcode string optional Kode field. Example: I45
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data Karyawan berhasil ditambahkan.",
     *  "data": [368, 368, 368, 368]
     * }
     * @response 400 scenario="invalid data" {
     *  "success": false,
     *  "message": "Data tidak valid atau kosong."
     * }
     * @response 500 scenario="error" {
     *  "success": false,
     *  "message": "Terjadi kesalahan saat menyimpan data. Silakan coba lagi.",
     *  "error": "Deskripsi error dari database"
     * }
     */
    public function attendance(Request $request)
    {
        try {
            // Ambil data dari request (diasumsikan array of records)
            $datas = $request->input('data');
            if (!$datas || !is_array($datas)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid atau kosong.'
                ], 400);
            }

            // Dapatkan base_max LINENOKEY dari ATTENDANCE_GAD dan ATTENDANCE_GAD_TEMP
            $baseMaxQuery1 = "SELECT NVL(MAX(LINENOKEY), 0) AS base_max FROM IPLASPROD.ATTENDANCE_GAD";
            $baseMaxResult1 = DB::connection('oracle')->selectOne($baseMaxQuery1);
            $baseMax1 = $baseMaxResult1->base_max ?? 0;

            $baseMaxQuery2 = "SELECT NVL(MAX(LINENOKEY), 0) AS base_max FROM IPLASPROD.ATTENDANCE_GAD_TEMP";
            $baseMaxResult2 = DB::connection('oracle')->selectOne($baseMaxQuery2);
            $baseMax2 = $baseMaxResult2->base_max ?? 0;

            $inserted = [];
            $linenoKeyCounter1 = 0;
            $linenoKeyCounter2 = 0;
            $currentDateTime = now(); // Current timestamp
            $currentTime = $currentDateTime->format('H:i'); // Format HH:MM
            foreach ($datas as $r_data) {
                // Normalisasi keys dari snake_case ke UPPERCASE
                $data = array_change_key_case($r_data, CASE_UPPER);
                // Hitung LINENOKEY baru berdasarkan base_max masing-masing tabel
                $linenoKeyCounter1++;
                $newLinenoKey1 = $baseMax1 + $linenoKeyCounter1;
                $linenoKeyCounter2++;
                $newLinenoKey2 = $baseMax2 + $linenoKeyCounter2;

                // Insert ke ATTENDANCE_GAD dengan SOURCETIME
                $sql1 = "INSERT INTO IPLASPROD.ATTENDANCE_GAD (
                    GANGCODE, FDDATE, SUPERVISION_1, SUPERVISION_2, SUPERVISION_3, SUPERVISION_4, SUPERVISION_5, EMPLOYEECODE, ATTENDANCE, JOBCODE, LOCATIONTYPE, LOCATIONCODE, MANDAYS, OTHRS, RATE, UNIT, OUTPUT, REFERENCE, REMARKS, FCENTRY, FCEDIT, FCIP, FCBA, LASTUPDATE, LASTTIME, LINENOKEY, OVERTIME_HOURS, TYPE_OVERTIME, CHARGEJOB, CHARGETYPE, CHARGECODE, BUCKET, SPBNO, KG_JANJANG, KG_BRONDOLAN, ROWSTATE, DOCUMENT_CLASSIFICATION, BASIS_BM, BJR, DOCUMENTNO
                ) VALUES (
                    :GANGCODE, :FDDATE, :SUPERVISION_1, :SUPERVISION_2, :SUPERVISION_3, :SUPERVISION_4, :SUPERVISION_5, :EMPLOYEECODE, :ATTENDANCE, :JOBCODE, :LOCATIONTYPE, :LOCATIONCODE, :MANDAYS, :OTHRS, :RATE, :UNIT, :OUTPUT, :REFERENCE, :REMARKS, :FCENTRY, :FCEDIT, :FCIP, :FCBA, :LASTUPDATE, :LASTTIME, :LINENOKEY, :OVERTIME_HOURS, :TYPE_OVERTIME, :CHARGEJOB, :CHARGETYPE, :CHARGECODE, :BUCKET, :SPBNO, :KG_JANJANG, :KG_BRONDOLAN, :ROWSTATE, :DOCUMENT_CLASSIFICATION, :BASIS_BM, :BJR, :DOCUMENTNO
                )";
                $params1 = [
                    'GANGCODE' => $data['GANGCODE'] ?? null,
                    'FDDATE' => $data['FDDATE'] ?? null,
                    'SUPERVISION_1' => $data['SUPERVISION_1'] ?? null,
                    'SUPERVISION_2' => $data['SUPERVISION_2'] ?? null,
                    'SUPERVISION_3' => $data['SUPERVISION_3'] ?? null,
                    'SUPERVISION_4' => $data['SUPERVISION_4'] ?? null,
                    'SUPERVISION_5' => $data['SUPERVISION_5'] ?? null,
                    'EMPLOYEECODE' => $data['EMPLOYEECODE'] ?? null,
                    'ATTENDANCE' => $data['ATTENDANCE'] ?? null,
                    'JOBCODE' => $data['JOBCODE'] ?? '505030101',  // Default jobcode jika kosong
                    'LOCATIONTYPE' => $data['LOCATIONTYPE'] ?? null,
                    'LOCATIONCODE' => $data['LOCATIONCODE'] ?? null,
                    'MANDAYS' => $data['MANDAYS'] ?? null,
                    'OTHRS' => $data['OTHRS'] ?? null,
                    'RATE' => $data['RATE'] ?? null,
                    'UNIT' => $data['UNIT'] ?? null,
                    'OUTPUT' => $data['OUTPUT'] ?? null,
                    'REFERENCE' => $data['REFERENCE'] ?? null,
                    'REMARKS' => $data['REMARKS'] ?? null,
                    'FCENTRY' => $data['FCENTRY'] ?? null,
                    'FCEDIT' => $data['FCEDIT'] ?? null,
                    'FCIP' => $data['FCIP'] ?? null,
                    'FCBA' => $data['FCBA'] ?? null,
                    'LASTUPDATE' => $currentDateTime,
                    'LASTTIME' => $currentTime,
                    'LINENOKEY' => $newLinenoKey1,
                    'OVERTIME_HOURS' => $data['OVERTIME_HOURS'] ?? null,
                    'TYPE_OVERTIME' => $data['TYPE_OVERTIME'] ?? null,
                    'CHARGEJOB' => $data['CHARGEJOB'] ?? null,
                    'CHARGETYPE' => $data['CHARGETYPE'] ?? null,
                    'CHARGECODE' => $data['CHARGECODE'] ?? null,
                    'BUCKET' => $data['BUCKET'] ?? null,
                    'SPBNO' => $data['SPBNO'] ?? null,
                    'KG_JANJANG' => $data['KG_JANJANG'] ?? null,
                    'KG_BRONDOLAN' => $data['KG_BRONDOLAN'] ?? null,
                    'ROWSTATE' => $data['ROWSTATE'] ?? null,
                    'DOCUMENT_CLASSIFICATION' => $data['DOCUMENT_CLASSIFICATION'] ?? null,
                    'BASIS_BM' => $data['BASIS_BM'] ?? null,
                    'BJR' => $data['BJR'] ?? null,
                    'DOCUMENTNO' => $data['DOCUMENTNO'] ?? null,
                ];
                DB::connection('oracle')->insert($sql1, $params1);

                // Insert ke ATTENDANCE_GAD_TEMP dengan SOURCETIME
                $sql2 = "INSERT INTO IPLASPROD.ATTENDANCE_GAD_TEMP (
                    GANGCODE, FDDATE, SUPERVISION_1, SUPERVISION_2, SUPERVISION_3, SUPERVISION_4, SUPERVISION_5, EMPLOYEECODE, ATTENDANCE, JOBCODE, LOCATIONTYPE, LOCATIONCODE, MANDAYS, OTHRS, RATE, UNIT, OUTPUT, REFERENCE, REMARKS, FCENTRY, FCEDIT, FCIP, FCBA, LASTUPDATE, LASTTIME, LINENOKEY, OVERTIME_HOURS, TYPE_OVERTIME, CHARGEJOB, CHARGETYPE, CHARGECODE, JANJANG, ROWSTATE, DOCUMENT_CLASSIFICATION, GENERATE, GENERATETIME, BASIS_BM, KG_JANJANG, BJR, DOCUMENTNO
                ) VALUES (
                    :GANGCODE, :FDDATE, :SUPERVISION_1, :SUPERVISION_2, :SUPERVISION_3, :SUPERVISION_4, :SUPERVISION_5, :EMPLOYEECODE, :ATTENDANCE, :JOBCODE, :LOCATIONTYPE, :LOCATIONCODE, :MANDAYS, :OTHRS, :RATE, :UNIT, :OUTPUT, :REFERENCE, :REMARKS, :FCENTRY, :FCEDIT, :FCIP, :FCBA, :LASTUPDATE, :LASTTIME, :LINENOKEY, :OVERTIME_HOURS, :TYPE_OVERTIME, :CHARGEJOB, :CHARGETYPE, :CHARGECODE, :JANJANG, :ROWSTATE, :DOCUMENT_CLASSIFICATION, :GENERATE, :GENERATETIME, :BASIS_BM, :KG_JANJANG, :BJR, :DOCUMENTNO
                )";
                $params2 = [
                    'GANGCODE' => $data['GANGCODE'] ?? null,
                    'FDDATE' => $data['FDDATE'] ?? null,
                    'SUPERVISION_1' => $data['SUPERVISION_1'] ?? null,
                    'SUPERVISION_2' => $data['SUPERVISION_2'] ?? null,
                    'SUPERVISION_3' => $data['SUPERVISION_3'] ?? null,
                    'SUPERVISION_4' => $data['SUPERVISION_4'] ?? null,
                    'SUPERVISION_5' => $data['SUPERVISION_5'] ?? null,
                    'EMPLOYEECODE' => $data['EMPLOYEECODE'] ?? null,
                    'ATTENDANCE' => $data['ATTENDANCE'] ?? null,
                    'JOBCODE' => $data['JOBCODE'] ?? '505030101',  // Default jobcode jika kosong
                    'LOCATIONTYPE' => $data['LOCATIONTYPE'] ?? null,
                    'LOCATIONCODE' => $data['LOCATIONCODE'] ?? null,
                    'MANDAYS' => $data['MANDAYS'] ?? null,
                    'OTHRS' => $data['OTHRS'] ?? null,
                    'RATE' => $data['RATE'] ?? null,
                    'UNIT' => $data['UNIT'] ?? null,
                    'OUTPUT' => $data['OUTPUT'] ?? null,
                    'REFERENCE' => $data['REFERENCE'] ?? null,
                    'REMARKS' => $data['REMARKS'] ?? null,
                    'FCENTRY' => $data['FCENTRY'] ?? null,
                    'FCEDIT' => $data['FCEDIT'] ?? null,
                    'FCIP' => $data['FCIP'] ?? null,
                    'FCBA' => $data['FCBA'] ?? null,
                    'LASTUPDATE' => $currentDateTime,
                    'LASTTIME' => $currentTime,
                    'LINENOKEY' => $newLinenoKey2,
                    'OVERTIME_HOURS' => $data['OVERTIME_HOURS'] ?? null,
                    'TYPE_OVERTIME' => $data['TYPE_OVERTIME'] ?? null,
                    'CHARGEJOB' => $data['CHARGEJOB'] ?? null,
                    'CHARGETYPE' => $data['CHARGETYPE'] ?? null,
                    'CHARGECODE' => $data['CHARGECODE'] ?? null,
                    'JANJANG' => $data['JANJANG'] ?? null,
                    'ROWSTATE' => $data['ROWSTATE'] ?? null,
                    'DOCUMENT_CLASSIFICATION' => $data['DOCUMENT_CLASSIFICATION'] ?? null,
                    'GENERATE' => $data['GENERATE'] ?? null,
                    'GENERATETIME' => $currentDateTime,
                    'BASIS_BM' => $data['BASIS_BM'] ?? null,
                    'KG_JANJANG' => $data['KG_JANJANG'] ?? null,
                    'BJR' => $data['BJR'] ?? null,
                    'DOCUMENTNO' => $data['DOCUMENTNO'] ?? null,
                ];
                DB::connection('oracle')->insert($sql2, $params2);
                $inserted[] = $data['DOCUMENTNO'] ?? null;
            }

            return new AllResource(true, 'Data Karyawan berhasil ditambahkan.', $inserted);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.',
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
