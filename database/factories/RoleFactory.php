<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'code' => strtoupper(fake()->unique()->lexify('ROLE_???')),
            'nom' => fake()->jobTitle(),
            'description' => fake()->sentence(),
            'statut' => 'ACTIF',
        ];
    }
}
