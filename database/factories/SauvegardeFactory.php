<?php

namespace Database\Factories;

use App\Models\Sauvegarde;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sauvegarde>
 */
class SauvegardeFactory extends Factory
{
    protected $model = Sauvegarde::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'nom_fichier' => 'backup_' . fake()->date('Y_m_d') . '_' . fake()->time('His') . '.sql',
            'chemin_fichier' => '/backups/' . fake()->uuid() . '.sql',
            'taille' => fake()->numberBetween(10240, 1048576),
            'date_sauvegarde' => now(),
            'statut' => 'TERMINE',
            'created_at' => now(),
        ];
    }
}
