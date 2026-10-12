<?php

namespace Database\Factories;

use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePackage>
 */
class ServicePackageFactory extends Factory
{
    protected $model = ServicePackage::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Package',
            'code' => strtoupper(fake()->unique()->bothify('PKG-###')),
            'description' => fake()->sentence(),
            'billing_cycle' => 'monthly',
            'base_price' => '35000.00',
            'currency' => 'INR',
            'status' => 'active',
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function withDefaultItems(): static
    {
        return $this->afterCreating(function (ServicePackage $package) {
            $package->items()->createMany([
                [
                    'service_name' => 'Static Post',
                    'service_code' => 'STATIC',
                    'quantity' => 12,
                    'unit' => 'Post',
                    'overage_rate' => 800,
                    'is_unlimited' => false,
                    'sort_order' => 0,
                ],
                [
                    'service_name' => 'Reel',
                    'service_code' => 'REEL',
                    'quantity' => 8,
                    'unit' => 'Reel',
                    'overage_rate' => 2000,
                    'is_unlimited' => false,
                    'sort_order' => 1,
                ],
                [
                    'service_name' => 'Meta Ads Management',
                    'service_code' => 'META',
                    'quantity' => 0,
                    'unit' => 'Month',
                    'overage_rate' => 0,
                    'is_unlimited' => true,
                    'sort_order' => 2,
                ],
            ]);
        });
    }
}
