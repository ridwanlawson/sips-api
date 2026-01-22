<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AncakFactory extends Factory
{
    public function modelName()
    {
        return \App\Models\Ancak::class;
    }

    public function definition()
    {
        $fcbas = ['MTE', 'MRE', 'MBE', 'MKE'];
        $afdelings = ['AFD-01', 'AFD-02', 'AFD-03', 'AFD-04', 'AFD-05'];
        $fieldcodes = ['A01A', 'A01B', 'A02A', 'A02B', 'A03A', 'B01A', 'B01B', 'B02A'];

        return [
            'fcba' => $this->faker->randomElement($fcbas),
            'afdeling' => $this->faker->randomElement($afdelings),
            'fieldcode' => $this->faker->randomElement($fieldcodes),
            'noancak' => $this->faker->numerify('##A'),
            'luas' => $this->faker->numberBetween(15, 50) + $this->faker->randomFloat(2, 0, 0.99),
            'tph_id' => null, // Akan di-set oleh seeder
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'notes' => $this->faker->optional()->sentence(),
            'created_by' => 'system',
            'updated_by' => null,
        ];
    }
}
