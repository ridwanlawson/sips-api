<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'mac_address' => $this->mac_address,
            'imei' => $this->imei,
            'device_name' => $this->device_name,
            'platform' => $this->platform,
            'os_version' => $this->os_version,
            'app_version' => $this->app_version,
            'assigned_to' => $this->assigned_to,
            'status' => $this->status,
            'registered_at' => $this->registered_at,
            'registered_by' => $this->registered_by,
            'last_login_at' => $this->last_login_at,
            'last_latitude' => $this->last_latitude,
            'last_longitude' => $this->last_longitude,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
