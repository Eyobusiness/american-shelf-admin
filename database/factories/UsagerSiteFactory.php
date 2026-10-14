<?php

namespace Database\Factories;

use App\Models\CategorieUsager;
use App\Models\Site;
use App\Models\Usager;
use App\Models\UsagerSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsagerSite>
 */
class UsagerSiteFactory extends Factory
{
    protected $model = UsagerSite::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'usager_id' => Usager::factory(),
            'numero' => 'LOCAL-' . fake()->numerify('#####'),
            'date_inscription' => fake()->date(),
            'type' => fake()->randomElement(['USAGER', 'ADHERENT']),
            'categorie_usager_id' => null,
            'statut' => fake()->randomElement(['ACTIF', 'SUSPENDU', 'EXPIRE']),
        ];
    }
}
