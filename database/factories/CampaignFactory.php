<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->words(3, true).' Campaign',
            'code' => Campaign::generateNextCode(),
            'campaign_type' => 'social_media',
            'status' => 'active',
            'priority' => 'normal',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'currency' => 'INR',
            'created_by' => User::factory(),
        ];
    }
}
