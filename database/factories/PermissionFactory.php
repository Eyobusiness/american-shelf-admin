<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'menu_id' => null,
            'sous_menu_id' => null,
            'code' => strtoupper(fake()->unique()->lexify('PERM_???')),
            'libelle' => fake()->sentence(3),
            'action' => fake()->randomElement(['READ', 'CREATE', 'UPDATE', 'DELETE']),
        ];
    }
}
