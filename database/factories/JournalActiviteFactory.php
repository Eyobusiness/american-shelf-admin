<?php

namespace Database\Factories;

use App\Models\JournalActivite;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JournalActivite>
 */
class JournalActiviteFactory extends Factory
{
    protected $model = JournalActivite::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'utilisateur_id' => null,
            'action' => fake()->randomElement(['CREATE', 'UPDATE', 'DELETE', 'LOGIN']),
            'module' => fake()->randomElement(['LIVRE', 'EMPRUNT', 'USAGER', 'CONFIG']),
            'reference' => strtoupper(fake()->bothify('REF-####')),
            'description' => fake()->sentence(),
            'adresse_ip' => fake()->ipv4(),
            'created_at' => now(),
        ];
    }
}
