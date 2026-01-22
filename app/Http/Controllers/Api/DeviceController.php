<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Http\Resources\AllResource;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * @group Settings
 *
 * @subgroup Devices
 * @subgroupDescription Sub Group untuk Device Management
 *
 */
class DeviceController extends Controller
{
    /**
     * Memanggil data device dari SIPS Mobile.
     *
     * API ini digunakan untuk memanggil data device secara keseluruhan. 
     * Tetapi jika ingin melakukan filter pada data yang dipanggil, buatlah parameter pada Url berdasarkan _**Query Parameter**_
     *
     * @queryParam q string Optional. Search device berdasarkan device_id, device_name, mac_address, atau imei. Example: DEV-00001
     * @queryParam per_page integer Optional. Jumlah data per halaman. Default: 15. Example: 10
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "List Data Device",
     *  "data": [
     *      {
     *          "id": 1,
     *          "device_id": "DEV-00001",
     *          "mac_address": "00:11:22:33:44:55",
     *          "imei": "123456789012345",
     *          "device_name": "Operator Phone",
     *          "platform": "android",
     *          "os_version": "13",
     *          "app_version": "1.0.0",
     *          "assigned_to": "John Doe",
     *          "status": "active",
     *          "registered_at": "2025-12-12T10:00:00.000000Z",
     *          "registered_by": 1,
     *          "last_login_at": "2025-12-12T10:05:00.000000Z",
     *          "last_latitude": -6.2,
     *          "last_longitude": 106.816666,
     *          "notes": "Device assigned to new operator",
     *          "created_at": "2025-12-12T10:00:00.000000Z",
     *          "updated_at": "2025-12-12T10:00:00.000000Z"
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
            $query = Device::query();

            // Filter berdasarkan search parameter
            if ($search = $request->query('q')) {
                $query->where(function ($q) use ($search) {
                    $q->where('device_id', 'like', "%{$search}%")
                        ->orWhere('device_name', 'like', "%{$search}%")
                        ->orWhere('mac_address', 'like', "%{$search}%")
                        ->orWhere('imei', 'like', "%{$search}%");
                });
            }

            $perPage = (int) $request->query('per_page', 15);

            $devices = $query->orderBy('id', 'desc')->paginate($perPage);

            return new AllResource(true, 'List Data Device', $devices);
        } catch (\Exception $e) {
            Log::error('Device index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan data device berdasarkan id device.
     *
     * @urlParam id integer required ID Device. Example: 1
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Detail Data Device",
     *  "data": {
     *      "id": 1,
     *      "device_id": "DEV-00001",
     *      "mac_address": "00:11:22:33:44:55",
     *      "imei": "123456789012345",
     *      "device_name": "Operator Phone",
     *      "platform": "android",
     *      "os_version": "13",
     *      "app_version": "1.0.0",
     *      "assigned_to": "John Doe",
     *      "status": "active",
     *      "registered_at": "2025-12-12T10:00:00.000000Z",
     *      "registered_by": 1,
     *      "last_login_at": "2025-12-12T10:05:00.000000Z",
     *      "last_latitude": -6.2,
     *      "last_longitude": 106.816666,
     *      "notes": "Device assigned to new operator",
     *      "created_at": "2025-12-12T10:00:00.000000Z",
     *      "updated_at": "2025-12-12T10:00:00.000000Z"
     *  }
     * }
     */
    public function show($id)
    {
        try {
            $device = Device::findOrFail($id);
            return new AllResource(true, 'Detail Data Device', $device);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data device tidak ditemukan.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Device show error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyimpan data device ke dalam database SIPS Mobile.
     *
     * @bodyParam device_id string required ID device yang unik. Example: DEV-00001
     * @bodyParam mac_address string optional MAC address device. Example: 00:11:22:33:44:55
     * @bodyParam imei string optional IMEI number device. Example: 123456789012345
     * @bodyParam device_name string optional Nama device. Example: Operator Phone
     * @bodyParam platform string optional Platform device (android/ios/windows/linux/other). Example: android
     * @bodyParam os_version string optional Versi OS. Example: 13
     * @bodyParam app_version string optional Versi aplikasi. Example: 1.0.0
     * @bodyParam assigned_to string optional Nama penerima device. Example: John Doe
     * @bodyParam status string optional Status device. Example: active
     * @bodyParam registered_at string optional Tanggal registrasi (format: YYYY-MM-DD HH:MM:SS). Example: 2025-12-12 10:00:00
     * @bodyParam registered_by integer optional ID user yang mendaftarkan. Example: 1
     * @bodyParam last_login_at string optional Tanggal login terakhir. Example: 2025-12-12 10:05:00
     * @bodyParam last_latitude double optional Latitude lokasi terakhir. Example: -6.2
     * @bodyParam last_longitude double optional Longitude lokasi terakhir. Example: 106.816666
     * @bodyParam notes string optional Catatan tambahan. Example: Device assigned to new operator
     *
     * @response 201 scenario="success" {
     *  "success": true,
     *  "message": "Data Device berhasil ditambahkan.",
     *  "data": {
     *      "id": 1,
     *      "device_id": "DEV-00001",
     *      "mac_address": "00:11:22:33:44:55",
     *      "imei": "123456789012345",
     *      "device_name": "Operator Phone",
     *      "platform": "android",
     *      "os_version": "13",
     *      "app_version": "1.0.0",
     *      "assigned_to": "John Doe",
     *      "status": "active",
     *      "registered_at": "2025-12-12T10:00:00.000000Z",
     *      "registered_by": 1,
     *      "last_login_at": "2025-12-12T10:05:00.000000Z",
     *      "last_latitude": -6.2,
     *      "last_longitude": 106.816666,
     *      "notes": "Device assigned to new operator",
     *      "created_at": "2025-12-12T10:00:00.000000Z",
     *      "updated_at": "2025-12-12T10:00:00.000000Z"
     *  }
     * }
     */
    public function store(StoreDeviceRequest $request)
    {
        try {
            $data = $request->validated();
            $device = Device::create($data);

            return new AllResource(true, 'Data Device berhasil ditambahkan.', $device);
        } catch (QueryException $e) {
            Log::error('Device store database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data.',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('Device store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mengubah data device berdasarkan id device.
     *
     * @urlParam id integer required ID Device. Example: 1
     *
     * @bodyParam device_id string optional ID device yang unik. Example: DEV-00001
     * @bodyParam mac_address string optional MAC address device. Example: 00:11:22:33:44:55
     * @bodyParam imei string optional IMEI number device. Example: 123456789012345
     * @bodyParam device_name string optional Nama device. Example: Operator Phone
     * @bodyParam platform string optional Platform device. Example: android
     * @bodyParam os_version string optional Versi OS. Example: 13
     * @bodyParam app_version string optional Versi aplikasi. Example: 1.0.0
     * @bodyParam assigned_to string optional Nama penerima device. Example: John Doe
     * @bodyParam status string optional Status device. Example: active
     * @bodyParam registered_at string optional Tanggal registrasi. Example: 2025-12-12 10:00:00
     * @bodyParam registered_by integer optional ID user yang mendaftarkan. Example: 1
     * @bodyParam last_login_at string optional Tanggal login terakhir. Example: 2025-12-12 10:05:00
     * @bodyParam last_latitude double optional Latitude lokasi terakhir. Example: -6.2
     * @bodyParam last_longitude double optional Longitude lokasi terakhir. Example: 106.816666
     * @bodyParam notes string optional Catatan tambahan. Example: Device updated
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data Device berhasil diperbarui.",
     *  "data": {
     *      "id": 1,
     *      "device_id": "DEV-00001",
     *      "mac_address": "00:11:22:33:44:55",
     *      "imei": "123456789012345",
     *      "device_name": "Operator Phone",
     *      "platform": "android",
     *      "os_version": "13",
     *      "app_version": "1.0.0",
     *      "assigned_to": "John Doe",
     *      "status": "active",
     *      "registered_at": "2025-12-12T10:00:00.000000Z",
     *      "registered_by": 1,
     *      "last_login_at": "2025-12-12T10:05:00.000000Z",
     *      "last_latitude": -6.2,
     *      "last_longitude": 106.816666,
     *      "notes": "Device updated",
     *      "created_at": "2025-12-12T10:00:00.000000Z",
     *      "updated_at": "2025-12-12T10:00:00.000000Z"
     *  }
     * }
     */
    public function update(UpdateDeviceRequest $request, $id)
    {
        try {
            $device = Device::findOrFail($id);
            $device->update($request->validated());

            return new AllResource(true, 'Data Device berhasil diperbarui.', $device);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data device tidak ditemukan.'
            ], 404);
        } catch (QueryException $e) {
            Log::error('Device update database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui data.',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('Device update error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menghapus data device berdasarkan id device.
     *
     * @urlParam id integer required ID Device. Example: 1
     *
     * @response 200 scenario="success" {
     *  "success": true,
     *  "message": "Data Device berhasil dihapus.",
     *  "data": {
     *      "id": 1,
     *      "device_id": "DEV-00001",
     *      "mac_address": "00:11:22:33:44:55",
     *      "imei": "123456789012345",
     *      "device_name": "Operator Phone",
     *      "platform": "android",
     *      "os_version": "13",
     *      "app_version": "1.0.0",
     *      "assigned_to": "John Doe",
     *      "status": "active",
     *      "registered_at": "2025-12-12T10:00:00.000000Z",
     *      "registered_by": 1,
     *      "last_login_at": "2025-12-12T10:05:00.000000Z",
     *      "last_latitude": -6.2,
     *      "last_longitude": 106.816666,
     *      "notes": "Device assigned to new operator",
     *      "created_at": "2025-12-12T10:00:00.000000Z",
     *      "updated_at": "2025-12-12T10:00:00.000000Z"
     *  }
     * }
     */
    public function destroy($id)
    {
        try {
            $device = Device::findOrFail($id);
            $device->delete();

            return new AllResource(true, 'Data Device berhasil dihapus.', $device);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data device tidak ditemukan.'
            ], 404);
        } catch (QueryException $e) {
            Log::error('Device destroy database error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data.',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('Device destroy error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
