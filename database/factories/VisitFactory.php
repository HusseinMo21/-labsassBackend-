<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Visit>
 */
class VisitFactory extends Factory
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
            'visit_number' => 'VIS-' . $this->faker->date('Ymd') . '-' . $this->faker->numerify('######'),
            'visit_date' => $this->faker->date(),
            'visit_time' => $this->faker->time(),
            'status' => $this->faker->randomElement(['pending', 'registered', 'completed']),
            'total_amount' => $this->faker->randomFloat(2, 10, 500),
            'final_amount' => $this->faker->randomFloat(2, 10, 500),
        ];
    }
}
