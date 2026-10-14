<?php

namespace Database\Factories;

use App\Models\Livre;
use App\Models\Reservation;
use App\Models\Site;
use App\Models\Usager;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        $dateReservation = fake()->dateTimeBetween('-1 month', 'now');
        $dateExpiration = (clone $dateReservation)->modify('+2 days');

        return [
            'site_id' => Site::factory(),
            'usager_id' => Usager::factory(),
            'livre_id' => Livre::factory(),
            'date_reservation' => $dateReservation,
            'date_expiration' => $dateExpiration,
            'statut' => 'EN_ATTENTE',
            'observations' => fake()->optional()->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'EN_ATTENTE',
            'date_expiration' => now()->addDays(2),
        ]);
    }

    public function annulee(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'ANNULEE',
        ]);
    }

    public function expiree(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'EXPIREE',
            'date_expiration' => now()->subDay(),
        ]);
    }
}
