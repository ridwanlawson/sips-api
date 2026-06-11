<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\AllResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
            $datas = $request->input("data");
            if (!$datas || !is_array($datas)) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Data tidak valid atau kosong.",
                    ],
                    400,
                );
            }

            // Gunakan UUID atau timestamp-based ID untuk mencegah race condition
            // Baca dari database dengan locking untuk generate LINENOKEY yang unique
            $inserted = [];
            $currentDateTime = now(); // Current timestamp
            $currentTime = $currentDateTime->format("H:i"); // Format HH:MM

            foreach ($datas as $r_data) {
                // Normalisasi keys dari snake_case ke UPPERCASE
                $data = array_change_key_case($r_data, CASE_UPPER);

                try {
                    // Generate unique LINENOKEY menggunakan timestamp + microseconds + random
                    // Ini lebih aman daripada hanya MAX() karena mencegah race condition
                    $newLinenoKey1 = intval(
                        microtime(true) * 10000 + rand(1, 99),
                    );
                    $newLinenoKey2 = intval(
                        microtime(true) * 10000 + rand(1, 99),
                    );

                    // Pastikan tidak duplicate dengan cek di database
                    $maxAttempts = 5;
                    $attempt = 0;
                    while ($attempt < $maxAttempts) {
                        $check1 = DB::connection("oracle")->selectOne(
                            "SELECT COUNT(*) as cnt FROM IPLASPROD.ATTENDANCE_GAD WHERE LINENOKEY = ?",
                            [$newLinenoKey1],
                        );
                        if ($check1->cnt == 0) {
                            break;
                        }
                        $newLinenoKey1 = intval(
                            microtime(true) * 10000 + rand(1, 99),
                        );
                        $attempt++;
                    }

                    if ($attempt >= $maxAttempts) {
                        // Jika masih conflict, gunakan sequence atau fallback
                        $seqResult = DB::connection("oracle")->selectOne(
                            "SELECT IPLASPROD.SEQ_LINENOKEY.NEXTVAL as seq_val FROM DUAL",
                        );
                        $newLinenoKey1 =
                            $seqResult->seq_val ??
                            intval(microtime(true) * 10000 + rand(100, 999));
                    }

                    $attempt = 0;
                    while ($attempt < $maxAttempts) {
                        $check2 = DB::connection("oracle")->selectOne(
                            "SELECT COUNT(*) as cnt FROM IPLASPROD.ATTENDANCE_GAD_TEMP WHERE LINENOKEY = ?",
                            [$newLinenoKey2],
                        );
                        if ($check2->cnt == 0) {
                            break;
                        }
                        $newLinenoKey2 = intval(
                            microtime(true) * 10000 + rand(1, 99),
                        );
                        $attempt++;
                    }

                    // Insert ke ATTENDANCE_GAD dengan SOURCETIME
                    $sql1 = "INSERT INTO IPLASPROD.ATTENDANCE_GAD (
                    GANGCODE, FDDATE, SUPERVISION_1, SUPERVISION_2, SUPERVISION_3, SUPERVISION_4, SUPERVISION_5, EMPLOYEECODE, ATTENDANCE, JOBCODE, LOCATIONTYPE, LOCATIONCODE, MANDAYS, OTHRS, RATE, UNIT, OUTPUT, REFERENCE, REMARKS, FCENTRY, FCEDIT, FCIP, FCBA, LASTUPDATE, LASTTIME, LINENOKEY, OVERTIME_HOURS, TYPE_OVERTIME, CHARGEJOB, CHARGETYPE, CHARGECODE, BUCKET, SPBNO, KG_JANJANG, KG_BRONDOLAN, ROWSTATE, DOCUMENT_CLASSIFICATION, BASIS_BM, BJR, DOCUMENTNO
                ) VALUES (
                    :GANGCODE, :FDDATE, :SUPERVISION_1, :SUPERVISION_2, :SUPERVISION_3, :SUPERVISION_4, :SUPERVISION_5, :EMPLOYEECODE, :ATTENDANCE, :JOBCODE, :LOCATIONTYPE, :LOCATIONCODE, :MANDAYS, :OTHRS, :RATE, :UNIT, :OUTPUT, :REFERENCE, :REMARKS, :FCENTRY, :FCEDIT, :FCIP, :FCBA, :LASTUPDATE, :LASTTIME, :LINENOKEY, :OVERTIME_HOURS, :TYPE_OVERTIME, :CHARGEJOB, :CHARGETYPE, :CHARGECODE, :BUCKET, :SPBNO, :KG_JANJANG, :KG_BRONDOLAN, :ROWSTATE, :DOCUMENT_CLASSIFICATION, :BASIS_BM, :BJR, :DOCUMENTNO
                )";
                    $params1 = [
                        "GANGCODE" => $data["GANGCODE"] ?? null,
                        "FDDATE" => $data["FDDATE"] ?? null,
                        "SUPERVISION_1" => $data["SUPERVISION_1"] ?? null,
                        "SUPERVISION_2" => $data["SUPERVISION_2"] ?? null,
                        "SUPERVISION_3" => $data["SUPERVISION_3"] ?? null,
                        "SUPERVISION_4" => $data["SUPERVISION_4"] ?? null,
                        "SUPERVISION_5" => $data["SUPERVISION_5"] ?? null,
                        "EMPLOYEECODE" => $data["EMPLOYEECODE"] ?? null,
                        "ATTENDANCE" => $data["ATTENDANCE"] ?? 0, // Default 0 untuk mandatory field
                        "JOBCODE" => $data["JOBCODE"] ?? "505030101", // Default jobcode jika kosong
                        "LOCATIONTYPE" => $data["LOCATIONTYPE"] ?? null,
                        "LOCATIONCODE" => $data["LOCATIONCODE"] ?? null,
                        "MANDAYS" => $data["MANDAYS"] ?? 0, // Default 0 untuk numeric field
                        "OTHRS" => $data["OTHRS"] ?? 0,
                        "RATE" => $data["RATE"] ?? 0,
                        "UNIT" => $data["UNIT"] ?? null,
                        "OUTPUT" => $data["OUTPUT"] ?? 0,
                        "REFERENCE" => $data["REFERENCE"] ?? null,
                        "REMARKS" => $data["REMARKS"] ?? null,
                        "FCENTRY" => $data["FCENTRY"] ?? "SYSTEM", // Default system
                        "FCEDIT" => $data["FCEDIT"] ?? "SYSTEM",
                        "FCIP" => $data["FCIP"] ?? "0.0.0.0",
                        "FCBA" => $data["FCBA"] ?? null,
                        "LASTUPDATE" => $currentDateTime,
                        "LASTTIME" => $currentTime,
                        "LINENOKEY" => $newLinenoKey1,
                        "OVERTIME_HOURS" => $data["OVERTIME_HOURS"] ?? 0,
                        "TYPE_OVERTIME" => $data["TYPE_OVERTIME"] ?? null,
                        "CHARGEJOB" => $data["CHARGEJOB"] ?? null,
                        "CHARGETYPE" => $data["CHARGETYPE"] ?? null,
                        "CHARGECODE" => $data["CHARGECODE"] ?? null,
                        "BUCKET" => $data["BUCKET"] ?? null,
                        "SPBNO" => $data["SPBNO"] ?? null,
                        "KG_JANJANG" => $data["KG_JANJANG"] ?? 0,
                        "KG_BRONDOLAN" => $data["KG_BRONDOLAN"] ?? 0,
                        "ROWSTATE" => $data["ROWSTATE"] ?? null,
                        "DOCUMENT_CLASSIFICATION" =>
                            $data["DOCUMENT_CLASSIFICATION"] ?? null,
                        "BASIS_BM" => $data["BASIS_BM"] ?? 0,
                        "BJR" => $data["BJR"] ?? 0,
                        "DOCUMENTNO" => $data["DOCUMENTNO"] ?? null,
                    ];
                    DB::connection("oracle")->insert($sql1, $params1);

                    // Insert ke ATTENDANCE_GAD_TEMP dengan SOURCETIME
                    $sql2 = "INSERT INTO IPLASPROD.ATTENDANCE_GAD_TEMP (
                    GANGCODE, FDDATE, SUPERVISION_1, SUPERVISION_2, SUPERVISION_3, SUPERVISION_4, SUPERVISION_5, EMPLOYEECODE, ATTENDANCE, JOBCODE, LOCATIONTYPE, LOCATIONCODE, MANDAYS, OTHRS, RATE, UNIT, OUTPUT, REFERENCE, REMARKS, FCENTRY, FCEDIT, FCIP, FCBA, LASTUPDATE, LASTTIME, LINENOKEY, OVERTIME_HOURS, TYPE_OVERTIME, CHARGEJOB, CHARGETYPE, CHARGECODE, JANJANG, ROWSTATE, DOCUMENT_CLASSIFICATION, GENERATE, GENERATETIME, BASIS_BM, KG_JANJANG, BJR, DOCUMENTNO
                ) VALUES (
                    :GANGCODE, :FDDATE, :SUPERVISION_1, :SUPERVISION_2, :SUPERVISION_3, :SUPERVISION_4, :SUPERVISION_5, :EMPLOYEECODE, :ATTENDANCE, :JOBCODE, :LOCATIONTYPE, :LOCATIONCODE, :MANDAYS, :OTHRS, :RATE, :UNIT, :OUTPUT, :REFERENCE, :REMARKS, :FCENTRY, :FCEDIT, :FCIP, :FCBA, :LASTUPDATE, :LASTTIME, :LINENOKEY, :OVERTIME_HOURS, :TYPE_OVERTIME, :CHARGEJOB, :CHARGETYPE, :CHARGECODE, :JANJANG, :ROWSTATE, :DOCUMENT_CLASSIFICATION, :GENERATE, :GENERATETIME, :BASIS_BM, :KG_JANJANG, :BJR, :DOCUMENTNO
                )";
                    $params2 = [
                        "GANGCODE" => $data["GANGCODE"] ?? null,
                        "FDDATE" => $data["FDDATE"] ?? null,
                        "SUPERVISION_1" => $data["SUPERVISION_1"] ?? null,
                        "SUPERVISION_2" => $data["SUPERVISION_2"] ?? null,
                        "SUPERVISION_3" => $data["SUPERVISION_3"] ?? null,
                        "SUPERVISION_4" => $data["SUPERVISION_4"] ?? null,
                        "SUPERVISION_5" => $data["SUPERVISION_5"] ?? null,
                        "EMPLOYEECODE" => $data["EMPLOYEECODE"] ?? null,
                        "ATTENDANCE" => $data["ATTENDANCE"] ?? 0, // Default 0 untuk mandatory field
                        "JOBCODE" => $data["JOBCODE"] ?? "505030101", // Default jobcode jika kosong
                        "LOCATIONTYPE" => $data["LOCATIONTYPE"] ?? null,
                        "LOCATIONCODE" => $data["LOCATIONCODE"] ?? null,
                        "MANDAYS" => $data["MANDAYS"] ?? 0, // Default 0 untuk numeric field
                        "OTHRS" => $data["OTHRS"] ?? 0,
                        "RATE" => $data["RATE"] ?? 0,
                        "UNIT" => $data["UNIT"] ?? null,
                        "OUTPUT" => $data["OUTPUT"] ?? 0,
                        "REFERENCE" => $data["REFERENCE"] ?? null,
                        "REMARKS" => $data["REMARKS"] ?? null,
                        "FCENTRY" => $data["FCENTRY"] ?? "SYSTEM", // Default system
                        "FCEDIT" => $data["FCEDIT"] ?? "SYSTEM",
                        "FCIP" => $data["FCIP"] ?? "0.0.0.0",
                        "FCBA" => $data["FCBA"] ?? null,
                        "LASTUPDATE" => $currentDateTime,
                        "LASTTIME" => $currentTime,
                        "LINENOKEY" => $newLinenoKey2,
                        "OVERTIME_HOURS" => $data["OVERTIME_HOURS"] ?? 0,
                        "TYPE_OVERTIME" => $data["TYPE_OVERTIME"] ?? null,
                        "CHARGEJOB" => $data["CHARGEJOB"] ?? null,
                        "CHARGETYPE" => $data["CHARGETYPE"] ?? null,
                        "CHARGECODE" => $data["CHARGECODE"] ?? null,
                        "JANJANG" => $data["JANJANG"] ?? 0, // Default 0 untuk numeric field
                        "ROWSTATE" => $data["ROWSTATE"] ?? null,
                        "DOCUMENT_CLASSIFICATION" =>
                            $data["DOCUMENT_CLASSIFICATION"] ?? null,
                        "GENERATE" => $data["GENERATE"] ?? "SIPS MOBILE",
                        "GENERATETIME" => $currentDateTime,
                        "BASIS_BM" => $data["BASIS_BM"] ?? 0,
                        "KG_JANJANG" => $data["KG_JANJANG"] ?? 0,
                        "BJR" => $data["BJR"] ?? 0,
                        "DOCUMENTNO" => $data["DOCUMENTNO"] ?? null,
                    ];
                    DB::connection("oracle")->insert($sql2, $params2);
                    $inserted[] = $data["DOCUMENTNO"] ?? null;
                } catch (\Exception $e) {
                    // Log error untuk record ini tapi lanjut ke record berikutnya
                    Log::error(
                        "Error inserting attendance record: " .
                            $data["DOCUMENTNO"] .
                            " - " .
                            $e->getMessage(),
                    );
                    continue;
                }
            }

            return new AllResource(
                true,
                "Data Karyawan berhasil ditambahkan.",
                $inserted,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "Terjadi kesalahan saat menyimpan data. Silakan coba lagi.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Upload Harvesting SPB ke database SIPS.
     *
     * Endpoint ini digunakan untuk mengirim data harvesting SPB dari SIPS Mobile ke tabel IPLASPROD.HARVESTINGSPB.
     * Data harus dikirim sebagai array dengan struktur sesuai format GET /report/upload-harvesting.
     *
     * @bodyParam data array required Array data harvesting SPB yang akan diinsert ke tabel HARVESTINGSPB.
     * @bodyParam data[].spbno string required No SPB. Example: SPB2026004804
     * @bodyParam data[].fieldcode string required Kode field. Example: M06
     * @bodyParam data[].receptiondate string required Tanggal penerimaan (format: YYYY-MM-DD HH:MM:SS). Example: 2026-02-08 00:00:00
     * @bodyParam data[].harvestdate string required Tanggal panen (format: YYYY-MM-DD HH:MM:SS). Example: 2026-02-08 00:00:00
     * @bodyParam data[].cropcode string required Kode tanaman. Example: OP
     * @bodyParam data[].productcode string required Kode produk. Example: TBS
     * @bodyParam data[].own string required Tipe kepemilikan. Example: OWN
     * @bodyParam data[].vehicle string required Kode kendaraan. Example: L9770CL
     * @bodyParam data[].driver string required Nama driver. Example: HENDRA
     * @bodyParam data[].mill string required Pabrik tujuan. Example: DOM
     * @bodyParam data[].agreementcode string optional Kode agreement. Example:
     * @bodyParam data[].transporttype string required Tipe transportasi. Example: DIRECTTRANSPORT
     * @bodyParam data[].spb_type integer required Tipe SPB. Example: 0
     * @bodyParam data[].bunch float required Jumlah bunch. Example: 127
     * @bodyParam data[].bucket float optional Jumlah bucket. Example:
     * @bodyParam data[].pressemester_abw float required Press semester ABW. Example: 11.19
     * @bodyParam data[].bunch_estateweight float required Berat bunch estate. Example: 1421
     * @bodyParam data[].fcentry string required Dibuat oleh. Example: PTE_PRODUKSI
     * @bodyParam data[].fcedit string required Diubah oleh. Example: PTE_PRODUKSI
     * @bodyParam data[].fcip string required IP address. Example: 114.10.139.104
     * @bodyParam data[].fcba string required Kode FCBA. Example: PTE
     * @bodyParam data[].chitno string required Weighbridge Chit Number. Example: TBS2026004804
     * @bodyParam data[].mill_weight_bruto float required Berat bruto pabrik (kg). Example: 10090
     * @bodyParam data[].mill_weight_gross float required Berat gross pabrik (kg). Example: 5920
     * @bodyParam data[].mill_weight_tarra float required Berat tarra pabrik (kg). Example: 4170
     * @bodyParam data[].mill_weight_potongan float required Berat potongan pabrik (kg). Example: 295.92
     * @bodyParam data[].mill_weight_netto float required Berat netto pabrik (kg). Example: 5624.08
     * @bodyParam data[].mentah string optional Mentah. Example:
     * @bodyParam data[].tankos string optional Tankos. Example:
     * @bodyParam data[].hilang string optional Hilang. Example:
     * @bodyParam data[].keterangan string optional Keterangan. Example:
     * @bodyParam data[].mill_weight_dtl float required Detail berat pabrik (kg). Example: 1173.27
     * @bodyParam data[].bjr_chit float required BJR per Chit. Example: 9.24
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data Harvesting SPB berhasil ditambahkan.",
     *  "data": ["SPB2026004804", "SPB2026004769", "SPB2026004781"]
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
    public function harvesting(Request $request)
    {
        try {
            // Ambil data dari request (diasumsikan array of records)
            $datas = $request->input("data");
            if (!$datas || !is_array($datas)) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Data tidak valid atau kosong.",
                    ],
                    400,
                );
            }

            $inserted = [];
            $currentDateTime = now(); // Current timestamp
            $currentTime = $currentDateTime->format("H:i"); // Format HH:MM

            foreach ($datas as $r_data) {
                // Normalisasi keys dari snake_case ke UPPERCASE
                $data = array_change_key_case($r_data, CASE_UPPER);

                // Insert ke HARVESTINGSPB
                $sql = "INSERT INTO IPLASPROD.HARVESTINGSPB (
                    SPBNO, FIELDCODE, RECEPTIONDATE, HARVESTDATE, CROPCODE, PRODUCTCODE, OWN, VEHICLE, DRIVER, MILL, AGREEMENTCODE, TRANSPORTTYPE, SPB_TYPE, BUNCH, BUCKET, PRESSEMESTER_ABW, BUNCH_ESTATEWEIGHT, FCENTRY, FCEDIT, FCIP, FCBA, LASTUPDATE, LASTTIME, CHITNO, MILL_WEIGHT_BRUTO, MILL_WEIGHT_GROSS, MILL_WEIGHT_TARRA, MILL_WEIGHT_POTONGAN, MILL_WEIGHT_NETTO, MENTAH, TANKOS, HILANG, KETERANGAN, MILL_WEIGHT_DTL, BJR_CHIT
                ) VALUES (
                    :SPBNO, :FIELDCODE, :RECEPTIONDATE, :HARVESTDATE, :CROPCODE, :PRODUCTCODE, :OWN, :VEHICLE, :DRIVER, :MILL, :AGREEMENTCODE, :TRANSPORTTYPE, :SPB_TYPE, :BUNCH, :BUCKET, :PRESSEMESTER_ABW, :BUNCH_ESTATEWEIGHT, :FCENTRY, :FCEDIT, :FCIP, :FCBA, :LASTUPDATE, :LASTTIME, :CHITNO, :MILL_WEIGHT_BRUTO, :MILL_WEIGHT_GROSS, :MILL_WEIGHT_TARRA, :MILL_WEIGHT_POTONGAN, :MILL_WEIGHT_NETTO, :MENTAH, :TANKOS, :HILANG, :KETERANGAN, :MILL_WEIGHT_DTL, :BJR_CHIT
                )";

                $params = [
                    "SPBNO" => $data["SPBNO"] ?? null,
                    "FIELDCODE" => $data["FIELDCODE"] ?? null,
                    "RECEPTIONDATE" => $data["RECEPTIONDATE"] ?? null,
                    "HARVESTDATE" => $data["HARVESTDATE"] ?? null,
                    "CROPCODE" => $data["CROPCODE"] ?? null,
                    "PRODUCTCODE" => $data["PRODUCTCODE"] ?? "TBS",
                    "OWN" => $data["OWN"] ?? "OWN",
                    "VEHICLE" => $data["VEHICLE"] ?? null,
                    "DRIVER" => $data["DRIVER"] ?? null,
                    "MILL" => $data["MILL"] ?? null,
                    "AGREEMENTCODE" => $data["AGREEMENTCODE"] ?? null,
                    "TRANSPORTTYPE" =>
                        $data["TRANSPORTTYPE"] ?? "DIRECTTRANSPORT",
                    "SPB_TYPE" => $data["SPB_TYPE"] ?? 0,
                    "BUNCH" => $data["BUNCH"] ?? null,
                    "BUCKET" => $data["BUCKET"] ?? null,
                    "PRESSEMESTER_ABW" => $data["PRESSEMESTER_ABW"] ?? null,
                    "BUNCH_ESTATEWEIGHT" => $data["BUNCH_ESTATEWEIGHT"] ?? null,
                    "FCENTRY" => $data["FCENTRY"] ?? null,
                    "FCEDIT" => $data["FCEDIT"] ?? null,
                    "FCIP" => $data["FCIP"] ?? null,
                    "FCBA" => $data["FCBA"] ?? null,
                    "LASTUPDATE" => $currentDateTime,
                    "LASTTIME" => $currentTime,
                    "CHITNO" => $data["CHITNO"] ?? null,
                    "MILL_WEIGHT_BRUTO" => $data["MILL_WEIGHT_BRUTO"] ?? null,
                    "MILL_WEIGHT_GROSS" => $data["MILL_WEIGHT_GROSS"] ?? null,
                    "MILL_WEIGHT_TARRA" => $data["MILL_WEIGHT_TARRA"] ?? null,
                    "MILL_WEIGHT_POTONGAN" =>
                        $data["MILL_WEIGHT_POTONGAN"] ?? null,
                    "MILL_WEIGHT_NETTO" => $data["MILL_WEIGHT_NETTO"] ?? null,
                    "MENTAH" => $data["MENTAH"] ?? null,
                    "TANKOS" => $data["TANKOS"] ?? null,
                    "HILANG" => $data["HILANG"] ?? null,
                    "KETERANGAN" => $data["KETERANGAN"] ?? "SIPSMOBILE",
                    "MILL_WEIGHT_DTL" => $data["MILL_WEIGHT_DTL"] ?? null,
                    "BJR_CHIT" => $data["BJR_CHIT"] ?? null,
                ];

                DB::connection("oracle")->insert($sql, $params);
                $inserted[] = $data["SPBNO"] ?? null;
            }

            return new AllResource(
                true,
                "Data Harvesting SPB berhasil ditambahkan.",
                $inserted,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "Terjadi kesalahan saat menyimpan data. Silakan coba lagi.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Upload Harvesting Quality ke database SIPS.
     *
     * Endpoint ini digunakan untuk mengirim data harvesting quality dari SIPS Mobile ke tabel IPLASPROD.HARVESTINGQUALITY.
     * Data harus dikirim sebagai array dengan struktur sesuai format GET /report/upload-harvesting-quality.
     *
     * @bodyParam data array required Array data harvesting quality yang akan diinsert ke tabel HARVESTINGQUALITY.
     * @bodyParam data[].empcode string required Kode karyawan panen. Example: 06-000223-230221-0323
     * @bodyParam data[].fddate string required Tanggal panen (format: YYYY-MM-DD). Example: 2025-11-05
     * @bodyParam data[].fieldcode string required Kode blok/field. Example: I43
     * @bodyParam data[].under_ripe integer required Buah mentah. Example: 1
     * @bodyParam data[].over_ripe integer required Buah overripe. Example: 1
     * @bodyParam data[].abnormal integer required Buah abnormal. Example: 1
     * @bodyParam data[].long_stalk integer required Tangkai panjang. Example: 1
     * @bodyParam data[].eaten_by_rat integer required Dimakan tikus. Example: 0
     * @bodyParam data[].unharvest_ffb integer required FFB tidak dipanen. Example: 1
     * @bodyParam data[].uncollect_lf_circle integer required Pelepah lingkaran tidak dikumpulkan. Example: 0
     * @bodyParam data[].uncollect_lf_piece integer required Pelepah potong tidak dikumpulkan. Example: 0
     * @bodyParam data[].unarrange_ffb integer required FFB tidak tersusun. Example: 0
     * @bodyParam data[].unprune_frond integer required Pelepah tidak dipangkas. Example: 0
     * @bodyParam data[].qe_1 integer required QE 1 - Pelepah tidak disusun. Example: 0
     * @bodyParam data[].qe_2 integer required QE 2 - Buah matahari. Example: 0
     * @bodyParam data[].qe_3 integer required QE 3 - Buah busuk. Example: 1
     * @bodyParam data[].qe_4 integer required QE 4 - Buah mentah diperam. Example: 0
     * @bodyParam data[].qe_5 integer required QE 5 - Over pruning. Example: 0
     * @bodyParam data[].qe_6 integer required QE 6 - Brondolan tidak dialas. Example: 0
     * @bodyParam data[].qe_7 integer required QE 7 - Brondolan kotor sampah. Example: 0
     * @bodyParam data[].qe_8 integer required QE 8 - Buah dibelah. Example: 0
     * @bodyParam data[].qe_9 integer required QE 9. Example: 0
     * @bodyParam data[].qe_10 integer required QE 10. Example: 0
     * @bodyParam data[].fcentry string optional Dibuat oleh. Example:
     * @bodyParam data[].fcedit string optional Diubah oleh. Example:
     * @bodyParam data[].fcip string optional IP address. Example:
     * @bodyParam data[].fcba string required Kode FCBA. Example: MTE
     * @bodyParam data[].qe_11 integer required QE 11 - Buah mentah A1. Example: 0
     * @bodyParam data[].qe_12 integer required QE 12 - Buah tinggal S. Example: 0
     * @bodyParam data[].qe_13 integer required QE 13 - Benggol panjang tidak dipotong. Example: 0
     * @bodyParam data[].qe_14 integer required QE 14. Example: 0
     * @bodyParam data[].qe_15 integer required QE 15. Example: 0
     * @bodyParam data[].qe_16 integer required QE 16 - Buah mentah kerani. Example: 0
     * @bodyParam data[].qe_17 integer required QE 17 - Buah mentah mandor. Example: 0
     * @bodyParam data[].documentno integer required Nomor dokumen. Example: 42
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data Harvesting Quality berhasil ditambahkan.",
     *  "data": ["42", "43", "44"]
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
    public function harvestingquality(Request $request)
    {
        try {
            // Ambil data dari request (diasumsikan array of records)
            $datas = $request->input("data");
            if (!$datas || !is_array($datas)) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Data tidak valid atau kosong.",
                    ],
                    400,
                );
            }

            $inserted = [];
            $currentDateTime = now(); // Current timestamp
            $currentTime = $currentDateTime->format("H:i"); // Format HH:MM

            foreach ($datas as $r_data) {
                // Normalisasi keys dari snake_case ke UPPERCASE
                $data = array_change_key_case($r_data, CASE_UPPER);

                // Insert ke HARVESTINGQUALITY
                $sql = "INSERT INTO IPLASPROD.HARVESTINGQUALITY (
                    EMPCODE, FDDATE, FIELDCODE, UNDER_RIPE, OVER_RIPE, ABNORMAL, LONG_STALK, EATEN_BY_RAT, UNHARVEST_FFB, UNCOLLECT_LF_CIRCLE, UNCOLLECT_LF_PIECE, UNARRANGE_FFB, UNPRUNE_FROND, QE_1, QE_2, QE_3, QE_4, QE_5, QE_6, QE_7, QE_8, QE_9, QE_10, FCENTRY, FCEDIT, FCIP, FCBA, LASTUPDATE, LASTTIME, QE_11, QE_12, QE_13, QE_14, QE_15, QE_16, QE_17, DOCUMENTNO
                ) VALUES (
                    :EMPCODE, :FDDATE, :FIELDCODE, :UNDER_RIPE, :OVER_RIPE, :ABNORMAL, :LONG_STALK, :EATEN_BY_RAT, :UNHARVEST_FFB, :UNCOLLECT_LF_CIRCLE, :UNCOLLECT_LF_PIECE, :UNARRANGE_FFB, :UNPRUNE_FROND, :QE_1, :QE_2, :QE_3, :QE_4, :QE_5, :QE_6, :QE_7, :QE_8, :QE_9, :QE_10, :FCENTRY, :FCEDIT, :FCIP, :FCBA, :LASTUPDATE, :LASTTIME, :QE_11, :QE_12, :QE_13, :QE_14, :QE_15, :QE_16, :QE_17, :DOCUMENTNO
                )";

                $params = [
                    "EMPCODE" => $data["EMPCODE"] ?? null,
                    "FDDATE" => $data["FDDATE"] ?? null,
                    "FIELDCODE" => $data["FIELDCODE"] ?? null,
                    "UNDER_RIPE" => $data["UNDER_RIPE"] ?? 0,
                    "OVER_RIPE" => $data["OVER_RIPE"] ?? 0,
                    "ABNORMAL" => $data["ABNORMAL"] ?? 0,
                    "LONG_STALK" => $data["LONG_STALK"] ?? 0,
                    "EATEN_BY_RAT" => $data["EATEN_BY_RAT"] ?? 0,
                    "UNHARVEST_FFB" => $data["UNHARVEST_FFB"] ?? 0,
                    "UNCOLLECT_LF_CIRCLE" => $data["UNCOLLECT_LF_CIRCLE"] ?? 0,
                    "UNCOLLECT_LF_PIECE" => $data["UNCOLLECT_LF_PIECE"] ?? 0,
                    "UNARRANGE_FFB" => $data["UNARRANGE_FFB"] ?? 0,
                    "UNPRUNE_FROND" => $data["UNPRUNE_FROND"] ?? 0,
                    "QE_1" => $data["QE_1"] ?? 0,
                    "QE_2" => $data["QE_2"] ?? 0,
                    "QE_3" => $data["QE_3"] ?? 0,
                    "QE_4" => $data["QE_4"] ?? 0,
                    "QE_5" => $data["QE_5"] ?? 0,
                    "QE_6" => $data["QE_6"] ?? 0,
                    "QE_7" => $data["QE_7"] ?? 0,
                    "QE_8" => $data["QE_8"] ?? 0,
                    "QE_9" => $data["QE_9"] ?? 0,
                    "QE_10" => $data["QE_10"] ?? 0,
                    "FCENTRY" => $data["FCENTRY"] ?? null,
                    "FCEDIT" => $data["FCEDIT"] ?? null,
                    "FCIP" => $data["FCIP"] ?? null,
                    "FCBA" => $data["FCBA"] ?? null,
                    "LASTUPDATE" => $currentDateTime,
                    "LASTTIME" => $currentTime,
                    "QE_11" => $data["QE_11"] ?? 0,
                    "QE_12" => $data["QE_12"] ?? 0,
                    "QE_13" => $data["QE_13"] ?? 0,
                    "QE_14" => $data["QE_14"] ?? 0,
                    "QE_15" => $data["QE_15"] ?? 0,
                    "QE_16" => $data["QE_16"] ?? 0,
                    "QE_17" => $data["QE_17"] ?? 0,
                    "DOCUMENTNO" => $data["DOCUMENTNO"] ?? null,
                ];

                DB::connection("oracle")->insert($sql, $params);
                $inserted[] = $data["DOCUMENTNO"] ?? null;
            }

            return new AllResource(
                true,
                "Data Harvesting Quality berhasil ditambahkan.",
                $inserted,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "Terjadi kesalahan saat menyimpan data. Silakan coba lagi.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Upload ke SIPS Mobile dari Attendance .
     *
     * Endpoint ini digunakan untuk mengirim data attendance dari SIPS Mobile ke tabel SIPSMOBILE.ATTENDANCE_GAD.
     * Data harus dikirim sebagai array dengan struktur sesuai format GET /report/upload-attendance.
     *
     * @bodyParam data array required Array data attendance yang akan diinsert ke tabel ATTENDANCE_GAD.
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
    public function attendance_mobile(Request $request)
    {
        try {
            // Ambil data dari request (diasumsikan array of records)
            $datas = $request->input("data");
            if (!$datas || !is_array($datas)) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Data tidak valid atau kosong.",
                    ],
                    400,
                );
            }

            // Dapatkan base_max LINENOKEY dari ATTENDANCE_GAD dan ATTENDANCE_GAD_TEMP
            $baseMaxQuery1 =
                "SELECT NVL(MAX(LINENOKEY), 0) AS base_max FROM SIPSMOBILE.ATTENDANCE_GAD";
            $baseMaxResult1 = DB::connection("oracle")->selectOne(
                $baseMaxQuery1,
            );
            $baseMax1 = $baseMaxResult1->base_max ?? 0;

            $inserted = [];
            $currentDateTime = now(); // Current timestamp
            $currentTime = $currentDateTime->format("H:i"); // Format HH:MM

            foreach ($datas as $r_data) {
                try {
                    // Normalisasi keys dari snake_case ke UPPERCASE
                    $data = array_change_key_case($r_data, CASE_UPPER);

                    // Generate unique LINENOKEY menggunakan timestamp + microseconds + random
                    $newLinenoKey1 = intval(
                        microtime(true) * 10000 + rand(1, 99),
                    );

                    // Pastikan tidak duplicate dengan cek di database
                    $maxAttempts = 5;
                    $attempt = 0;
                    while ($attempt < $maxAttempts) {
                        $check1 = DB::connection("oracle")->selectOne(
                            "SELECT COUNT(*) as cnt FROM SIPSMOBILE.ATTENDANCE_GAD WHERE LINENOKEY = ?",
                            [$newLinenoKey1],
                        );
                        if ($check1->cnt == 0) {
                            break;
                        }
                        $newLinenoKey1 = intval(
                            microtime(true) * 10000 + rand(1, 99),
                        );
                        $attempt++;
                    }

                    if ($attempt >= $maxAttempts) {
                        // Jika masih conflict, gunakan sequence atau fallback
                        $seqResult = DB::connection("oracle")->selectOne(
                            "SELECT SIPSMOBILE.SEQ_LINENOKEY.NEXTVAL as seq_val FROM DUAL",
                        );
                        $newLinenoKey1 =
                            $seqResult->seq_val ??
                            intval(microtime(true) * 10000 + rand(100, 999));
                    }

                    // Insert ke ATTENDANCE_GAD dengan SOURCETIME
                    $sql1 = "INSERT INTO SIPSMOBILE.ATTENDANCE_GAD (
                    GANGCODE, FDDATE, SUPERVISION_1, SUPERVISION_2, SUPERVISION_3, SUPERVISION_4, SUPERVISION_5, EMPLOYEECODE, ATTENDANCE, JOBCODE, LOCATIONTYPE, LOCATIONCODE, MANDAYS, OTHRS, RATE, UNIT, OUTPUT, REFERENCE, REMARKS, FCENTRY, FCEDIT, FCIP, FCBA, LASTUPDATE, LASTTIME, LINENOKEY, OVERTIME_HOURS, TYPE_OVERTIME, CHARGEJOB, CHARGETYPE, CHARGECODE, BUCKET, SPBNO, KG_JANJANG, KG_BRONDOLAN, ROWSTATE, DOCUMENT_CLASSIFICATION, BASIS_BM, BJR, DOCUMENTNO, LASTAPPROVAL
                ) VALUES (
                    :GANGCODE, :FDDATE, :SUPERVISION_1, :SUPERVISION_2, :SUPERVISION_3, :SUPERVISION_4, :SUPERVISION_5, :EMPLOYEECODE, :ATTENDANCE, :JOBCODE, :LOCATIONTYPE, :LOCATIONCODE, :MANDAYS, :OTHRS, :RATE, :UNIT, :OUTPUT, :REFERENCE, :REMARKS, :FCENTRY, :FCEDIT, :FCIP, :FCBA, :LASTUPDATE, :LASTTIME, :LINENOKEY, :OVERTIME_HOURS, :TYPE_OVERTIME, :CHARGEJOB, :CHARGETYPE, :CHARGECODE, :BUCKET, :SPBNO, :KG_JANJANG, :KG_BRONDOLAN, :ROWSTATE, :DOCUMENT_CLASSIFICATION, :BASIS_BM, :BJR, :DOCUMENTNO, :LASTAPPROVAL
                )";
                    $params1 = [
                        "GANGCODE" => $data["GANGCODE"] ?? null,
                        "FDDATE" => $data["FDDATE"] ?? null,
                        "SUPERVISION_1" => $data["SUPERVISION_1"] ?? null,
                        "SUPERVISION_2" => $data["SUPERVISION_2"] ?? null,
                        "SUPERVISION_3" => $data["SUPERVISION_3"] ?? null,
                        "SUPERVISION_4" => $data["SUPERVISION_4"] ?? null,
                        "SUPERVISION_5" => $data["SUPERVISION_5"] ?? null,
                        "EMPLOYEECODE" => $data["EMPLOYEECODE"] ?? null,
                        "ATTENDANCE" => $data["ATTENDANCE"] ?? 0, // Default 0 untuk mandatory field
                        "JOBCODE" => $data["JOBCODE"] ?? "505030101", // Default jobcode jika kosong
                        "LOCATIONTYPE" => $data["LOCATIONTYPE"] ?? null,
                        "LOCATIONCODE" => $data["LOCATIONCODE"] ?? null,
                        "MANDAYS" => $data["MANDAYS"] ?? 0, // Default 0 untuk numeric field
                        "OTHRS" => $data["OTHRS"] ?? 0,
                        "RATE" => $data["RATE"] ?? 0,
                        "UNIT" => $data["UNIT"] ?? null,
                        "OUTPUT" => $data["OUTPUT"] ?? 0,
                        "REFERENCE" => $data["REFERENCE"] ?? null,
                        "REMARKS" => $data["REMARKS"] ?? null,
                        "FCENTRY" => $data["FCENTRY"] ?? "SYSTEM", // Default system
                        "FCEDIT" => $data["FCEDIT"] ?? "SYSTEM",
                        "FCIP" => $data["FCIP"] ?? "0.0.0.0",
                        "FCBA" => $data["FCBA"] ?? null,
                        "LASTUPDATE" => $currentDateTime,
                        "LASTTIME" => $currentTime,
                        "LINENOKEY" => $newLinenoKey1,
                        "OVERTIME_HOURS" => $data["OVERTIME_HOURS"] ?? 0,
                        "TYPE_OVERTIME" => $data["TYPE_OVERTIME"] ?? null,
                        "CHARGEJOB" => $data["CHARGEJOB"] ?? null,
                        "CHARGETYPE" => $data["CHARGETYPE"] ?? null,
                        "CHARGECODE" => $data["CHARGECODE"] ?? null,
                        "BUCKET" => $data["BUCKET"] ?? null,
                        "SPBNO" => $data["SPBNO"] ?? null,
                        "KG_JANJANG" => $data["KG_JANJANG"] ?? 0,
                        "KG_BRONDOLAN" => $data["KG_BRONDOLAN"] ?? 0,
                        "ROWSTATE" => $data["ROWSTATE"] ?? null,
                        "DOCUMENT_CLASSIFICATION" =>
                            $data["DOCUMENT_CLASSIFICATION"] ?? null,
                        "BASIS_BM" => $data["BASIS_BM"] ?? 0,
                        "BJR" => $data["BJR"] ?? 0,
                        "DOCUMENTNO" => $data["DOCUMENTNO"] ?? null,
                        "LASTAPPROVAL" =>
                            Auth::user()->username ?? "SIPSMOBILE",
                    ];
                    DB::connection("oracle")->insert($sql1, $params1);
                    $inserted[] = $data["DOCUMENTNO"] ?? null;
                } catch (\Exception $e) {
                    // Log error untuk record ini tapi lanjut ke record berikutnya
                    Log::error(
                        "Error inserting attendance record (SIPSMOBILE): " .
                            $data["DOCUMENTNO"] .
                            " - " .
                            $e->getMessage(),
                    );
                    continue;
                }
            }

            return new AllResource(
                true,
                "Data Karyawan berhasil ditambahkan.",
                $inserted,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "Terjadi kesalahan saat menyimpan data. Silakan coba lagi.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Upload ke SIPS Mobile dari Harvesting SPB .
     *
     * Endpoint ini digunakan untuk mengirim data harvesting SPB dari SIPS Mobile ke tabel SIPSMOBILE.HARVESTINGSPB.
     * Data harus dikirim sebagai array dengan struktur sesuai format GET /report/upload-harvesting.
     *
     * @bodyParam data array required Array data harvesting SPB yang akan diinsert ke tabel HARVESTINGSPB.
     * @bodyParam data[].spbno string required No SPB. Example: SPB2026004804
     * @bodyParam data[].fieldcode string required Kode field. Example: M06
     * @bodyParam data[].receptiondate string required Tanggal penerimaan (format: YYYY-MM-DD HH:MM:SS). Example: 2026-02-08 00:00:00
     * @bodyParam data[].harvestdate string required Tanggal panen (format: YYYY-MM-DD HH:MM:SS). Example: 2026-02-08 00:00:00
     * @bodyParam data[].cropcode string required Kode tanaman. Example: OP
     * @bodyParam data[].productcode string required Kode produk. Example: TBS
     * @bodyParam data[].own string required Tipe kepemilikan. Example: OWN
     * @bodyParam data[].vehicle string required Kode kendaraan. Example: L9770CL
     * @bodyParam data[].driver string required Nama driver. Example: HENDRA
     * @bodyParam data[].mill string required Pabrik tujuan. Example: DOM
     * @bodyParam data[].agreementcode string optional Kode agreement. Example:
     * @bodyParam data[].transporttype string required Tipe transportasi. Example: DIRECTTRANSPORT
     * @bodyParam data[].spb_type integer required Tipe SPB. Example: 0
     * @bodyParam data[].bunch float required Jumlah bunch. Example: 127
     * @bodyParam data[].bucket float optional Jumlah bucket. Example:
     * @bodyParam data[].pressemester_abw float required Press semester ABW. Example: 11.19
     * @bodyParam data[].bunch_estateweight float required Berat bunch estate. Example: 1421
     * @bodyParam data[].fcentry string required Dibuat oleh. Example: PTE_PRODUKSI
     * @bodyParam data[].fcedit string required Diubah oleh. Example: PTE_PRODUKSI
     * @bodyParam data[].fcip string required IP address. Example: 114.10.139.104
     * @bodyParam data[].fcba string required Kode FCBA. Example: PTE
     * @bodyParam data[].chitno string required Weighbridge Chit Number. Example: TBS2026004804
     * @bodyParam data[].mill_weight_bruto float required Berat bruto pabrik (kg). Example: 10090
     * @bodyParam data[].mill_weight_gross float required Berat gross pabrik (kg). Example: 5920
     * @bodyParam data[].mill_weight_tarra float required Berat tarra pabrik (kg). Example: 4170
     * @bodyParam data[].mill_weight_potongan float required Berat potongan pabrik (kg). Example: 295.92
     * @bodyParam data[].mill_weight_netto float required Berat netto pabrik (kg). Example: 5624.08
     * @bodyParam data[].mentah string optional Mentah. Example:
     * @bodyParam data[].tankos string optional Tankos. Example:
     * @bodyParam data[].hilang string optional Hilang. Example:
     * @bodyParam data[].keterangan string optional Keterangan. Example:
     * @bodyParam data[].mill_weight_dtl float required Detail berat pabrik (kg). Example: 1173.27
     * @bodyParam data[].bjr_chit float required BJR per Chit. Example: 9.24
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data Harvesting SPB berhasil ditambahkan.",
     *  "data": ["SPB2026004804", "SPB2026004769", "SPB2026004781"]
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
    public function harvesting_mobile(Request $request)
    {
        try {
            // Ambil data dari request (diasumsikan array of records)
            $datas = $request->input("data");
            if (!$datas || !is_array($datas)) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Data tidak valid atau kosong.",
                    ],
                    400,
                );
            }

            $inserted = [];
            $currentDateTime = now(); // Current timestamp
            $currentTime = $currentDateTime->format("H:i"); // Format HH:MM

            foreach ($datas as $r_data) {
                // Normalisasi keys dari snake_case ke UPPERCASE
                $data = array_change_key_case($r_data, CASE_UPPER);

                // Insert ke HARVESTINGSPB
                $sql = "INSERT INTO SIPSMOBILE.HARVESTINGSPB (
                    SPBNO, FIELDCODE, RECEPTIONDATE, HARVESTDATE, CROPCODE, PRODUCTCODE, OWN, VEHICLE, DRIVER, MILL, AGREEMENTCODE, TRANSPORTTYPE, SPB_TYPE, BUNCH, BUCKET, PRESSEMESTER_ABW, BUNCH_ESTATEWEIGHT, FCENTRY, FCEDIT, FCIP, FCBA, LASTUPDATE, LASTTIME, CHITNO, MILL_WEIGHT_BRUTO, MILL_WEIGHT_GROSS, MILL_WEIGHT_TARRA, MILL_WEIGHT_POTONGAN, MILL_WEIGHT_NETTO, MENTAH, TANKOS, HILANG, KETERANGAN, MILL_WEIGHT_DTL, BJR_CHIT, LASTAPPROVAL
                ) VALUES (
                    :SPBNO, :FIELDCODE, :RECEPTIONDATE, :HARVESTDATE, :CROPCODE, :PRODUCTCODE, :OWN, :VEHICLE, :DRIVER, :MILL, :AGREEMENTCODE, :TRANSPORTTYPE, :SPB_TYPE, :BUNCH, :BUCKET, :PRESSEMESTER_ABW, :BUNCH_ESTATEWEIGHT, :FCENTRY, :FCEDIT, :FCIP, :FCBA, :LASTUPDATE, :LASTTIME, :CHITNO, :MILL_WEIGHT_BRUTO, :MILL_WEIGHT_GROSS, :MILL_WEIGHT_TARRA, :MILL_WEIGHT_POTONGAN, :MILL_WEIGHT_NETTO, :MENTAH, :TANKOS, :HILANG, :KETERANGAN, :MILL_WEIGHT_DTL, :BJR_CHIT, :LASTAPPROVAL
                )";

                $params = [
                    "SPBNO" => $data["SPBNO"] ?? null,
                    "FIELDCODE" => $data["FIELDCODE"] ?? null,
                    "RECEPTIONDATE" => $data["RECEPTIONDATE"] ?? null,
                    "HARVESTDATE" => $data["HARVESTDATE"] ?? null,
                    "CROPCODE" => $data["CROPCODE"] ?? null,
                    "PRODUCTCODE" => $data["PRODUCTCODE"] ?? "TBS",
                    "OWN" => $data["OWN"] ?? "OWN",
                    "VEHICLE" => $data["VEHICLE"] ?? null,
                    "DRIVER" => $data["DRIVER"] ?? null,
                    "MILL" => $data["MILL"] ?? null,
                    "AGREEMENTCODE" => $data["AGREEMENTCODE"] ?? null,
                    "TRANSPORTTYPE" =>
                        $data["TRANSPORTTYPE"] ?? "DIRECTTRANSPORT",
                    "SPB_TYPE" => $data["SPB_TYPE"] ?? 0,
                    "BUNCH" => $data["BUNCH"] ?? null,
                    "BUCKET" => $data["BUCKET"] ?? null,
                    "PRESSEMESTER_ABW" => $data["PRESSEMESTER_ABW"] ?? null,
                    "BUNCH_ESTATEWEIGHT" => $data["BUNCH_ESTATEWEIGHT"] ?? null,
                    "FCENTRY" => $data["FCENTRY"] ?? null,
                    "FCEDIT" => $data["FCEDIT"] ?? null,
                    "FCIP" => $data["FCIP"] ?? null,
                    "FCBA" => $data["FCBA"] ?? null,
                    "LASTUPDATE" => $currentDateTime,
                    "LASTTIME" => $currentTime,
                    "CHITNO" => $data["CHITNO"] ?? null,
                    "MILL_WEIGHT_BRUTO" => $data["MILL_WEIGHT_BRUTO"] ?? null,
                    "MILL_WEIGHT_GROSS" => $data["MILL_WEIGHT_GROSS"] ?? null,
                    "MILL_WEIGHT_TARRA" => $data["MILL_WEIGHT_TARRA"] ?? null,
                    "MILL_WEIGHT_POTONGAN" =>
                        $data["MILL_WEIGHT_POTONGAN"] ?? null,
                    "MILL_WEIGHT_NETTO" => $data["MILL_WEIGHT_NETTO"] ?? null,
                    "MENTAH" => $data["MENTAH"] ?? null,
                    "TANKOS" => $data["TANKOS"] ?? null,
                    "HILANG" => $data["HILANG"] ?? null,
                    "KETERANGAN" => $data["KETERANGAN"] ?? "SIPSMOBILE",
                    "MILL_WEIGHT_DTL" => $data["MILL_WEIGHT_DTL"] ?? null,
                    "BJR_CHIT" => $data["BJR_CHIT"] ?? null,
                    "LASTAPPROVAL" => Auth::user()->username ?? "SIPSMOBILE",
                ];

                DB::connection("oracle")->insert($sql, $params);
                $inserted[] = $data["SPBNO"] ?? null;
            }

            return new AllResource(
                true,
                "Data Harvesting SPB berhasil ditambahkan.",
                $inserted,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "Terjadi kesalahan saat menyimpan data. Silakan coba lagi.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Upload ke SIPS Mobile dari Harvesting Quality.
     *
     * Endpoint ini digunakan untuk mengirim data harvesting quality dari SIPS Mobile ke tabel SIPSMOBILE.HARVESTINGQUALITY.
     * Data harus dikirim sebagai array dengan struktur sesuai format GET /report/upload-harvesting-quality.
     *
     * @bodyParam data array required Array data harvesting quality yang akan diinsert ke tabel HARVESTINGQUALITY.
     * @bodyParam data[].empcode string required Kode karyawan panen. Example: 06-000223-230221-0323
     * @bodyParam data[].fddate string required Tanggal panen (format: YYYY-MM-DD). Example: 2025-11-05
     * @bodyParam data[].fieldcode string required Kode blok/field. Example: I43
     * @bodyParam data[].under_ripe integer required Buah mentah. Example: 1
     * @bodyParam data[].over_ripe integer required Buah overripe. Example: 1
     * @bodyParam data[].abnormal integer required Buah abnormal. Example: 1
     * @bodyParam data[].long_stalk integer required Tangkai panjang. Example: 1
     * @bodyParam data[].eaten_by_rat integer required Dimakan tikus. Example: 0
     * @bodyParam data[].unharvest_ffb integer required FFB tidak dipanen. Example: 1
     * @bodyParam data[].uncollect_lf_circle integer required Pelepah lingkaran tidak dikumpulkan. Example: 0
     * @bodyParam data[].uncollect_lf_piece integer required Pelepah potong tidak dikumpulkan. Example: 0
     * @bodyParam data[].unarrange_ffb integer required FFB tidak tersusun. Example: 0
     * @bodyParam data[].unprune_frond integer required Pelepah tidak dipangkas. Example: 0
     * @bodyParam data[].qe_1 integer required QE 1 - Pelepah tidak disusun. Example: 0
     * @bodyParam data[].qe_2 integer required QE 2 - Buah matahari. Example: 0
     * @bodyParam data[].qe_3 integer required QE 3 - Buah busuk. Example: 1
     * @bodyParam data[].qe_4 integer required QE 4 - Buah mentah diperam. Example: 0
     * @bodyParam data[].qe_5 integer required QE 5 - Over pruning. Example: 0
     * @bodyParam data[].qe_6 integer required QE 6 - Brondolan tidak dialas. Example: 0
     * @bodyParam data[].qe_7 integer required QE 7 - Brondolan kotor sampah. Example: 0
     * @bodyParam data[].qe_8 integer required QE 8 - Buah dibelah. Example: 0
     * @bodyParam data[].qe_9 integer required QE 9. Example: 0
     * @bodyParam data[].qe_10 integer required QE 10. Example: 0
     * @bodyParam data[].fcentry string optional Dibuat oleh. Example:
     * @bodyParam data[].fcedit string optional Diubah oleh. Example:
     * @bodyParam data[].fcip string optional IP address. Example:
     * @bodyParam data[].fcba string required Kode FCBA. Example: MTE
     * @bodyParam data[].qe_11 integer required QE 11 - Buah mentah A1. Example: 0
     * @bodyParam data[].qe_12 integer required QE 12 - Buah tinggal S. Example: 0
     * @bodyParam data[].qe_13 integer required QE 13 - Benggol panjang tidak dipotong. Example: 0
     * @bodyParam data[].qe_14 integer required QE 14. Example: 0
     * @bodyParam data[].qe_15 integer required QE 15. Example: 0
     * @bodyParam data[].qe_16 integer required QE 16 - Buah mentah kerani. Example: 0
     * @bodyParam data[].qe_17 integer required QE 17 - Buah mentah mandor. Example: 0
     * @bodyParam data[].documentno integer required Nomor dokumen. Example: 42
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data Harvesting Quality berhasil ditambahkan.",
     *  "data": ["42", "43", "44"]
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
    public function harvestingquality_mobile(Request $request)
    {
        try {
            // Ambil data dari request (diasumsikan array of records)
            $datas = $request->input("data");
            if (!$datas || !is_array($datas)) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Data tidak valid atau kosong.",
                    ],
                    400,
                );
            }

            $inserted = [];
            $currentDateTime = now(); // Current timestamp
            $currentTime = $currentDateTime->format("H:i"); // Format HH:MM

            foreach ($datas as $r_data) {
                // Normalisasi keys dari snake_case ke UPPERCASE
                $data = array_change_key_case($r_data, CASE_UPPER);

                // Insert ke HARVESTINGQUALITY
                $sql = "INSERT INTO SIPSMOBILE.HARVESTINGQUALITY (
                    EMPCODE, FDDATE, FIELDCODE, UNDER_RIPE, OVER_RIPE, ABNORMAL, LONG_STALK, EATEN_BY_RAT, UNHARVEST_FFB, UNCOLLECT_LF_CIRCLE, UNCOLLECT_LF_PIECE, UNARRANGE_FFB, UNPRUNE_FROND, QE_1, QE_2, QE_3, QE_4, QE_5, QE_6, QE_7, QE_8, QE_9, QE_10, FCENTRY, FCEDIT, FCIP, FCBA, LASTUPDATE, LASTTIME, QE_11, QE_12, QE_13, QE_14, QE_15, QE_16, QE_17, DOCUMENTNO, LASTAPPROVAL
                ) VALUES (
                    :EMPCODE, :FDDATE, :FIELDCODE, :UNDER_RIPE, :OVER_RIPE, :ABNORMAL, :LONG_STALK, :EATEN_BY_RAT, :UNHARVEST_FFB, :UNCOLLECT_LF_CIRCLE, :UNCOLLECT_LF_PIECE, :UNARRANGE_FFB, :UNPRUNE_FROND, :QE_1, :QE_2, :QE_3, :QE_4, :QE_5, :QE_6, :QE_7, :QE_8, :QE_9, :QE_10, :FCENTRY, :FCEDIT, :FCIP, :FCBA, :LASTUPDATE, :LASTTIME, :QE_11, :QE_12, :QE_13, :QE_14, :QE_15, :QE_16, :QE_17, :DOCUMENTNO, :LASTAPPROVAL
                )";

                $params = [
                    "EMPCODE" => $data["EMPCODE"] ?? null,
                    "FDDATE" => $data["FDDATE"] ?? null,
                    "FIELDCODE" => $data["FIELDCODE"] ?? null,
                    "UNDER_RIPE" => $data["UNDER_RIPE"] ?? 0,
                    "OVER_RIPE" => $data["OVER_RIPE"] ?? 0,
                    "ABNORMAL" => $data["ABNORMAL"] ?? 0,
                    "LONG_STALK" => $data["LONG_STALK"] ?? 0,
                    "EATEN_BY_RAT" => $data["EATEN_BY_RAT"] ?? 0,
                    "UNHARVEST_FFB" => $data["UNHARVEST_FFB"] ?? 0,
                    "UNCOLLECT_LF_CIRCLE" => $data["UNCOLLECT_LF_CIRCLE"] ?? 0,
                    "UNCOLLECT_LF_PIECE" => $data["UNCOLLECT_LF_PIECE"] ?? 0,
                    "UNARRANGE_FFB" => $data["UNARRANGE_FFB"] ?? 0,
                    "UNPRUNE_FROND" => $data["UNPRUNE_FROND"] ?? 0,
                    "QE_1" => $data["QE_1"] ?? 0,
                    "QE_2" => $data["QE_2"] ?? 0,
                    "QE_3" => $data["QE_3"] ?? 0,
                    "QE_4" => $data["QE_4"] ?? 0,
                    "QE_5" => $data["QE_5"] ?? 0,
                    "QE_6" => $data["QE_6"] ?? 0,
                    "QE_7" => $data["QE_7"] ?? 0,
                    "QE_8" => $data["QE_8"] ?? 0,
                    "QE_9" => $data["QE_9"] ?? 0,
                    "QE_10" => $data["QE_10"] ?? 0,
                    "FCENTRY" => $data["FCENTRY"] ?? null,
                    "FCEDIT" => $data["FCEDIT"] ?? null,
                    "FCIP" => $data["FCIP"] ?? null,
                    "FCBA" => $data["FCBA"] ?? null,
                    "LASTUPDATE" => $currentDateTime,
                    "LASTTIME" => $currentTime,
                    "QE_11" => $data["QE_11"] ?? 0,
                    "QE_12" => $data["QE_12"] ?? 0,
                    "QE_13" => $data["QE_13"] ?? 0,
                    "QE_14" => $data["QE_14"] ?? 0,
                    "QE_15" => $data["QE_15"] ?? 0,
                    "QE_16" => $data["QE_16"] ?? 0,
                    "QE_17" => $data["QE_17"] ?? 0,
                    "DOCUMENTNO" => $data["DOCUMENTNO"] ?? null,
                    "LASTAPPROVAL" => Auth::user()->username ?? "SIPSMOBILE",
                ];

                DB::connection("oracle")->insert($sql, $params);
                $inserted[] = $data["DOCUMENTNO"] ?? null;
            }

            return new AllResource(
                true,
                "Data Harvesting Quality berhasil ditambahkan.",
                $inserted,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "Terjadi kesalahan saat menyimpan data. Silakan coba lagi.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Approval LHM SIPSMobile.
     *
     * Endpoint ini digunakan untuk melakukan kegiatan approval.
     * Data harus dikirim sebagai array dengan struktur untuk perhitungan upah harian karyawan.
     *
     * @bodyParam data array required Array data LHM yang akan diinsert ke tabel LHM_DATA.
     * @bodyParam data[].ID string required ID Data Absensi (format: YYYY-MM-DD). Example: 123311
     * @bodyParam data[].ROWDATA string required baris data. Example: 1
     * @bodyParam data[].HA string required baris data. Example: 2.7
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data LHM berhasil ditambahkan.",
     *  "data": [1, 2, 3]
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
    public function lhm_data(Request $request)
    {
        try {
            // Ambil data dari request (diasumsikan array of records)
            $datas = $request->input("data");
            if (!$datas || !is_array($datas)) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Data tidak valid atau kosong.",
                    ],
                    400,
                );
            }

            $inserted = [];
            $currentDateTime = now(); // Current timestamp
            $currentTime = $currentDateTime->format("H:i"); // Format HH:MM

            DB::beginTransaction();

            if (Auth::user()->level === "MDP") {
                $conn = DB::connection("oracle");
                $user = Auth::user()->username;

                // 1. Prepare temp table data (ID, ROWDATA, HA)
                $tempRows = [];

                foreach ($datas as $r_data) {
                    $data = array_change_key_case($r_data, CASE_UPPER);

                    $key = $data["ID"] . "|" . $data["ROWDATA"];

                    $tempRows[$key] = [
                        "ID" => (int) $data["ID"],
                        "ROWDATA" => (int) $data["ROWDATA"],
                        "HA" => (float) ($data["HA"] ?? 0),
                    ];
                }

                $tempRows = array_values($tempRows);

                // 2. Insert ke TEMP TABLE (buat TEMP_LHM_INPUT)
                foreach (array_chunk($tempRows, 1000) as $chunk) {
                    $bindings = [];
                    $selects = [];

                    foreach ($chunk as $row) {
                        $selects[] = "SELECT ?, ?, ? FROM dual";
                        $bindings[] = $row["ID"];
                        $bindings[] = $row["ROWDATA"];
                        $bindings[] = $row["HA"];
                    }

                    $sql =
                        "INSERT INTO SIPSMOBILE.TEMP_LHM_INPUT (ID, ROWDATA, HA)
                            " . implode(" UNION ALL ", $selects);

                    $conn->statement($sql, $bindings);
                }

                // 3. INSERT SELECT dari VIEW
                $conn->statement(
                    "  INSERT INTO SIPSMOBILE.LHM_DATA (
                                        ID, ROWDATA, KEMANDORAN, GANGCODE, FDDATE, FCBA, AFDELING, AFDELING_BLOK, EMPLOYEECODE, NAMA, ATTENDANCE, HK,
                                        HECTARAGEPLANTED, TOTALLUASAN, BLOK, TAHUNTANAM, JJG, BRD, HA, MENTAHQTY, MENTAHRP, EMPTYBUNCHQTY, EMPTYBUNCHRP, JUMLAHDENDA,
                                        TOTALALLJJG, BASIS, RPBASIS, PREMILV1, RATE1, RPLV1, PREMILV2, RATE2, RPLV2, PREMILV3, RATE3, RPLV3, TOTALRPPREMI,
                                        KURANGBASIS, HARILIBUR, TOTALBRD, RATE_BRONDOLAN, RPHK, BRD_RP, TOTAL, ATTENDANCE_UPLOAD,
                                        SUPERVISION_1, SUPERVISION_2, SUPERVISION_3, SUPERVISION_4, SUPERVISION_5, JOBCODE, LOCATIONTYPE, LOCATIONCODE, MANDAYS,
                                        OTHRS,RATE,UNIT,OUTPUT,REFERENCE,REMARKS,OVERTIME_HOURS,TYPE_OVERTIME,CHARGEJOB,CHARGETYPE,CHARGECODE,BUCKET,SPBNO,
                                        KG_BRONDOLAN, ROWSTATE, DOCUMENT_CLASSIFICATION, BASIS_BM, KG_JANJANG, BJR, DOCUMENTNO, SOURCETIME, JANJANG, GENERATE, GENERATETIME, FIELDCODE,
                                        FCENTRY, FCEDIT, FCIP, LASTUPDATE, LASTTIME, LASTAPPROVAL
                                    )
                                    SELECT
                                        v.ID,
                                        v.ROWDATA,
                                        v.KEMANDORAN,
                                        v.GANGCODE,
                                        v.FDDATE,
                                        v.FCBA,
                                        v.AFDELING,
                                        v.AFDELING_BLOK,
                                        v.EMPLOYEECODE,
                                        v.NAMA,
                                        v.ATTENDANCE,
                                        v.HK,
                                        v.HECTARAGEPLANTED,
                                        v.TOTALLUASAN,
                                        v.BLOK,
                                        v.TAHUNTANAM,
                                        v.JJG,
                                        v.BRD,
                                        tmp.HA,
                                        v.MENTAHQTY,
                                        v.MENTAHRP,
                                        v.EMPTYBUNCHQTY,
                                        v.EMPTYBUNCHRP,
                                        v.JUMLAHDENDA,
                                        v.TOTALALLJJG,
                                        v.BASIS,
                                        v.RPBASIS,
                                        v.PREMILV1,
                                        v.RATE1,
                                        v.RPLV1,
                                        v.PREMILV2,
                                        v.RATE2,
                                        v.RPLV2,
                                        v.PREMILV3,
                                        v.RATE3,
                                        v.RPLV3,
                                        v.TOTALRPPREMI,
                                        v.KURANGBASIS,
                                        v.HARILIBUR,
                                        v.TOTALBRD,
                                        v.RATE_BRONDOLAN,
                                        v.RPHK,
                                        v.BRD_RP,
                                        v.TOTAL,
                                        v.ATTENDANCE_UPLOAD,
                                        v.SUPERVISION_1,
                                        v.SUPERVISION_2,
                                        v.SUPERVISION_3,
                                        v.SUPERVISION_4,
                                        v.SUPERVISION_5,
                                        v.JOBCODE,
                                        v.LOCATIONTYPE,
                                        v.LOCATIONCODE,
                                        v.MANDAYS,
                                        v.OTHRS,
                                        v.RATE,
                                        v.UNIT,
                                        v.OUTPUT,
                                        v.REFERENCE,
                                        v.REMARKS,
                                        v.OVERTIME_HOURS,
                                        v.TYPE_OVERTIME,
                                        v.CHARGEJOB,
                                        v.CHARGETYPE,
                                        v.CHARGECODE,
                                        v.BUCKET,
                                        v.SPBNO,
                                        v.KG_BRONDOLAN,
                                        v.ROWSTATE,
                                        v.DOCUMENT_CLASSIFICATION,
                                        v.BASIS_BM,
                                        v.KG_JANJANG,
                                        v.BJR,
                                        v.DOCUMENTNO,
                                        v.SOURCETIME,
                                        v.JANJANG,
                                        v.GENERATE,
                                        v.GENERATETIME,
                                        v.FIELDCODE,
                                        v.FCENTRY, v.FCEDIT, v.FCIP, v.LASTUPDATE, v.LASTTIME, ?
                                    FROM SIPSMOBILE.VIEW_LHM v
                                    JOIN SIPSMOBILE.TEMP_LHM_INPUT tmp
                                    ON v.ID = tmp.ID AND v.ROWDATA = tmp.ROWDATA
                                ",
                    [$user],
                );

                $conn->statement("
                    UPDATE SIPSMOBILE.ATTENDANCE a
                    SET a.STATUS_ATTENDANCE = 'Approved'
                    WHERE EXISTS (
                        SELECT 1
                        FROM SIPSMOBILE.LHM_DATA l
                        JOIN SIPSMOBILE.TEMP_LHM_INPUT tmp
                            ON l.ID = tmp.ID AND l.ROWDATA = tmp.ROWDATA
                        WHERE l.ID = a.ID
                    )
                ");

                $conn->statement("
                    UPDATE SIPSMOBILE.HARVESTING h
                    SET h.STATUS_HARVESTING = 'Approved'
                    WHERE EXISTS (
                        SELECT 1
                        FROM SIPSMOBILE.LHM_DATA l
                        JOIN SIPSMOBILE.TEMP_LHM_INPUT tmp
                            ON l.ID = tmp.ID AND l.ROWDATA = tmp.ROWDATA
                        WHERE TRUNC(l.FDDATE) = TRUNC(h.TANGGAL)
                        AND l.EMPLOYEECODE = h.KODE_KARYAWAN
                        AND l.FIELDCODE = h.FIELDCODE
                    )
                ");
            }

            if (Auth::user()->level !== "MDP") {
                $conn = DB::connection("oracle");
                $user = Auth::user()->username;

                // 1. Prepare temp rows (DEDUP)
                $tempRows = [];

                foreach ($datas as $r_data) {
                    $data = array_change_key_case($r_data, CASE_UPPER);

                    $key = $data["ID"] . "|" . $data["ROWDATA"];

                    $tempRows[$key] = [
                        "ID" => (int) $data["ID"],
                        "ROWDATA" => (int) $data["ROWDATA"],
                    ];
                }

                $tempRows = array_values($tempRows);

                // 2. Insert ke TEMP TABLE
                foreach (array_chunk($tempRows, 1000) as $chunk) {
                    $bindings = [];
                    $selects = [];

                    foreach ($chunk as $row) {
                        $selects[] = "SELECT ?, ? FROM dual";
                        $bindings[] = $row["ID"];
                        $bindings[] = $row["ROWDATA"];
                    }

                    $sql =
                        "INSERT INTO SIPSMOBILE.TEMP_LHM_UPDATE (ID, ROWDATA)
                " . implode(" UNION ALL ", $selects);

                    $conn->statement($sql, $bindings);
                }

                // 3. MERGE
                $conn->statement(
                    "
                                    MERGE INTO SIPSMOBILE.LHM_DATA t
                                    USING SIPSMOBILE.TEMP_LHM_UPDATE tmp
                                    ON (t.ID = tmp.ID AND t.ROWDATA = tmp.ROWDATA)
                                    WHEN MATCHED THEN
                                        UPDATE SET
                                            t.LASTAPPROVAL = ?,
                                            t.FCEDIT = ?,
                                            t.LASTUPDATE = SYSDATE
                                ",
                    [$user, $user],
                );
            }

            $checkLastApproval = DB::table("T_LASTAPPROVAL")
                ->where("FCBA", Auth::user()->fcba) // penting kalau ada banyak data
                ->value("CODE"); // langsung ambil 1 kolom

            if (Auth::user()->level === $checkLastApproval) {
                Log::info("LUAR BIASA");
                DB::statement("
                        INSERT ALL
                        INTO IPLASPROD.ATTENDANCE_GAD (
                            GANGCODE, FDDATE, SUPERVISION_1, SUPERVISION_2, SUPERVISION_3, SUPERVISION_4,
                            EMPLOYEECODE, ATTENDANCE, JOBCODE, LOCATIONTYPE, LOCATIONCODE,
                            MANDAYS, OTHRS, RATE, UNIT, OUTPUT,
                            REFERENCE, REMARKS, FCENTRY, FCEDIT, FCIP, FCBA,
                            LASTUPDATE, LASTTIME, LINENOKEY,
                            OVERTIME_HOURS, TYPE_OVERTIME,
                            CHARGEJOB, CHARGETYPE, CHARGECODE,
                            BUCKET, SPBNO,
                            KG_JANJANG, KG_BRONDOLAN,
                            ROWSTATE, DOCUMENT_CLASSIFICATION,
                            BASIS_BM, BJR, DOCUMENTNO, SUPERVISION_5
                        )
                        VALUES (
                            GANGCODE, FDDATE, SUPERVISION_1, SUPERVISION_2, SUPERVISION_3, SUPERVISION_4,
                            EMPLOYEECODE, ATTENDANCE, JOBCODE, LOCATIONTYPE, LOCATIONCODE,
                            MANDAYS, OTHRS, RATE, UNIT, OUTPUT,
                            REFERENCE, REMARKS, FCENTRY, FCEDIT, FCIP, FCBA,
                            LASTUPDATE, LASTTIME, LINENOKEY,
                            OVERTIME_HOURS, TYPE_OVERTIME,
                            CHARGEJOB, CHARGETYPE, CHARGECODE,
                            BUCKET, SPBNO,
                            KG_JANJANG, KG_BRONDOLAN,
                            ROWSTATE, DOCUMENT_CLASSIFICATION,
                            BASIS_BM, BJR, DOCUMENTNO, SUPERVISION_5
                        )
                        INTO IPLASPROD.ATTENDANCE_GAD_TEMP (
                            GANGCODE, FDDATE, SUPERVISION_1, SUPERVISION_2, SUPERVISION_3, SUPERVISION_4,
                            EMPLOYEECODE, ATTENDANCE, JOBCODE, LOCATIONTYPE, LOCATIONCODE,
                            MANDAYS, OTHRS, RATE, UNIT, OUTPUT,
                            REFERENCE, REMARKS, FCENTRY, FCEDIT, FCIP, FCBA,
                            LASTUPDATE, LASTTIME, LINENOKEY,
                            OVERTIME_HOURS, TYPE_OVERTIME,
                            CHARGEJOB, CHARGETYPE, CHARGECODE,
                            JANJANG,
                            ROWSTATE, DOCUMENT_CLASSIFICATION,
                            GENERATE, GENERATETIME,
                            BASIS_BM, KG_JANJANG, BJR, DOCUMENTNO, SUPERVISION_5
                        )
                        VALUES (
                            GANGCODE, FDDATE, SUPERVISION_1, SUPERVISION_2, SUPERVISION_3, SUPERVISION_4,
                            EMPLOYEECODE, ATTENDANCE, JOBCODE, LOCATIONTYPE, LOCATIONCODE,
                            MANDAYS, OTHRS, RATE, UNIT, OUTPUT,
                            REFERENCE, REMARKS, FCENTRY, FCEDIT, FCIP, FCBA,
                            LASTUPDATE, LASTTIME, LINENOKEY,
                            OVERTIME_HOURS, TYPE_OVERTIME,
                            CHARGEJOB, CHARGETYPE, CHARGECODE,
                            0,
                            ROWSTATE, DOCUMENT_CLASSIFICATION,
                            'AUTO GENERATE', SYSDATE,
                            BASIS_BM, KG_JANJANG, BJR, DOCUMENTNO, SUPERVISION_5
                        )
                        SELECT
                            ld.GANGCODE,
                            FDDATE,
                            SUPERVISION_1,
                            SUPERVISION_2,
                            SUPERVISION_3,
                            SUPERVISION_4,
                            SUPERVISION_5,
                            EMPLOYEECODE,
                            ATTENDANCE_UPLOAD ATTENDANCE,
                            JOBCODE,
                            LOCATIONTYPE,
                            LOCATIONCODE,
                            MANDAYS,
                            OTHRS,
                            RATE,
                            UNIT,
                            OUTPUT,
                            REFERENCE,
                            REMARKS,
                            FCENTRY,
                            FCEDIT,
                            FCIP,
                            ld.FCBA,
                            LASTUPDATE,
                            LASTTIME,
                            bm.base_max + ROW_NUMBER() OVER (ORDER BY FDDATE, EMPLOYEECODE) AS LINENOKEY,
                            OVERTIME_HOURS,
                            TYPE_OVERTIME,
                            CHARGEJOB,
                            CHARGETYPE,
                            CHARGECODE,
                            BUCKET,
                            SPBNO,
                            KG_JANJANG,
                            KG_BRONDOLAN,
                            ROWSTATE,
                            DOCUMENT_CLASSIFICATION,
                            BASIS_BM,
                            BJR,
                            DOCUMENTNO,
                            u.\"LEVEL\" USER_LEVEL
                        FROM
                            SIPSMOBILE.LHM_DATA ld
                        JOIN SIPSMOBILE.USERS u
                            ON ld.LASTAPPROVAL = u.USERNAME
                        CROSS JOIN
                            (
                            SELECT
                                NVL(MAX(LINENOKEY), 0) AS base_max
                            FROM
                                IPLASPROD.ATTENDANCE_GAD_TEMP
                            ) bm
                        WHERE
                            NOT EXISTS (SELECT 1 FROM IPLASPROD.ATTENDANCE_GAD_TEMP agt WHERE agt.DOCUMENTNO = ld.ID)
                            AND EXISTS
                                (
                                SELECT
                                *
                                FROM
                                    (
                                        SELECT
                                            d.FCBA,
                                            r.CODE
                                        FROM
                                            (
                                            SELECT
                                                u.FCBA,
                                                MIN(ORDERAPPROVAL) AS ORDERAPPROVAL
                                            FROM
                                                SIPSMOBILE.USERS u
                                            JOIN SIPSMOBILE.ROLES r ON u.\"LEVEL\" = r.CODE
                                            GROUP BY
                                                u.FCBA
                                            ORDER BY
                                                u.FCBA
                                            ) d
                                        JOIN SIPSMOBILE.ROLES r ON r.ORDERAPPROVAL = d.ORDERAPPROVAL AND r.FCBA = d.FCBA
                                    ) DATA
                                WHERE DATA.fcba = ld.fcba AND DATA.code = u.\"LEVEL\"
                                )
                    ");
            }

            Log::info("TIDAK LUAR BIASA " . $checkLastApproval);

            DB::commit();

            return new AllResource(
                true,
                "Data LHM berhasil ditambahkan.",
                $inserted,
            );
        } catch (\Exception $e) {
            Log::error("LHM BULK ERROR", [
                "message" => $e->getMessage(),
                "trace" => $e->getTraceAsString(),
            ]);

            DB::rollBack();

            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "Terjadi kesalahan saat menyimpan data. Silakan coba lagi.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Open LHM SIPSMobile.
     *
     * Endpoint ini digunakan untuk mengirim data LHM (Laporan Harian Mandor) dari SIPS Mobile ke tabel SIPSMOBILE.LHM_DATA.
     * Data harus dikirim sebagai array dengan struktur untuk perhitungan upah harian karyawan.
     *
     * @bodyParam data array required Array data LHM yang akan diopen.
     * @bodyParam data[].ID string required ID Data Absensi (format: YYYY-MM-DD). Example: 123311
     * @bodyParam data[].ROWDATA string required baris data. Example: 1
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data LHM berhasil dibuka.",
     *  "data": [1, 2, 3]
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
    public function open_lhm_data(Request $request)
    {
        try {
            $datas = $request->input("data");

            if (!$datas || !is_array($datas)) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Data tidak valid atau kosong.",
                    ],
                    400,
                );
            }

            DB::beginTransaction();

            $conn = DB::connection("oracle");

            // =============================
            // 1. PREPARE TEMP TABLE
            // =============================
            $tempRows = [];

            foreach ($datas as $r_data) {
                $data = array_change_key_case($r_data, CASE_UPPER);

                $key = $data["ID"] . "|" . $data["ROWDATA"];

                $tempRows[$key] = [
                    "ID" => (int) $data["ID"],
                    "ROWDATA" => (int) $data["ROWDATA"],
                ];
            }

            $tempRows = array_values($tempRows);

            // insert ke TEMP
            foreach (array_chunk($tempRows, 1000) as $chunk) {
                $bindings = [];
                $selects = [];

                foreach ($chunk as $row) {
                    $selects[] = "SELECT ?, ? FROM dual";
                    $bindings[] = $row["ID"];
                    $bindings[] = $row["ROWDATA"];
                }

                $sql =
                    "INSERT INTO SIPSMOBILE.TEMP_LHM_INPUT (ID, ROWDATA)
                        " . implode(" UNION ALL ", $selects);

                $conn->statement($sql, $bindings);
            }

            // =============================
            // 2. BALIKKAN HARVESTING (WAJIB PERTAMA)
            // =============================
            $conn->statement("
                UPDATE SIPSMOBILE.HARVESTING h
                SET h.STATUS_HARVESTING = 'Planned'
                WHERE EXISTS (
                    SELECT 1
                    FROM SIPSMOBILE.LHM_DATA l
                    JOIN SIPSMOBILE.TEMP_LHM_INPUT tmp
                        ON l.ID = tmp.ID AND l.ROWDATA = tmp.ROWDATA
                    WHERE TRUNC(l.FDDATE) = TRUNC(h.TANGGAL)
                    AND l.EMPLOYEECODE = h.KODE_KARYAWAN
                    AND l.FIELDCODE = h.FIELDCODE
                )
            ");

            // =============================
            // 3. BALIKKAN ATTENDANCE
            // =============================
            $conn->statement("
                UPDATE SIPSMOBILE.ATTENDANCE a
                SET a.STATUS_ATTENDANCE = 'Planned'
                WHERE EXISTS (
                    SELECT 1
                    FROM SIPSMOBILE.TEMP_LHM_INPUT tmp
                    WHERE a.ID = tmp.ID
                )
            ");

            // =============================
            // 4. DELETE HARVESTING QUALITY
            // =============================
            $conn->statement("
                DELETE FROM IPLASPROD.HARVESTINGQUALITY hq
                WHERE EXISTS (
                    SELECT 1
                    FROM SIPSMOBILE.TEMP_LHM_INPUT tmp
                    WHERE hq.DOCUMENTNO = tmp.ID
                )
            ");

            // =============================
            // 5. DELETE GAD
            // =============================
            $conn->statement("
                DELETE FROM IPLASPROD.ATTENDANCE_GAD ag
                WHERE EXISTS (
                    SELECT 1
                    FROM SIPSMOBILE.TEMP_LHM_INPUT tmp
                    WHERE ag.DOCUMENTNO = tmp.ID
                )
            ");

            // =============================
            // 6. DELETE GAD TEMP
            // =============================
            $conn->statement("
                DELETE FROM IPLASPROD.ATTENDANCE_GAD_TEMP agt
                WHERE EXISTS (
                    SELECT 1
                    FROM SIPSMOBILE.TEMP_LHM_INPUT tmp
                    WHERE agt.DOCUMENTNO = tmp.ID
                )
            ");

            // =============================
            // 7. DELETE LHM_DATA (TERAKHIR!)
            // =============================
            $conn->statement("
                DELETE FROM SIPSMOBILE.LHM_DATA l
                WHERE EXISTS (
                    SELECT 1
                    FROM SIPSMOBILE.TEMP_LHM_INPUT tmp
                    WHERE l.ID = tmp.ID
                    AND l.ROWDATA = tmp.ROWDATA
                )
            ");

            DB::commit();

            return response()->json([
                "success" => true,
                "message" => "Reverse LHM berhasil.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("REVERSE LHM ERROR", [
                "message" => $e->getMessage(),
            ]);

            return response()->json(
                [
                    "success" => false,
                    "message" => "Gagal reverse data.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    private function formatNumber($value)
    {
        if ($value === null) {
            return null;
        }

        // Paksa ke float agar bisa dibulatkan
        $num = (float) $value;

        // Bulatkan ke 3 digit desimal
        $num = round($num, 3);

        // Konversi ke string tanpa notasi scientific
        $v = number_format($num, 3, ".", "");

        // Hilangkan trailing zero: 1.500 → 1.5 , 10.000 → 10
        $v = rtrim(rtrim($v, "0"), ".");

        // Tambahkan 0 jika mulai dengan titik
        if (str_starts_with($v, ".")) {
            $v = "0" . $v;
        }

        return $v;
    }
}
