<?php

namespace Database\Factories;

use App\Models\Exemplaire;
use App\Models\Livre;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exemplaire>
 */
class ExemplaireFactory extends Factory
{
    protected $model = Exemplaire::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'livre_id' => Livre::factory(),
            'code' => 'EXP-' . fake()->unique()->lexify('????-####'),
            'etat' => fake()->randomElement(['NEUF', 'BON', 'MOYEN', 'DEGRADE']),
            'statut' => 'DISPONIBLE',
            'localisation' => 'Rayon ' . fake()->bothify('?#'),
        ];
    }
}
