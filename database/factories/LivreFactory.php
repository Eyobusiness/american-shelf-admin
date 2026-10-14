<?php

namespace Database\Factories;

use App\Models\CategorieLivre;
use App\Models\Livre;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Livre>
 */
class LivreFactory extends Factory
{
    protected $model = Livre::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'categorie_livre_id' => CategorieLivre::factory(),
            'code' => 'LIV-' . fake()->unique()->lexify('????-####'),
            'titre' => fake()->sentence(4),
            'auteur' => fake()->name(),
            'isbn' => fake()->optional()->isbn13(),
        ];
    }
}
