<?php

namespace Database\Factories;

use App\Models\CategorieLivre;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategorieLivre>
 */
class CategorieLivreFactory extends Factory
{
    protected $model = CategorieLivre::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'code' => 'CATL-' . fake()->unique()->lexify('????'),
            'nom' => fake()->word(),
            'statut' => 'ACTIF',
        ];
    }
}
