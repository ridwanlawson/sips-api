<?php

namespace App\Http\Controllers\Api;

use App\Models\Attendance;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\AllResource;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;
use App\Services\StorageService;

/**
 * @group Apps
 *
 * @subgroup Absensi
 * @subgroupDescription Sub Group untuk Absensi
 *
 */
class AttendanceController extends Controller
{
    use \App\Traits\ImageOptimizerTrait;

    // -------------------------------------------------------------------------
    // INDEX
    // -------------------------------------------------------------------------

    /**
     * Memanggil data Absensi dari SIPS Mobile.
     *
     * @queryParam tanggal string Optional. Format YYYY-MM-DD. Example: 2025-08-17
     * @queryParam tanggal_end string Optional. Format YYYY-MM-DD. Example: 2025-08-20
     * @queryParam kode_karyawan_mandor string Optional. Example: 06-851012-151218-0079
     * @queryParam kode_karyawan string Optional. Example: 06-031014-231025-0438
     * @queryParam fcba string Optional. Example: MTE
     * @queryParam afdeling string Optional. Example: AFD-01
     * @queryParam gang string Optional. Example: PN011
     * @queryParam attendance string Optional. Example: KJ
     * @queryParam status_attendance string Optional. Planned|AuthorizedOnProgress|Approved|Reject. Example: Planned
     * @queryParam attendance_type string Optional. REGULAR|ASSISTENSI. Example: REGULAR
     * @queryParam fcba_destination string Optional. Example: MRE
     * @queryParam section_destination string Optional. Example: AFD-01
     * @queryParam kemandoran string Optional. Example: MD011
     */
    public function index(Request $request)
    {
        try {
            $tanggal = $request->query("tanggal");
            $tanggalEnd = $request->query("tanggal_end");
            $kode_karyawan_mandor = $request->query("kode_karyawan_mandor");
            $kode_karyawan = $request->query("kode_karyawan");
            $fcba = $request->query("fcba");
            $afdeling = $request->query("afdeling");
            $gang = $request->query("gang");
            $attendance = $request->query("attendance");
            $status_attendance = $request->query("status_attendance");
            $fcba_destination = $request->query("fcba_destination");
            $section_destination = $request->query("section_destination");
            $attendance_type = $request->query("attendance_type");
            $kemandoran = $request->query("kemandoran");

            $query = "
                SELECT
                    ATTENDANCE.ID,
                    ATTENDANCE.TANGGAL,
                    ATTENDANCE.TIME_IN,
                    ATTENDANCE.TIME_OUT,
                    ATTENDANCE.LOCATION_IN,
                    ATTENDANCE.LOCATION_OUT,
                    ATTENDANCE.KODE_KARYAWAN_MANDOR,
                    MANDOR.FCNAME NAMAMANDOR,
                    ATTENDANCE.KODE_KARYAWAN,
                    KARYAWAN.FCNAME NAMAKARYAWAN,
                    ATTENDANCE.PENGANCAKAN,
                    ATTENDANCE.TOTAL_LATE_TIME,
                    ATTENDANCE.GO_HOME_EARLY,
                    ATTENDANCE.ATTENDANCE_TYPE,
                    ATTENDANCE.EXCEPTION_CASE,
                    ATTENDANCE.NO_BA_EXCA,
                    ATTENDANCE.FCBA,
                    ATTENDANCE.SECTION,
                    ATTENDANCE.GANG,
                    ATTENDANCE.ATTENDANCE,
                    NVL(
                        TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM
                            TO_CHAR(ATTENDANCE.MANDAYS, 'FM9999990D9999')
                        )),
                        0
                    ) AS MANDAYS,
                    ATTENDANCE.STATUS_ATTENDANCE,
                    ATTENDANCE.FCBA_DESTINATION,
                    ATTENDANCE.SECTION_DESTINATION,
                    ATTENDANCE.KEMANDORAN,
                    ATTENDANCE.IMAGES,
                    ATTENDANCE.ID_DEVICE,
                    ATTENDANCE.MAC_ADDRESS,
                    ATTENDANCE.CREATED_AT,
                    ATTENDANCE.CREATED_BY
                FROM SIPSMOBILE.ATTENDANCE
                INNER JOIN SIPSMOBILE.EMPLOYEE KARYAWAN
                    ON ATTENDANCE.KODE_KARYAWAN = KARYAWAN.FCCODE
                    AND ATTENDANCE.FCBA = KARYAWAN.FCBA
                LEFT JOIN SIPSMOBILE.EMPLOYEE MANDOR
                    ON ATTENDANCE.KODE_KARYAWAN_MANDOR = MANDOR.FCCODE
                    AND ATTENDANCE.FCBA = MANDOR.FCBA
                WHERE ATTENDANCE.DELETED_AT IS NULL
            ";

            $bindings = [];

            if ($tanggal && $tanggalEnd) {
                $startDate = $tanggal <= $tanggalEnd ? $tanggal : $tanggalEnd;
                $endDate = $tanggal <= $tanggalEnd ? $tanggalEnd : $tanggal;
                $query .=
                    " AND TRUNC(ATTENDANCE.TANGGAL) BETWEEN TO_DATE(:tanggal, 'YYYY-MM-DD') AND TO_DATE(:tanggal_end, 'YYYY-MM-DD')";
                $bindings["tanggal"] = $startDate;
                $bindings["tanggal_end"] = $endDate;
            } elseif ($tanggal) {
                $query .=
                    " AND TRUNC(ATTENDANCE.TANGGAL) = TO_DATE(:tanggal, 'YYYY-MM-DD')";
                $bindings["tanggal"] = $tanggal;
            } elseif ($tanggalEnd) {
                $query .=
                    " AND TRUNC(ATTENDANCE.TANGGAL) = TO_DATE(:tanggal_end, 'YYYY-MM-DD')";
                $bindings["tanggal_end"] = $tanggalEnd;
            }

            if ($kode_karyawan_mandor) {
                $query .=
                    " AND ATTENDANCE.KODE_KARYAWAN_MANDOR = :kode_karyawan_mandor";
                $bindings["kode_karyawan_mandor"] = $kode_karyawan_mandor;
            }
            if ($kode_karyawan) {
                $query .= " AND ATTENDANCE.KODE_KARYAWAN = :kode_karyawan";
                $bindings["kode_karyawan"] = $kode_karyawan;
            }
            if ($fcba) {
                $query .= " AND ATTENDANCE.FCBA = :fcba";
                $bindings["fcba"] = $fcba;
            }
            if ($afdeling) {
                $query .= " AND ATTENDANCE.SECTION = :afdeling";
                $bindings["afdeling"] = $afdeling;
            }
            if ($gang) {
                $query .= " AND ATTENDANCE.GANG = :gang";
                $bindings["gang"] = $gang;
            }
            if ($attendance) {
                $query .= " AND ATTENDANCE.ATTENDANCE = :attendance";
                $bindings["attendance"] = $attendance;
            }
            if ($status_attendance) {
                $query .=
                    " AND ATTENDANCE.STATUS_ATTENDANCE = :status_attendance";
                $bindings["status_attendance"] = $status_attendance;
            }
            if ($fcba_destination) {
                $query .=
                    " AND ATTENDANCE.FCBA_DESTINATION = :fcba_destination";
                $bindings["fcba_destination"] = $fcba_destination;
            }
            if ($section_destination) {
                $query .=
                    " AND ATTENDANCE.SECTION_DESTINATION = :section_destination";
                $bindings["section_destination"] = $section_destination;
            }
            if ($attendance_type) {
                $query .= " AND ATTENDANCE.ATTENDANCE_TYPE = :attendance_type";
                $bindings["attendance_type"] = $attendance_type;
            }
            if ($kemandoran) {
                $query .= " AND ATTENDANCE.KEMANDORAN = :kemandoran";
                $bindings["kemandoran"] = $kemandoran;
            }

            $query .= "
                ORDER BY
                    ATTENDANCE.FCBA,
                    ATTENDANCE.TANGGAL DESC,
                    KARYAWAN.SECTIONNAME,
                    ATTENDANCE.KODE_KARYAWAN
            ";

            $datas = DB::connection("oracle")->select($query, $bindings);

            if (empty($datas)) {
                return response()->json(
                    [
                        "success" => true,
                        "message" => "Data tidak ditemukan.",
                        "data" => [],
                    ],
                    404,
                );
            }

            return new AllResource(true, "List Data Absensi", $datas);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Terjadi kesalahan saat mengambil data.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    // -------------------------------------------------------------------------
    // STORE
    // -------------------------------------------------------------------------

    /**
     * Menyimpan data Absensi ke dalam database SIPS Mobile.
     */
    public function store(Request $request)
    {
        $request->validate([
            "tanggal" => "required|date_format:Y-m-d",
            "kode_karyawan_mandor" => "nullable|exists:employee,fccode",
            "kode_karyawan" => "required|string|exists:employee,fccode",
            "time_in" => "required|date_format:Y-m-d H:i:s",
            "time_out" => "nullable|date_format:Y-m-d H:i:s",
            "location_in" => "nullable",
            "location_out" => "nullable",
            "pengancakan" => "nullable",
            "total_late_time" => "nullable|date_format:H:i",
            "go_home_early" => "nullable|date_format:H:i",
            "attendance_type" => "nullable|in:REGULAR,ASSISTENSI",
            "exception_case" => "nullable",
            "no_ba_exca" => "nullable|file|mimes:pdf|max:2048",
            "fcba" => "required|string|exists:employee,fcba",
            "section" => "nullable|exists:employee,sectionname",
            "gang" => "nullable|exists:employee,gangcode",
            "mandays" => "nullable|numeric|max:1",
            "attendance" => "required|string|in:KJ,WH,WS,MK,ML,P1,KB,OT",
            "fcba_destination" => "nullable|exists:employee,fcba",
            "section_destination" => "nullable|exists:employee,sectionname",
            "kemandoran" => "nullable|exists:users,gangcode",
            "id_device" => "nullable",
            "mac_address" => "nullable",
            "images" => "nullable|file|mimes:jpg,jpeg,png|max:2048",
            "created_by" => "nullable",
        ]);

        try {
            $storage = app(StorageService::class);

            $fcbaSlug = Str::slug(strtolower($request->fcba ?? "unknown"));
            $tanggal = $request->tanggal
                ? Carbon::parse($request->tanggal)
                : Carbon::now();
            $datePath = $tanggal->format("Y/m/d");

            // --- IMAGES ---
            $imagePath = null;

            if ($request->hasFile("images")) {
                $folderPath = "file/attendance/images/{$fcbaSlug}/{$datePath}";
                $relativePath = $this->optimizeAndSaveImage(
                    $request->file("images"),
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

            // --- NO_BA_EXCA ---
            $baExcaPath = null;

            if ($request->hasFile("no_ba_exca")) {
                $baFile = $request->file("no_ba_exca");
                $baFileName = time() . "_" . $baFile->getClientOriginalName();
                $relativePath = "file/attendance/files/{$fcbaSlug}/{$datePath}/{$baFileName}";

                $baExcaPath = $storage->storeFile($baFile, $relativePath);
            }

            // --- Cari mandor ---
            $idkode_karyawan_mandor = User::where("STATUS", "Y")
                ->where("FCBA", $request->fcba)
                ->where("AFDELING", $request->section)
                ->where("GANGCODE", $request->kemandoran)
                ->where("LEVEL", "MDP")
                ->value("IDKARYAWAN");

            // --- Simpan ke DB ---
            $datas = Attendance::create([
                "TANGGAL" => $request->tanggal,
                "KODE_KARYAWAN_MANDOR" =>
                    $request->kode_karyawan_mandor ?? $idkode_karyawan_mandor,
                "KODE_KARYAWAN" => $request->kode_karyawan,
                "TIME_IN" => $request->time_in,
                "TIME_OUT" => $request->time_out,
                "LOCATION_IN" => $request->location_in,
                "LOCATION_OUT" => $request->location_out,
                "PENGANCAKAN" => $request->pengancakan,
                "TOTAL_LATE_TIME" => $request->total_late_time,
                "GO_HOME_EARLY" => $request->go_home_early,
                "ATTENDANCE_TYPE" => $request->attendance_type,
                "EXCEPTION_CASE" => $request->exception_case,
                "NO_BA_EXCA" => $baExcaPath,
                "FCBA" => $request->fcba,
                "SECTION" => $request->section,
                "GANG" => $request->gang,
                "MANDAYS" => $request->mandays,
                "ATTENDANCE" => $request->attendance,
                "STATUS_ATTENDANCE" => "Planned",
                "FCBA_DESTINATION" => $request->fcba_destination,
                "SECTION_DESTINATION" => $request->section_destination,
                "KEMANDORAN" => $request->kemandoran,
                "ID_DEVICE" => $request->id_device,
                "MAC_ADDRESS" => $request->mac_address,
                "IMAGES" => $imagePath,
                "CREATED_BY" => Auth::user()->username,
            ]);

            return new AllResource(
                true,
                "Data Absensi berhasil ditambahkan.",
                $datas,
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

    // -------------------------------------------------------------------------
    // SHOW
    // -------------------------------------------------------------------------

    /**
     * Menampilkan data Absensi berdasarkan id.
     *
     * @urlParam id integer required ID Absensi.
     */
    public function show(string $id)
    {
        try {
            $query = "
                SELECT
                    ATTENDANCE.ID,
                    ATTENDANCE.TANGGAL,
                    ATTENDANCE.TIME_IN,
                    ATTENDANCE.TIME_OUT,
                    ATTENDANCE.LOCATION_IN,
                    ATTENDANCE.LOCATION_OUT,
                    ATTENDANCE.KODE_KARYAWAN_MANDOR,
                    MANDOR.FCNAME NAMAMANDOR,
                    ATTENDANCE.KODE_KARYAWAN,
                    KARYAWAN.FCNAME NAMAKARYAWAN,
                    ATTENDANCE.PENGANCAKAN,
                    ATTENDANCE.TOTAL_LATE_TIME,
                    ATTENDANCE.GO_HOME_EARLY,
                    ATTENDANCE.ATTENDANCE_TYPE,
                    ATTENDANCE.EXCEPTION_CASE,
                    ATTENDANCE.NO_BA_EXCA,
                    ATTENDANCE.FCBA,
                    ATTENDANCE.SECTION,
                    ATTENDANCE.GANG,
                    ATTENDANCE.ATTENDANCE,
                    NVL(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM
                        TO_CHAR(ATTENDANCE.MANDAYS, 'FM9999990D9999')
                    )), 0) AS MANDAYS,
                    ATTENDANCE.STATUS_ATTENDANCE,
                    ATTENDANCE.FCBA_DESTINATION,
                    ATTENDANCE.SECTION_DESTINATION,
                    ATTENDANCE.KEMANDORAN,
                    ATTENDANCE.IMAGES,
                    ATTENDANCE.ID_DEVICE,
                    ATTENDANCE.MAC_ADDRESS,
                    ATTENDANCE.CREATED_AT,
                    ATTENDANCE.CREATED_BY
                FROM SIPSMOBILE.ATTENDANCE
                INNER JOIN SIPSMOBILE.EMPLOYEE KARYAWAN
                    ON ATTENDANCE.KODE_KARYAWAN = KARYAWAN.FCCODE
                    AND ATTENDANCE.FCBA = KARYAWAN.FCBA
                LEFT JOIN SIPSMOBILE.EMPLOYEE MANDOR
                    ON ATTENDANCE.KODE_KARYAWAN_MANDOR = MANDOR.FCCODE
                    AND ATTENDANCE.FCBA = MANDOR.FCBA
                WHERE ATTENDANCE.ID = :id
            ";

            $data = DB::connection("oracle")->selectOne($query, ["id" => $id]);

            if (!$data) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Data Absensi tidak ditemukan.",
                    ],
                    404,
                );
            }

            return new AllResource(true, "Detail Data Absensi", $data);
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Terjadi kesalahan pada sistem.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    // -------------------------------------------------------------------------
    // UPDATE
    // -------------------------------------------------------------------------

    /**
     * Mengubah data Absensi berdasarkan id.
     *
     * @urlParam id integer required ID Absensi.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            "kode_karyawan_mandor" => "nullable|exists:employee,fccode",
            "kode_karyawan" => "required|string|exists:employee,fccode",
            "attendance_type" => "nullable|in:REGULAR,ASSISTENSI",
            "time_out" => "nullable|date_format:Y-m-d H:i:s",
            "location_out" => "nullable",
            "pengancakan" => "nullable",
            "total_late_time" => "nullable|date_format:H:i",
            "go_home_early" => "nullable|date_format:H:i",
            "exception_case" => "nullable",
            "no_ba_exca" => "nullable|file|mimes:pdf|max:2048",
            "fcba" => "required|string|exists:employee,fcba",
            "section" => "nullable|exists:employee,sectionname",
            "gang" => "nullable|exists:employee,gangcode",
            "mandays" => "nullable|numeric|max:1",
            "attendance" => "required|string|in:KJ,WH,WS,MK,ML,P1,KB,OT",
            "fcba_destination" => "nullable|exists:employee,fcba",
            "section_destination" => "nullable|exists:employee,sectionname",
            "kemandoran" => "nullable|exists:users,gangcode",
            "id_device" => "nullable",
            "mac_address" => "nullable",
            "images" => "nullable|file|mimes:jpg,jpeg,png|max:2048",
        ]);

        try {
            $datas = Attendance::findOrFail($id);

            $storage = app(StorageService::class);

            $fcbaSlug = Str::slug(strtolower($datas->fcba ?? "unknown"));
            $tanggal = $datas->tanggal
                ? Carbon::parse($datas->tanggal)
                : Carbon::now();
            $datePath = $tanggal->format("Y/m/d");

            // --- IMAGES ---
            $imagePath = $datas->images;

            if ($request->hasFile("images")) {
                $folderPath = "file/attendance/images/{$fcbaSlug}/{$datePath}";
                $relativePath = $this->optimizeAndSaveImage(
                    $request->file("images"),
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

            // --- NO_BA_EXCA ---
            $baExcaPath = null;

            if ($request->hasFile("no_ba_exca")) {
                $baFile = $request->file("no_ba_exca");
                $baFileName = time() . "_" . $baFile->getClientOriginalName();
                $relativePath = "file/attendance/files/{$fcbaSlug}/{$datePath}/{$baFileName}";

                $baExcaPath = $storage->storeFile($baFile, $relativePath);
            }

            // --- Build update ---
            $updateData = [
                $validated["kode_karyawan_mandor"] ?? null,
                $validated["kode_karyawan"] ?? null,
                $validated["attendance_type"] ?? null,
                $validated["time_out"] ?? null,
                $validated["location_out"] ?? null,
                $validated["pengancakan"] ?? null,
                $validated["total_late_time"] ?? null,
                $validated["go_home_early"] ?? null,
                $validated["exception_case"] ?? null,
                $validated["fcba"] ?? null,
                $validated["section"] ?? null,
                $validated["gang"] ?? null,
                $validated["mandays"] ?? null,
                $validated["attendance"] ?? null,
                $validated["fcba_destination"] ?? null,
                $validated["section_destination"] ?? null,
                $validated["kemandoran"] ?? null,
                $validated["id_device"] ?? null,
                $validated["mac_address"] ?? null,
                $imagePath,
                Auth::user()->username,
                $id,
            ];

            $setClause = '
                "KODE_KARYAWAN_MANDOR" = ?,
                "KODE_KARYAWAN"        = ?,
                "ATTENDANCE_TYPE"      = ?,
                "TIME_OUT"             = ?,
                "LOCATION_OUT"         = ?,
                "PENGANCAKAN"          = ?,
                "TOTAL_LATE_TIME"      = ?,
                "GO_HOME_EARLY"        = ?,
                "EXCEPTION_CASE"       = ?,
                "FCBA"                 = ?,
                "SECTION"              = ?,
                "GANG"                 = ?,
                "MANDAYS"              = ?,
                "ATTENDANCE"           = ?,
                "FCBA_DESTINATION"     = ?,
                "SECTION_DESTINATION"  = ?,
                "KEMANDORAN"           = ?,
                "ID_DEVICE"            = ?,
                "MAC_ADDRESS"          = ?,
                "IMAGES"               = ?,
                "UPDATED_BY"           = ?,
                "UPDATED_AT"           = SYSDATE
            ';

            if ($baExcaPath !== null) {
                $setClause = '
                    "KODE_KARYAWAN_MANDOR" = ?,
                    "KODE_KARYAWAN"        = ?,
                    "ATTENDANCE_TYPE"      = ?,
                    "TIME_OUT"             = ?,
                    "LOCATION_OUT"         = ?,
                    "PENGANCAKAN"          = ?,
                    "TOTAL_LATE_TIME"      = ?,
                    "GO_HOME_EARLY"        = ?,
                    "EXCEPTION_CASE"       = ?,
                    "NO_BA_EXCA"           = ?,
                    "FCBA"                 = ?,
                    "SECTION"              = ?,
                    "GANG"                 = ?,
                    "MANDAYS"              = ?,
                    "ATTENDANCE"           = ?,
                    "FCBA_DESTINATION"     = ?,
                    "SECTION_DESTINATION"  = ?,
                    "KEMANDORAN"           = ?,
                    "ID_DEVICE"            = ?,
                    "MAC_ADDRESS"          = ?,
                    "IMAGES"               = ?,
                    "UPDATED_BY"           = ?,
                    "UPDATED_AT"           = SYSDATE
                ';
                array_splice($updateData, 9, 0, [$baExcaPath]);
            }

            DB::update(
                'UPDATE "SIPSMOBILE"."ATTENDANCE" SET ' .
                    $setClause .
                    ' WHERE "ID" = ?',
                $updateData,
            );

            $datas = Attendance::findOrFail($id);

            return response()->json(
                [
                    "success" => true,
                    "message" => "Data Absensi berhasil diperbarui.",
                    "data" => $datas,
                ],
                200,
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Data Absensi tidak ditemukan.",
                ],
                404,
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Terjadi kesalahan saat mengupdate data.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Terjadi kesalahan pada sistem.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    // -------------------------------------------------------------------------
    // UPDATE STATUS
    // -------------------------------------------------------------------------

    /**
     * Approved atau Reject status_attendance berdasarkan id.
     *
     * @urlParam id integer required ID Absensi.
     */
    public function updateStatus(Request $request, string $id)
    {
        $validated = $request->validate([
            "status_attendance" => "required|string|in:Planned,Reject,Approved",
        ]);

        try {
            Attendance::findOrFail($id);

            DB::update(
                'UPDATE "SIPSMOBILE"."ATTENDANCE"
                 SET "STATUS_ATTENDANCE" = ?, "UPDATED_BY" = ?, "UPDATED_AT" = SYSDATE
                 WHERE "ID" = ?',
                [$validated["status_attendance"], Auth::user()->username, $id],
            );

            $datas = Attendance::findOrFail($id);

            return response()->json(
                [
                    "success" => true,
                    "message" => "Status Absensi berhasil diperbarui.",
                    "data" => $datas,
                ],
                200,
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Data Absensi tidak ditemukan.",
                ],
                404,
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "Terjadi kesalahan saat mengupdate status absensi.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Terjadi kesalahan pada sistem.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }

    // -------------------------------------------------------------------------
    // DESTROY
    // -------------------------------------------------------------------------

    /**
     * Menghapus data Absensi berdasarkan id.
     *
     * @urlParam id integer required ID Absensi.
     */
    public function destroy(Request $request, string $id)
    {
        $request->validate([
            "ba_deleted" => "required|file|mimes:pdf|max:2048",
        ]);

        try {
            $datas = Attendance::findOrFail($id);

            $storage = app(StorageService::class);

            $fcbaSlug = Str::slug(strtolower($datas->fcba ?? "unknown"));
            $tanggal = $datas->tanggal
                ? Carbon::parse($datas->tanggal)
                : Carbon::now();
            $datePath = $tanggal->format("Y/m/d");

            // --- BA DELETED ---
            $baDeletedPath = null;

            if ($request->hasFile("ba_deleted")) {
                $baFile = $request->file("ba_deleted");
                $baFileName = time() . "_" . $baFile->getClientOriginalName();
                $relativePath = "file/attendance/files/{$fcbaSlug}/{$datePath}/{$baFileName}";

                $baDeletedPath = $storage->storeFile($baFile, $relativePath);
            }

            $datas->deleted_by = Auth::user()->username ?? null;
            $datas->deleted_attachment = $baDeletedPath;
            $datas->save();

            $datas->delete();

            return new AllResource(
                true,
                "Data Absensi berhasil dihapus.",
                $datas,
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Data Absensi tidak ditemukan.",
                ],
                404,
            );
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Terjadi kesalahan saat menghapus data.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Terjadi kesalahan pada sistem.",
                    "error" => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
