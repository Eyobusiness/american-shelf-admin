<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\Site;
use App\Models\Usager;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'usager_id' => Usager::factory(),
            'type' => Notification::TYPE_RESERVATION_CREEE,
            'objet' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'email_destinataire' => fake()->safeEmail(),
            'date_envoi' => now(),
            'statut' => Notification::STATUT_ENVOYE,
        ];
    }
}
