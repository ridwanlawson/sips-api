<?php

namespace App\Http\Controllers\Api;

use App\Models\Attendance;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\AllResource;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @group Apps
 * 
 * @subgroup Absensi
 * @subgroupDescription Sub Group untuk Absensi 
 * 
 */
class AttendanceController extends Controller
{
    /**
     * Memanggil data Absensi dari SIPS Mobile.
     *
     * API ini digunakan untuk memanggil data ABSENSI secara keseluruhan. 
     * Tetapi jika ingin melakukan filter pada data yang dipanggil, 
     * buatlah parameter pada Url berdasarkan _**Query Parameter**_. data ini diurutkan berdasarkan Bisnis Unit, Tanggal terbaru, Afdeling dan Kode Karyawan
     *
     * @queryParam tanggal string Optional. Filter Absensi berdasarkan tanggal. Harus dalam format YYYY-MM-DD. Example: 2025-08-17
     * @queryParam tanggal_end string Optional. Filter Absensi berdasarkan rentang tanggal. Harus dalam format YYYY-MM-DD. Example: 2025-08-20
     * @queryParam kode_karyawan_mandor string Optional. Filter Absensi berdasarkan kode_karyawan_mandor. Example: 06-851012-151218-0079
     * @queryParam kode_karyawan string Optional. Filter Absensi berdasarkan kode_karyawan. Example: 06-031014-231025-0438
     * @queryParam fcba string Optional. Filter Absensi berdasarkan fcba. Example: MTE
     * @queryParam afdeling string Optional. Filter Absensi berdasarkan afdeling. Example: AFD-01
     * @queryParam gang string Optional. Filter Absensi berdasarkan gang. Example: PN011
     * @queryParam attendance string Optional. Filter Absensi berdasarkan attendance. Example: KJ
     * @queryParam status_attendance string Optional. Filter Absensi berdasarkan status kehadiran salah satu dari Planned, AuthorizedOnProgress, Approved, Reject. Example: Planned
     * @queryParam attendance_type string Optional. Filter Absensi berdasarkan type kehadiran salah satu dari REGULAR, ASSISTENSI. Example: REGULAR
     * @queryParam fcba_destination string Optional. Filter Absensi berdasarkan fcba_destination. Example: MRE
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "List Data Absensi",
     *  "data": [
     *      {
     *          "id": "25",
     *          "tanggal": "2024-12-19 00:00:00",
     *          "time_in": "2024-12-19 06:00:31",
     *          "location_in": "2024-12-19 06:00:31",
     *          "time_out": "2024-12-19 17:44:31",
     *          "location_out": "2024-12-19 17:44:31",
     *          "kode_karyawan_mandor": "06-851012-151218-0079",
     *          "namamandor": "SAMSUL",
     *          "kode_karyawan": "06-031014-231025-0438",
     *          "namakaryawan": "HENDRIKUS KLAU SERAN",
     *          "pengancakan": "MTE",
     *          "total_late_time": "01:11",
     *          "go_home_early": "00:10",
     *          "attendance_type": "REGULAR",
     *          "exception_case": "",
     *          "no_ba_exca": "",
     *          "fcba": "MTE",
     *          "section": "AFD-01",
     *          "gang": "PN011",
     *          "attendance": "KJ",
     *          "mandays": 0.82,
     *          "status_attendance": "Planned",
     *          "fcba_destination": "MRE",
     *          "image": "",
     *          "id_device": "",
     *          "mac_address": ""
     *      }
     *  ]
     * }
     */
    public function index(Request $request)
    {
        try {
            // Ambil parameter dari query string
            $tanggal        = $request->query('tanggal');
            $tanggalEnd     = $request->query('tanggal_end');
            $kode_karyawan_mandor = $request->query('kode_karyawan_mandor');
            $kode_karyawan  = $request->query('kode_karyawan');
            $fcba           = $request->query('fcba');
            $afdeling       = $request->query('afdeling');
            $gang           = $request->query('gang');
            $attendance     = $request->query('attendance');
            $status_attendance = $request->query('status_attendance');
            $fcba_destination = $request->query('fcba_destination');
            $attendance_type  = $request->query('attendance_type');

            $query = "
            select
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
                ATTENDANCE.IMAGES,
                ATTENDANCE.ID_DEVICE,
                ATTENDANCE.MAC_ADDRESS
            from 
                SIPSMOBILE.ATTENDANCE
            inner join 
                SIPSMOBILE.EMPLOYEE KARYAWAN 
                on ATTENDANCE.KODE_KARYAWAN = KARYAWAN.FCCODE 
                and ATTENDANCE.FCBA = KARYAWAN.FCBA 
            left join 
                SIPSMOBILE.EMPLOYEE MANDOR 
                on ATTENDANCE.KODE_KARYAWAN_MANDOR = MANDOR.FCCODE 
                and ATTENDANCE.FCBA = MANDOR.FCBA 
            where 
                ATTENDANCE.FCBA IS NOT NULL
        ";

            // Parameter binding
            $bindings = [];

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

                $query .= " and TRUNC(ATTENDANCE.TANGGAL) between TO_DATE(:tanggal, 'YYYY-MM-DD') and TO_DATE(:tanggal_end, 'YYYY-MM-DD') ";
                $bindings['tanggal'] = $startDate;
                $bindings['tanggal_end']   = $endDate;
            } elseif ($tanggal) {
                $query .= " and TRUNC(ATTENDANCE.TANGGAL) = TO_DATE(:tanggal, 'YYYY-MM-DD') ";
                $bindings['tanggal'] = $tanggal;
            } elseif ($tanggalEnd) {
                $query .= " and TRUNC(ATTENDANCE.TANGGAL) = TO_DATE(:tanggal_end, 'YYYY-MM-DD') ";
                $bindings['tanggal_end'] = $tanggalEnd;
            }

            if ($kode_karyawan_mandor) {
                $query .= " and ATTENDANCE.KODE_KARYAWAN_MANDOR = :kode_karyawan_mandor";
                $bindings['kode_karyawan_mandor'] = $kode_karyawan_mandor;
            }

            if ($kode_karyawan) {
                $query .= " and ATTENDANCE.KODE_KARYAWAN = :kode_karyawan";
                $bindings['kode_karyawan'] = $kode_karyawan;
            }

            if ($fcba) {
                $query .= " and ATTENDANCE.FCBA = :fcba";
                $bindings['fcba'] = $fcba;
            }

            if ($afdeling) {
                $query .= " and ATTENDANCE.SECTION = :afdeling";
                $bindings['afdeling'] = $afdeling;
            }

            if ($gang) {
                $query .= " and ATTENDANCE.GANG = :gang";
                $bindings['gang'] = $gang;
            }

            if ($attendance) {
                $query .= " and ATTENDANCE.ATTENDANCE = :attendance";
                $bindings['attendance'] = $attendance;
            }

            if ($status_attendance) {
                $query .= " and ATTENDANCE.STATUS_ATTENDANCE = :status_attendance";
                $bindings['status_attendance'] = $status_attendance;
            }

            if ($fcba_destination) {
                $query .= " and ATTENDANCE.FCBA_DESTINATION = :fcba_destination";
                $bindings['fcba_destination'] = $fcba_destination;
            }

            if ($attendance_type) {
                $query .= " and ATTENDANCE.ATTENDANCE_TYPE = :attendance_type";
                $bindings['attendance_type'] = $attendance_type;
            }

            // Tambahkan bagian akhir query
            $query .= "
            order by 
                ATTENDANCE.FCBA,
                ATTENDANCE.TANGGAL DESC,
                KARYAWAN.SECTIONNAME,
                ATTENDANCE.KODE_KARYAWAN
        ";

            // Jalankan query
            $datas = DB::connection('oracle')->select($query, $bindings);

            // Jika data kosong
            if (empty($datas)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data tidak ditemukan.',
                    'data'    => []
                ], 404);
            }

            return new AllResource(true, 'List Data Absensi', $datas);
        } catch (\Exception $e) {
            // Tangani kesalahan yang mungkin terjadi
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menyimpan data Absensi ke dalam database SIPS Mobile.
     */
    public function store(Request $request)
    {
        // Validasi inputan
        $request->validate([
            'tanggal' => 'required|date_format:Y-m-d',
            'kode_karyawan_mandor' => 'nullable|exists:employee,fccode',
            'kode_karyawan' => 'required|string|exists:employee,fccode',
            'time_in' => 'required|date_format:Y-m-d H:i:s',
            'time_out' => 'nullable|date_format:Y-m-d H:i:s',
            'location_in' => 'required',
            'location_out' => 'nullable',
            'pengancakan' => 'nullable',
            'total_late_time' => 'nullable|date_format:H:i',
            'go_home_early' => 'nullable|date_format:H:i',
            'attendance_type' => 'nullable|in:REGULAR,ASSISTENSI',
            'exception_case' => 'nullable',
            'no_ba_exca' => 'nullable|file|mimes:pdf|max:2048',
            'fcba' => 'required|string|exists:employee,fcba',
            'section' => 'nullable|exists:employee,sectionname',
            'gang' => 'nullable|exists:employee,gangcode',
            'mandays' => 'nullable|numeric|max:1',
            'attendance' => 'required|string|in:KJ,WH,WS,MK,ML,P1,KB,OT',
            'fcba_destination' => 'nullable|exists:employee,fcba',
            'id_device' => 'nullable',
            'mac_address' => 'nullable',
            'images' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'created_by' => 'nullable',
        ]);

        try {
            // Inisialisasi variabel path image (default null jika tidak ada file)
            $imagePath = null;

            // Jika ada file image yang diunggah
            if ($request->hasFile('images')) {
                $image = $request->file('images');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('file/attendance_images'), $imageName); // Simpan di public/attendance_images
                $imagePath = 'file/attendance_images/' . $imageName; // Path yang disimpan di database
            }

            $imagePath = $imagePath ? asset($imagePath) : null;

            // Inisialisasi variabel path image (default null jika tidak ada file)
            $baExcaPath = null;

            // Jika ada file image yang diunggah
            if ($request->hasFile('no_ba_exca')) {
                $baExca = $request->file('no_ba_exca');
                $baExcaName = time() . '_' . $baExca->getClientOriginalName();
                $baExca->move(public_path('file/attendance_images'), $baExcaName); // Simpan di public/attendance_images
                $baExcaPath = 'file/attendance_images/' . $baExcaName; // Path yang disimpan di database
            }

            $baExcaPath = $baExcaPath ? asset($baExcaPath) : null;

            // Simpan data absensi ke dalam database
            $datas = Attendance::create([
                'TANGGAL' => $request->tanggal,
                'KODE_KARYAWAN_MANDOR' => $request->kode_karyawan_mandor,
                'KODE_KARYAWAN' => $request->kode_karyawan,
                'TIME_IN' => $request->time_in,
                'TIME_OUT' => $request->time_out,
                'LOCATION_IN' => $request->location_in,
                'LOCATION_OUT' => $request->location_out,
                'PENGANCAKAN' => $request->pengancakan,
                'TOTAL_LATE_TIME' => $request->total_late_time,
                'GO_HOME_EARLY' => $request->go_home_early,
                'ATTENDANCE_TYPE' => $request->attendance_type,
                'EXCEPTION_CASE' => $request->exception_case,
                'NO_BA_EXCA' => $baExcaPath,
                'FCBA' => $request->fcba,
                'SECTION' => $request->section,
                'GANG' => $request->gang,
                'MANDAYS' => $request->mandays,
                'ATTENDANCE' => $request->attendance,
                'STATUS_ATTENDANCE' => 'Planned',
                'FCBA_DESTINATION' => $request->fcba_destination,
                'ID_DEVICE' => $request->id_device,
                'MAC_ADDRESS' => $request->mac_address,
                'IMAGES' => $imagePath, // Simpan path image jika ada
                'CREATED_BY' => Auth::user()->username, // Asumsi Anda menggunakan autentikasi untuk menyimpan user yang membuat data
            ]);

            // Kembalikan respons dengan data yang baru saja disimpan
            return new AllResource(true, 'Data Absensi berhasil ditambahkan.', $datas);
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
     * Menampilkan data Absensi berdasarkan id Absensi dari SIPS Mobile.
     *
     * @urlParam id integer required ID Absensi.
     */
    public function show(string $id)
    {
        try {
            // Query untuk mengambil detail data berdasarkan ID
            $query = "
                select 
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
                    )),0) AS MANDAYS,
                    ATTENDANCE.STATUS_ATTENDANCE,
                    ATTENDANCE.FCBA_DESTINATION,
                    ATTENDANCE.IMAGES,
                    ATTENDANCE.ID_DEVICE,
                    ATTENDANCE.MAC_ADDRESS
                from 
                    SIPSMOBILE.ATTENDANCE
                inner join
                    SIPSMOBILE.EMPLOYEE KARYAWAN
                on
                    ATTENDANCE.KODE_KARYAWAN = KARYAWAN.FCCODE
                    and ATTENDANCE.FCBA = KARYAWAN.FCBA
                left join
                    SIPSMOBILE.EMPLOYEE MANDOR
                on
                    ATTENDANCE.KODE_KARYAWAN_MANDOR = MANDOR.FCCODE 
                    and ATTENDANCE.FCBA = MANDOR.FCBA 
                where 
                    ATTENDANCE.ID = :id
            ";

            // Jalankan query
            $data = DB::connection('oracle')->selectOne($query, ['id' => $id]);

            // Jika data tidak ditemukan
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Absensi tidak ditemukan.',
                ], 404);
            }

            // Jika data ditemukan, kembalikan data
            return new AllResource(true, 'Detail Data Absensi', $data);
        } catch (\Exception $e) {
            // Tangani kesalahan yang mungkin terjadi
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage(), // Tambahkan pesan error teknis jika diperlukan
            ], 500);
        }
    }

    /**
     * Mengubah data Absensi berdasarkan id Absensi.
     *
     * @urlParam id integer required ID Absensi.
     */
    public function update(Request $request, string $id)
    {
        // Validasi input
        $validated = $request->validate([
            'kode_karyawan_mandor' => 'nullable|exists:employee,fccode',
            'kode_karyawan' => 'required|string|exists:employee,fccode',
            'attendance_type' => 'nullable|in:REGULAR,ASSISTENSI',
            'time_out' => 'nullable|date_format:Y-m-d H:i:s',
            'location_out' => 'nullable',
            'pengancakan' => 'nullable',
            'total_late_time' => 'nullable|date_format:H:i',
            'go_home_early' => 'nullable|date_format:H:i',
            'exception_case' => 'nullable',
            'no_ba_exca' => 'nullable|file|mimes:pdf|max:2048',
            'fcba' => 'required|string|exists:employee,fcba',
            'section' => 'nullable|exists:employee,sectionname',
            'gang' => 'nullable|exists:employee,gangcode',
            'mandays' => 'nullable|numeric|max:1',
            'attendance' => 'required|string|in:KJ,WH,WS,MK,ML,P1,KB,OT',
            'fcba_destination' => 'nullable|exists:employee,fcba',
            'id_device' => 'nullable',
            'mac_address' => 'nullable',
            'images' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ]);

        try {
            // Cari data berdasarkan ID
            $datas = Attendance::findOrFail($id);

            // Jika data tidak ditemukan
            if (!$datas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Absensi tidak ditemukan'
                ], 404);
            }

            $imagePath = $datas->images; // Default gunakan gambar lama

            // Jika ada file image yang diunggah
            if (!empty($request->hasFile('images'))) {
                $image = $request->file('images');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('file/attendance_images'), $imageName); // Simpan di public/attendance_images
                $imagePath = 'file/attendance_images/' . $imageName; // Path yang disimpan di database
                $imagePath = $imagePath ? asset($imagePath) : null;
            }

            // Inisialisasi variabel path image (default null jika tidak ada file)
            $baExcaPath = null;

            // Jika ada file image yang diunggah
            if ($request->hasFile('no_ba_exca')) {
                $baExca = $request->file('no_ba_exca');
                $baExcaName = time() . '_' . $baExca->getClientOriginalName();
                $baExca->move(public_path('file/attendance_images'), $baExcaName); // Simpan di public/attendance_images
                $baExcaPath = 'file/attendance_images/' . $baExcaName; // Path yang disimpan di database
            }

            $baExcaPath = $baExcaPath ? asset($baExcaPath) : null;

            // Menyusun data untuk update
            $updateData = [
                $validated['kode_karyawan_mandor'] ?? null, // 1                
                $validated['kode_karyawan'] ?? null,        // 2
                $validated['attendance_type'] ?? null,      // 3
                $validated['time_out'] ?? null,             // 4
                $validated['location_out'] ?? null,         // 5
                $validated['pengancakan'] ?? null,          // 6
                $validated['total_late_time'] ?? null,      // 7
                $validated['go_home_early'] ?? null,        // 8
                $validated['exception_case'] ?? null,       // 9
                $baExcaPath,                                // 10
                $validated['fcba'] ?? null,                 // 11
                $validated['section'] ?? null,              // 12
                $validated['gang'] ?? null,                 // 13
                $validated['mandays'] ?? null,              // 15
                $validated['attendance'] ?? null,           // 14
                $validated['fcba_destination'] ?? null,     // 16
                $validated['id_device'] ?? null,            // 17
                $validated['mac_address'] ?? null,          // 18
                $imagePath,                                 // 19
                Auth::user()->username,                     // 20
                $id,                                        // (ID untuk WHERE)
            ];

            // Update menggunakan query manual
            DB::update(
                "UPDATE \"SIPSMOBILE\".\"ATTENDANCE\" 
                SET 
                    \"KODE_KARYAWAN_MANDOR\" = ?,
                    \"KODE_KARYAWAN\" = ?,
                    \"ATTENDANCE_TYPE\" = ?,
                    \"TIME_OUT\" = ?,
                    \"LOCATION_OUT\" = ?,
                    \"PENGANCAKAN\" = ?,
                    \"TOTAL_LATE_TIME\" = ?,
                    \"GO_HOME_EARLY\" = ?,
                    \"EXCEPTION_CASE\" = ?,
                    \"NO_BA_EXCA\" = ?,
                    \"FCBA\" = ?,
                    \"SECTION\" = ?,
                    \"GANG\" = ?,
                    \"MANDAYS\" = ?,
                    \"ATTENDANCE\" = ?,
                    \"FCBA_DESTINATION\" = ?,
                    \"ID_DEVICE\" = ?,
                    \"MAC_ADDRESS\" = ?,
                    \"IMAGES\" = ?, 
                    \"UPDATED_BY\" = ?, 
                    \"UPDATED_AT\" = SYSDATE
                WHERE \"ID\" = ?",
                $updateData
            );
            $datas = Attendance::findOrFail($id);

            // Berhasil diupdate
            return response()->json([
                'success' => true,
                'message' => 'Data Absensi berhasil diperbarui.',
                'data' => $datas,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data Absensi tidak ditemukan.',
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
     * Approved atau Reject status_absensi (STATUS_ATTENDANCE) berdasarkan id Absensi.
     *
     * @urlParam id integer required ID Absensi.
     */
    public function updateStatus(Request $request, string $id)
    {
        // Validasi input status yang diizinkan
        $validated = $request->validate([
            'status_attendance' => 'required|string|in:Planned,Reject,Approved',
        ]);

        try {
            // Cari data berdasarkan ID
            $datas = Attendance::findOrFail($id);

            // Update status menggunakan query manual (konsisten dengan update lain)
            DB::update(
                "UPDATE \"SIPSMOBILE\".\"ATTENDANCE\" \n                SET \"STATUS_ATTENDANCE\" = ?, \"UPDATED_BY\" = ?, \"UPDATED_AT\" = SYSDATE\n                WHERE \"ID\" = ?",
                [$validated['status_attendance'], Auth::user()->username, $id]
            );

            // Ambil kembali data yang sudah diupdate
            $datas = Attendance::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Status Absensi berhasil diperbarui.',
                'data' => $datas,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data Absensi tidak ditemukan.',
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengupdate status absensi.',
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
     * Menghapus data Absensi berdasarkan id Absensi.
     *
     * @urlParam id integer required ID Absensi.
     */
    public function destroy(string $id)
    {
        try {
            $datas = Attendance::findOrFail($id);
            $datas->delete();
            return new AllResource(true, 'Data Absensi berhasil dihapus.', $datas);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data Absensi tidak ditemukan.',
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
