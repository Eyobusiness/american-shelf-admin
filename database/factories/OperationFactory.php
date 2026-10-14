<?php

namespace Database\Factories;

use App\Models\Configuration;
use App\Models\Exemplaire;
use App\Models\Operation;
use App\Models\Site;
use App\Models\Usager;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Operation>
 */
class OperationFactory extends Factory
{
    protected $model = Operation::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['CONSULTATION', 'EMPRUNT']);
        $dateOperation = fake()->dateTimeBetween('-1 month', 'now');
        $dateRetourPrevue = (clone $dateOperation)->modify($type === 'EMPRUNT' ? '+14 days' : '+2 hours');

        return [
            'site_id' => Site::factory(),
            'configuration_id' => Configuration::factory(),
            'usager_id' => Usager::factory(),
            'exemplaire_id' => Exemplaire::factory(),
            'type' => $type,
            'date_operation' => $dateOperation,
            'date_retour_prevue' => $dateRetourPrevue,
            'date_retour_effective' => null,
            'statut' => 'EN_COURS',
            'observations' => fake()->optional()->sentence(),
        ];
    }

    public function emprunt(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'EMPRUNT',
        ]);
    }

    public function consultation(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'CONSULTATION',
        ]);
    }

    public function retourne(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'RETOURNE',
            'date_retour_effective' => now(),
        ]);
    }
}
