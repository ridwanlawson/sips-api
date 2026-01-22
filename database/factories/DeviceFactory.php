<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DeviceFactory extends Factory
{
    public function modelName()
    {
        return \App\Models\Device::class;
    }

    public function definition()
    {
        $lat = $this->faker->optional()->latitude();
        $lng = $this->faker->optional()->longitude();

        return [
            'device_id' => Str::upper($this->faker->bothify('DEV-#####')),
            'mac_address' => $this->faker->macAddress(),
            'imei' => $this->faker->optional()->numerify('###############'),
            'device_name' => $this->faker->word(),
            'platform' => $this->faker->randomElement(['android', 'ios', 'windows', 'linux', 'other']),
            'os_version' => $this->faker->optional()->semver(),
            'app_version' => $this->faker->numerify('v#.#.#'),
            'assigned_to' => $this->faker->optional()->name(),
            'status' => $this->faker->randomElement(['active', 'inactive', 'suspended']),
            'registered_at' => $this->faker->optional()->dateTimeThisDecade(),
            'registered_by' => null,
            'last_login_at' => $this->faker->optional()->dateTimeThisYear(),
            'last_latitude' => $lat,
            'last_longitude' => $lng,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
