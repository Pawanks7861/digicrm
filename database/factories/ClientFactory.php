<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'contact_person' => fake()->name(),
            'email' => fake()->companyEmail(),
            'mobile' => fake()->numerify('98########'),
            'industry' => fake()->randomElement(['Real Estate', 'Beauty', 'F&B', 'Education']),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => 'India',
            'status' => 'prospect',
            'account_manager_id' => null,
            'start_date' => now()->toDateString(),
        ];
    }

    public function forManager(User $user): static
    {
        return $this->state(fn () => ['account_manager_id' => $user->id]);
    }
}
