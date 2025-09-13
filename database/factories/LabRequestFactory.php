<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LabRequest>
 */
class LabRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => \App\Models\Patient::factory(),
            'lab_no' => $this->faker->year() . '-' . $this->faker->unique()->numberBetween(1, 9999),
            'status' => $this->faker->randomElement(['pending', 'in_progress', 'completed']),
        ];
    }
}
