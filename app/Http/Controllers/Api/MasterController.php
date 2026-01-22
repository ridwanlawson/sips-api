<?php

namespace App\Http\Controllers\Api;

use App\Models\Field;
use App\Models\Employee;
use App\Models\Tph;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\BusinessUnit;
use App\Http\Controllers\Controller;
use App\Http\Resources\AllResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;


/**
 * @group Master
 */
class MasterController extends Controller
{
    /**
     * Data Blok dari SIPS Production
     * 
     * Digunakan untuk inisialisasi data TPH dan mendata hasil panen
     */
    public function field()
    {
        $datas = Field::select(
            'FCCODE',
            'FCNAME',
            DB::raw('EXTRACT(YEAR FROM PLANTINGDATE) AS planting_year'), // Ambil hanya tahun
            DB::raw('NVL(HARVESTINGBASED_ABW,0) AS BJR'),
            DB::raw('HECTARAGEPLANTED AS HA_PLANTED'),
            'OWNERSHIP',
            'STATUS',
            'DIVISION AS AFDELING',
            'FCBA'
        )
            ->where('ACTIVATION', 'Y') // Hanya data dengan ACTIVATION = 'Y'
            ->orderBy('FCBA', 'asc')
            ->orderBy('DIVISION', 'asc')
            ->orderBy('PLANTINGDATE', 'asc')
            ->orderBy('FCCODE', 'asc')
            ->get();
        return new AllResource(true, 'List Data Field', $datas);
    }

    /**
     * Data Karyawan dari SIPS Production
     * 
     * Digunakan untuk inisialisasi data Karyawan pada SIPS Mobile untuk penetapan no ancak, absensi dan hasil panen. Tetapi jika ingin melakukan filter pada data yang dipanggil, buatlah parameter pada Url berdasarkan _**Query Parameter**_
     * 
     * @queryParam fccode string Optional. Filter Karyawan berdasarkan Kode Karyawan. Example: 06-001114-230720-0381
     * @queryParam fcname string Optional. Filter Karyawan berdasarkan Nama Karyawan. Example: REMIANUS NAHAK SERAN
     * @queryParam gangcode string Optional. Filter Karyawan berdasarkan Kode Gang. Example: PN01
     * @queryParam sectionname string Optional. Filter Karyawan berdasarkan Afdeling. Example: AFD-01
     * @queryParam fcba string Optional. Filter Karyawan berdasarkan Bisnis Unit (FCBA). Example: MTE
     */
    public function karyawan(Request $request)
    {
        $query = Employee::whereNull('DATETERMINATE')
            ->select('FCCODE', 'FCNAME', 'GANGCODE', 'SECTIONNAME', 'FCBA');

        // Tambahkan filter berdasarkan parameter yang diberikan
        if ($request->has('fccode')) {
            $query->where('FCCODE', 'like', '%' . $request->fccode . '%');
        }

        if ($request->has('fcname')) {
            $query->where('FCNAME', 'like', '%' . $request->fcname . '%');
        }

        if ($request->has('gangcode')) {
            $query->where('GANGCODE', 'like', '%' . $request->gangcode . '%');
        }

        if ($request->has('sectionname')) {
            $query->where('SECTIONNAME', 'like', '%' . $request->sectionname . '%');
        }

        if ($request->has('fcba')) {
            $query->where('FCBA', 'like', '%' . $request->fcba . '%');
        }

        // Eksekusi query dan dapatkan data
        $query->orderBy('FCBA', 'asc');
        $query->orderBy('SECTIONNAME', 'asc');
        $query->orderBy('GANGCODE', 'asc');
        $query->orderBy('FCCODE', 'asc');
        $datas = $query->get();

        return new AllResource(true, 'List Data Karyawan', $datas);
    }

    /**
     * Data Karyawan per Kemandoran dari SIPS Production
     * 
     * Digunakan untuk inisialisasi data Karyawan pada SIPS Mobile berdasarkan kemandoran untuk penetapan no ancak, absensi dan hasil panen. Wajib isi semua Parameter di _**Query Parameter**_ agar tepat kelompok kemandoran yang diambil. Pola gang code karyawan yang diambil berdasarkan PN ditambah 2 digit terakhir gang code mandor ditambah 1 digit terakhir gang code mandor.
     * 
     * @queryParam gangcode string Optional. Filter Karyawan per Kemandoran berdasarkan kode gang. Example: MD011
     * @queryParam sectionname string Optional. Filter Karyawan per Kemandoran berdasarkan afdeling. Example: AFD-01
     * @queryParam fcba string Optional. Filter Karyawan per Kemandoran berdasarkan bisnis unit atau FCBA. Example: MTE
     */
    public function karyawanKemandoran(Request $request)
    {
        // Pastikan filter tidak null dan diisi dengan benar
        $gangcode = $request->gangcode;
        $sectionname = $request->sectionname;
        $fcba = $request->fcba;

        if (empty($gangcode) || empty($sectionname) || empty($fcba)) {
            return response()->json([
                'success' => false,
                'message' => 'Semua filter harus diisi dengan nilai yang valid.',
            ], 400);
        }

        $lastsectionname = substr($sectionname, -2);
        $lastgangcode = substr($gangcode, -1);

        $panengangcode = "PN" . $lastsectionname . $lastgangcode;

        // Query dengan filter wajib
        $query = Employee::whereNull('DATETERMINATE')
            ->select('FCCODE', 'FCNAME', 'GANGCODE', 'SECTIONNAME', 'FCBA')
            ->where('GANGCODE', 'like', '%' . $panengangcode . '%')
            ->where('SECTIONNAME', 'like', '%' . $sectionname . '%')
            ->where('FCBA', 'like', '%' . $fcba . '%');

        // Urutkan hasil query
        $query->orderBy('FCBA', 'asc');
        $query->orderBy('SECTIONNAME', 'asc');
        $query->orderBy('GANGCODE', 'asc');
        $query->orderBy('FCCODE', 'asc');

        // Ambil hasil data
        $datas = $query->get();

        // Periksa apakah data kosong
        if ($datas->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Tidak ada data yang ditemukan.',
                'data' => []
            ], 200);
        }

        return new AllResource(true, 'List Data Karyawan', $datas);
    }

    /**
     * Data Kendaraan dari SIPS Production
     * 
     * Digunakan untuk inisialisasi data Kendaraan pada SIPS Mobile untuk mencari data kendaraan untuk pengangkutan panen. 
     * Tetapi jika ingin melakukan filter pada data yang dipanggil, buatlah parameter pada Url berdasarkan _**Query Parameter**_
     * 
     * @queryParam fccode string Optional. Filter Kendaraan berdasarkan kode kendaraan. Example: DT70
     * @queryParam fcname string Optional. Filter Kendaraan berdasarkan nama kendaraan. Example: Dump Truck 70
     * @queryParam vehiclegroupcode string Optional. Filter Kendaraan berdasarkan kode grup kendaraan. Example: DT
     * @queryParam registrationno string Optional. Filter Kendaraan berdasarkan plat nomor kendaraan tanpa spasi. Example: L9577BZ
     */
    public function vehicle(Request $request)
    {
        $query = Vehicle::where('ACTIVATION', '=', 'Y')
            ->select('FCCODE', 'FCNAME', 'VEHICLEGROUPCODE', 'DATECREATED', 'REGISTRATIONNO', 'MAKE', 'MODEL', 'YEAROFMADE', 'YEAROFPURCHASE');

        // Tambahkan filter berdasarkan parameter yang diberikan
        if ($request->has('fccode')) {
            $query->where('FCCODE', 'like', '%' . $request->fccode . '%');
        }

        if ($request->has('fcname')) {
            $query->where('FCNAME', 'like', '%' . $request->fcname . '%');
        }

        if ($request->has('vehiclegroupcode')) {
            $query->where('VEHICLEGROUPCODE', 'like', '%' . $request->vehiclegroupcode . '%');
        }

        if ($request->has('registrationno')) {
            $query->where('REGISTRATIONNO', 'like', '%' . $request->registrationno . '%');
        }

        // $query->where('FCBA', '=', 'CNT');

        // Eksekusi query dan dapatkan data
        $query->orderBy('VEHICLEGROUPCODE', 'asc');
        $query->orderBy('FCCODE', 'asc');
        $query->orderBy('YEAROFMADE', 'asc');
        $datas = $query->get();

        return new AllResource(true, 'List Data Kendaraan', $datas);
    }

    /**
     * Data TPH dari SIPS Mobile
     * 
     * Digunakan untuk mencatat hasil panen 
     */
    public function tph()
    {
        $datas = Tph::all();
        return new AllResource(true, 'List Data TPH', $datas);
    }

    /**
     * Data User dari SIPS Mobile
     * 
     * Digunakan untuk melihat seluruh data user yang bisa melakukan kegiatan pada sistem SIPS Mobile.
     * Tetapi jika ingin melakukan filter pada data yang dipanggil, buatlah parameter pada Url berdasarkan _**Query Parameter**_
     * 
     * @queryParam fcba string Optional. Filter User berdasarkan kode FCBA/Business Unit. Example: MTE
     * @queryParam afdeling string Optional. Filter User berdasarkan kode Afdeling. Example: AFD-01
     * @queryParam gangcode string Optional. Filter User berdasarkan kode Gang. Example: PNN
     * @queryParam level string Optional. Filter User berdasarkan level User (MGR : MANAGER, AST : ASISTEN, MD1 : MANDOR 1, MDP : MANDOR PANEN, KRP : KERANI PANEN, KRT : KERANI TRANSPORT). Example: AST
     * @queryParam position string Optional. Filter User berdasarkan posisi User (EM : MANAGER, ASISTEN : ASISTEN, MANDOR1 : MANDOR 1, MD.PANEN : MANDOR PANEN, KR.PANEN : KERANI PANEN, KR.TRANS : KERANI TRANSPORT) . Example: ASISTEN
     */
    public function index(Request $request)
    {
        $users = User::where('STATUS', '=', 'Y')
            ->select('*');

        // Tambahkan filter berdasarkan parameter yang diberikan
        if ($request->has('fcba')) {
            $users->where('FCBA', 'like', '%' . $request->fcba . '%');
        }

        if ($request->has('afdeling')) {
            $users->where('AFDELING', 'like', '%' . $request->afdeling . '%');
        }

        if ($request->has('gangcode')) {
            $users->where('GANGCODE', 'like', '%' . $request->gangcode . '%');
        }

        if ($request->has('level')) {
            $users->where('LEVEL', 'like', '%' . $request->level . '%');
        }

        if ($request->has('position')) {
            $users->where('POSITION', 'like', '%' . $request->position . '%');
        }
        // Eksekusi query dan dapatkan data
        $users->orderBy('FCBA', 'asc');
        $users->orderBy('AFDELING', 'asc');
        $users->orderBy('GANGCODE', 'asc');
        $datas = $users->get();

        return new AllResource(true, 'List Data Users', $datas);
    }

    /**
     * Data Business Unit dari SIPS Production
     * 
     * Digunakan untuk inisialisasi data Business Unit pada SIPS Mobile untuk mencari data Business Unit untuk pengangkutan panen. 
     * Tetapi jika ingin melakukan filter pada data yang dipanggil, buatlah parameter pada Url berdasarkan _**Query Parameter**_
     * 
     * @queryParam fccode string Optional. Filter Business Unit berdasarkan kode Business Unit. Example: MTE
     * @queryParam fcname string Optional. Filter Business Unit berdasarkan nama Business Unit. Example: Mutiara Estate
     * @queryParam fccompanycode string Optional. Filter Business Unit berdasarkan kode grup Business Unit. Example: PT.SKJ
     * @queryParam fctype string Optional. Filter Business Unit berdasarkan type Business Unit (E : Estate, M : Mill / Pabrik, HO : Head Office, W : Workshop). Example: E
     * @queryParam central string Optional. Filter Business Unit berdasarkan posisi Business Unit apakah Central (Y) atau tidak (N). Example: N
     */
    public function businessunit(Request $request)
    {
        $query = BusinessUnit::where('ISACTIVE', '=', 'TRUE')
            ->select('FCCODE', 'FCNAME', 'FCCOMPANYCODE', 'FCCOMPANYNAME', 'FCAREA', 'FCOWNERSHIP', 'FCMANAGER', 'FCKTU', 'FCTYPE', 'FCCROP', 'GROUPPLANTATION', 'PLANTATION', 'GROUPREGION', 'REGION', 'ADDRESS', 'CENTRAL', 'NOUNIT');

        // Tambahkan filter berdasarkan parameter yang diberikan
        if ($request->has('fccode')) {
            $query->where('FCCODE', 'like', '%' . $request->fccode . '%');
        }

        if ($request->has('fcname')) {
            $query->where('FCNAME', 'like', '%' . $request->fcname . '%');
        }

        if ($request->has('fccompanycode')) {
            $query->where('FCCOMPANYCODE', 'like', '%' . $request->fccompanycode . '%');
        }

        if ($request->has('fctype')) {
            $query->where('FCTYPE', 'like', '%' . $request->fctype . '%');
        }

        if ($request->has('central')) {
            $query->where('CENTRAL', 'like', '%' . $request->central . '%');
        }

        // Eksekusi query dan dapatkan data
        $query->orderBy('NOUNIT', 'asc');
        $datas = $query->get();

        return new AllResource(true, 'List Data Business Unit', $datas);
    }
}
