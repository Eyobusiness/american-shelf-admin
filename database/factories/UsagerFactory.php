<?php

namespace Database\Factories;

use App\Models\Usager;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Usager>
 */
class UsagerFactory extends Factory
{
    protected $model = Usager::class;

    public function definition(): array
    {
        return [
            'numero' => 'USG-' . fake()->unique()->numerify('#####'),
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'mot_de_passe' => Hash::make('Password123!'),
            'contact' => fake()->phoneNumber(),
        ];
    }
}
