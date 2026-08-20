<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AllResource;
use App\Models\VehicleRent;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * @group Apps
 *
 * @subgroup Vehicle Rents
 * @subgroupDescription Sub Group untuk Management Vehicle Rent
 *
 */
class VehicleRentController extends Controller
{
    /**
     * Memanggil data vehicle rent.
     *
     * API ini digunakan untuk memanggil data vehicle rent secara keseluruhan.
     * Tetapi jika ingin melakukan filter pada data yang dipanggil, buatlah parameter pada Url berdasarkan _**Query Parameter**_
     *
     * @queryParam contract_no string Optional. Filter berdasarkan nomor kontrak. Example: CTR-0001
     * @queryParam fcba string Optional. Filter berdasarkan fcba. Example: MTE
     * @queryParam vehicle_code string Optional. Filter berdasarkan kode kendaraan. Example: KND-0001
     * @queryParam vehicle_name string Optional. Filter berdasarkan nama kendaraan. Example: Toyota Hiace
     * @queryParam registration_no string Optional. Filter berdasarkan nomor registrasi. Example: B 1234 XYZ
     * @queryParam nik string Optional. Filter berdasarkan NIK driver. Example: 1234567890123456
     * @queryParam driver_name string Optional. Filter berdasarkan nama driver. Example: John Doe
     * @queryParam tanggal string Optional. Filter berdasarkan tanggal. Example: 2026-08-01
     * @queryParam valid_from string Optional. Filter berdasarkan tanggal mulai berlaku. Example: 2026-08-01
     * @queryParam valid_until string Optional. Filter berdasarkan tanggal berakhir berlaku. Example: 2026-12-31
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "List Data Vehicle Rent",
     *  "data": [
     *      {
     *          "id": 1,
     *          "contract_no": "CTR-0001",
     *          "vehicle_code": "KND-0001",
     *          "vehicle_name": "Toyota Hiace",
     *          "registration_no": "B 1234 XYZ",
     *          "nik": "1234567890123456",
     *          "driver_name": "John Doe",

     *          "valid_from": "2026-08-01",
     *          "valid_until": "2026-12-31",
     *          "created_at": "2026-08-19T10:00:00.000000Z",
     *          "updated_at": "2026-08-19T10:00:00.000000Z"
     *      }
     *  ],
     *  "meta": {
     *      "current_page": 1,
     *      "from": 1,
     *      "last_page": 1,
     *      "per_page": 15,
     *      "to": 1,
     *      "total": 1
     *  }
     * }
     */
    public function index(Request $request)
    {
        try {
            $query = VehicleRent::query();

            // Filter berdasarkan nomor kontrak
            if ($contractNo = $request->query('contract_no')) {
                $query->where('contract_no', '=', $contractNo);
            }

            // Filter berdasarkan fcba
            if ($fcba = $request->query('fcba')) {
                $query->where('fcba', '=', $fcba);
            }

            // Filter berdasarkan kode kendaraan
            if ($vehicleCode = $request->query('vehicle_code')) {
                $query->where('vehicle_code', '=', $vehicleCode);
            }

            // Filter berdasarkan nama kendaraan
            if ($vehicleName = $request->query('vehicle_name')) {
                $query->where('vehicle_name', '=', $vehicleName);
            }

            // Filter berdasarkan nomor registrasi
            if ($registrationNo = $request->query('registration_no')) {
                $query->where('registration_no', '=', $registrationNo);
            }

            // Filter berdasarkan NIK driver
            if ($nik = $request->query('nik')) {
                $query->where('nik', '=', $nik);
            }

            // Filter berdasarkan tanggal
            if ($tanggal = $request->query('tanggal')) {
                $query->whereDate('tanggal', '=', $tanggal);
            }

            // Filter berdasarkan nama driver
            if ($driverName = $request->query('driver_name')) {
                $query->where('driver_name', '=', $driverName);
            }

            // Filter berdasarkan tanggal
            if ($tanggal = $request->query('tanggal')) {
                $query->whereDate('tanggal', '=', $tanggal);
            }

            // Filter berdasarkan tanggal mulai berlaku
            if ($validFrom = $request->query('valid_from')) {
                $query->whereDate('valid_from', '=', $validFrom);
            }

            // Filter berdasarkan tanggal berakhir berlaku
            if ($validUntil = $request->query('valid_until')) {
                $query->whereDate('valid_until', '=', $validUntil);
            }

            $data = $query->orderBy('id', 'desc')->get();

            return new AllResource(true, 'List Data Vehicle Rent', $data);
        } catch (\Exception $e) {
            Log::error('VehicleRent index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan data vehicle rent berdasarkan id.
     *
     * @urlParam id integer required ID Vehicle Rent. Example: 1
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Detail Data Vehicle Rent",
     *  "data": {
     *      "id": 1,
     *      "contract_no": "CTR-0001",
     *      "vehicle_code": "KND-0001",
     *      "vehicle_name": "Toyota Hiace",
     *      "registration_no": "B 1234 XYZ",
     *      "nik": "1234567890123456",
     *      "driver_name": "John Doe",
     *      "valid_from": "2026-08-01",
     *      "valid_until": "2026-12-31",
     *      "created_at": "2026-08-19T10:00:00.000000Z",
     *      "updated_at": "2026-08-19T10:00:00.000000Z"
     *  }
     * }
     */
    public function show($id)
    {
        try {
            $vehicleRent = VehicleRent::findOrFail($id);
            return new AllResource(true, 'Detail Data Vehicle Rent', $vehicleRent);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data vehicle rent tidak ditemukan.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('VehicleRent show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyimpan data vehicle rent ke dalam database.
     *
     * @bodyParam contract_no string optional Nomor kontrak sewa. Example: CTR-0001
     * @bodyParam vehicle_code string optional Kode kendaraan. Example: KND-0001
     * @bodyParam vehicle_name string optional Nama/jenis kendaraan. Example: Toyota Hiace
     * @bodyParam registration_no string optional Nomor registrasi kendaraan. Example: B 1234 XYZ
     * @bodyParam nik string optional NIK driver. Example: 1234567890123456
     * @bodyParam driver_name string optional Nama driver. Example: John Doe

     * @bodyParam valid_from string optional Tanggal mulai berlaku. Example: 2026-08-01
     * @bodyParam valid_until string optional Tanggal berakhir berlaku. Example: 2026-12-31
     *
     * @response 201 scenario="success" {
     *  "success": true,
     *  "message": "Data Vehicle Rent berhasil ditambahkan.",
     *  "data": {
     *      "id": 1,
     *      "contract_no": "CTR-0001"
     *  }
     * }
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'contract_no' => 'required|string',
                'fcba' => 'nullable|string',
                'vehicle_code' => 'required|string',
                'vehicle_name' => 'required|string',
                'registration_no' => 'required|string',
                'nik' => 'required|string',
                'driver_name' => 'required|string',
                'tanggal' => 'required|date',
                'valid_from' => 'required|date',
                'valid_until' => 'required|date',
            ]);
            $vehicleRent = VehicleRent::create($data);

            return new AllResource(true, 'Data Vehicle Rent berhasil ditambahkan.', $vehicleRent);
        } catch (QueryException $e) {
            Log::error('VehicleRent store database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data.',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('VehicleRent store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengubah data vehicle rent berdasarkan id.
     *
     * @urlParam id integer required ID Vehicle Rent. Example: 1
     *
     * @bodyParam contract_no string optional Nomor kontrak sewa. Example: CTR-0001
     * @bodyParam vehicle_code string optional Kode kendaraan. Example: KND-0001
     * @bodyParam vehicle_name string optional Nama/jenis kendaraan. Example: Toyota Hiace
     * @bodyParam registration_no string optional Nomor registrasi kendaraan. Example: B 1234 XYZ
     * @bodyParam nik string optional NIK driver. Example: 1234567890123456
     * @bodyParam driver_name string optional Nama driver. Example: John Doe

     * @bodyParam valid_from string optional Tanggal mulai berlaku. Example: 2026-08-01
     * @bodyParam valid_until string optional Tanggal berakhir berlaku. Example: 2026-12-31
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data Vehicle Rent berhasil diperbarui.",
     *  "data": {
     *      "id": 1,
     *      "contract_no": "CTR-0001"
     *  }
     * }
     */
    public function update(Request $request, $id)
    {
        try {
            $vehicleRent = VehicleRent::findOrFail($id);
            $vehicleRent->update($request->validate([
                'contract_no' => 'required|string',
                'fcba' => 'nullable|string',
                'vehicle_code' => 'required|string',
                'vehicle_name' => 'required|string',
                'registration_no' => 'required|string',
                'nik' => 'required|string',
                'driver_name' => 'required|string',
                'tanggal' => 'required|date',
                'valid_from' => 'required|date',
                'valid_until' => 'required|date',
            ]));

            return new AllResource(true, 'Data Vehicle Rent berhasil diperbarui.', $vehicleRent);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data vehicle rent tidak ditemukan.'
            ], 404);
        } catch (QueryException $e) {
            Log::error('VehicleRent update database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui data.',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('VehicleRent update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menghapus data vehicle rent berdasarkan id.
     *
     * @urlParam id integer required ID Vehicle Rent. Example: 1
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data Vehicle Rent berhasil dihapus.",
     *  "data": {
     *      "id": 1
     *  }
     * }
     */
    public function destroy($id)
    {
        try {
            $vehicleRent = VehicleRent::findOrFail($id);
            $vehicleRent->delete();

            return new AllResource(true, 'Data Vehicle Rent berhasil dihapus.', $vehicleRent);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data vehicle rent tidak ditemukan.'
            ], 404);
        } catch (QueryException $e) {
            Log::error('VehicleRent destroy database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data.',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('VehicleRent destroy error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
