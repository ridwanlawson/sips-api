<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeviceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id') ?? $this->route('device');

        return [
            'device_id' => 'sometimes|string|unique:devices,device_id,' . $id,
            'mac_address' => 'nullable|string',
            'imei' => 'nullable|string',
            'device_name' => 'nullable|string',
            'platform' => 'nullable|string',
            'os_version' => 'nullable|string',
            'app_version' => 'nullable|string',
            'assigned_to' => 'nullable|string',
            'status' => 'nullable|string',
            'registered_at' => 'nullable|date',
            'registered_by' => 'nullable|integer|exists:users,id',
            'last_login_at' => 'nullable|date',
            'last_latitude' => 'nullable|numeric|between:-90,90',
            'last_longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string',
        ];
    }

    /**
     * Scribe Body Parameters Documentation
     */
    public function bodyParameters()
    {
        return [
            'device_id' => [
                'description' => 'ID device yang unik (opsional untuk update)',
                'example' => 'DEV-00001',
            ],
            'mac_address' => [
                'description' => 'MAC address dari device',
                'example' => '00:11:22:33:44:55',
            ],
            'imei' => [
                'description' => 'IMEI number dari device mobile',
                'example' => '123456789012345',
            ],
            'device_name' => [
                'description' => 'Nama device untuk identifikasi',
                'example' => 'Operator Phone Updated',
            ],
            'platform' => [
                'description' => 'Platform device (android/ios/web)',
                'example' => 'android',
            ],
            'os_version' => [
                'description' => 'Versi operating system',
                'example' => '14',
            ],
            'app_version' => [
                'description' => 'Versi aplikasi SIPS Mobile',
                'example' => '1.1.0',
            ],
            'assigned_to' => [
                'description' => 'Nama user yang device ini ditugaskan ke',
                'example' => 'Jane Doe',
            ],
            'status' => [
                'description' => 'Status device (active/inactive/suspended)',
                'example' => 'inactive',
            ],
            'registered_at' => [
                'description' => 'Tanggal device di-register (format: Y-m-d)',
                'example' => '2025-12-12',
            ],
            'registered_by' => [
                'description' => 'User ID yang melakukan registrasi device',
                'example' => '2',
            ],
            'last_login_at' => [
                'description' => 'Tanggal last login device (format: Y-m-d)',
                'example' => '2025-12-13',
            ],
            'last_latitude' => [
                'description' => 'Latitude koordinat terakhir device (range: -90 to 90)',
                'example' => '-6.250000',
            ],
            'last_longitude' => [
                'description' => 'Longitude koordinat terakhir device (range: -180 to 180)',
                'example' => '106.850000',
            ],
            'notes' => [
                'description' => 'Catatan atau keterangan tambahan tentang device',
                'example' => 'Device updated status',
            ],
        ];
    }
}
