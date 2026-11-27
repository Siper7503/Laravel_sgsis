<?php

namespace Database\Factories;

use App\Models\Etablissement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Etablissement>
 */
class EtablissementFactory extends Factory
{
    protected $model = Etablissement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('ETB-####'),
            'nom' => fake()->company(),
            'type' => fake()->randomElement(['primaire', 'secondaire']),
            'statut' => 'public',
            'arrondissement' => fake()->optional()->city(),
            'adresse' => fake()->optional()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'telephone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'actif' => true,
        ];
    }
}
