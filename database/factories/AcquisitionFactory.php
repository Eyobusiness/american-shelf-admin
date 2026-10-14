<?php

namespace Database\Factories;

use App\Models\Acquisition;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Acquisition>
 */
class AcquisitionFactory extends Factory
{
    protected $model = Acquisition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'type' => 'ACHAT',
            'provenance' => fake()->company(),
            'reference_facture' => strtoupper(fake()->bothify('FAC-####-????')),
            'date_acquisition' => now()->subDays(rand(1, 30))->toDateString(),
            'cout' => fake()->randomFloat(2, 50000, 500000),
            'quantite' => rand(1, 10),
            'etat' => 'RECU',
            'observations' => fake()->sentence(),
        ];
    }
}
