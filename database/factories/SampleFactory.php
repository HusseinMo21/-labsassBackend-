<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sample>
 */
class SampleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lab_request_id' => \App\Models\LabRequest::factory(),
            'barcode' => $this->faker->year() . '-' . $this->faker->numberBetween(1, 999) . '-S' . $this->faker->numberBetween(1, 10),
            'sample_id' => 'S' . $this->faker->numberBetween(1, 10),
            'tsample' => $this->faker->word(),
            'nsample' => $this->faker->word(),
            'isample' => $this->faker->word(),
            'notes' => $this->faker->sentence(),
        ];
    }
}
