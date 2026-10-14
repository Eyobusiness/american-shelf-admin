<?php

namespace Database\Factories;

use App\Models\Presence;
use App\Models\Site;
use App\Models\Usager;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Presence>
 */
class PresenceFactory extends Factory
{
    protected $model = Presence::class;

    public function definition(): array
    {
        $heureArrivee = fake()->time('H:i:s');
        return [
            'site_id' => Site::factory(),
            'usager_id' => null,
            'date_presence' => fake()->date(),
            'heure_arrivee' => $heureArrivee,
            'heure_depart' => fake()->boolean(70) ? fake()->time('H:i:s', '23:59:59') : null,
            'motif' => fake()->sentence(3),
            'observations' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the presence has an identified user.
     */
    public function withUsager(?Usager $usager = null): static
    {
        return $this->state(fn (array $attributes) => [
            'usager_id' => $usager?->id ?? Usager::factory(),
        ]);
    }
}
