<?php

namespace Database\Factories;

use App\Models\CategorieUsager;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategorieUsager>
 */
class CategorieUsagerFactory extends Factory
{
    protected $model = CategorieUsager::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'code' => 'CAT-' . fake()->unique()->lexify('????'),
            'nom' => fake()->word(),
            'statut' => 'ACTIF',
        ];
    }
}
