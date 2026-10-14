<?php

namespace Database\Factories;

use App\Models\Configuration;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Configuration>
 */
class ConfigurationFactory extends Factory
{
    protected $model = Configuration::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'duree_emprunt' => 14,
            'nombre_jours_grace' => 3,
            'duree_consultation' => 2,
            'quota_emprunt' => 3,
            'duree_reservation' => 2,
        ];
    }
}
