<?php

namespace Tests\Feature\Phase13\Concerns;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

trait BuildsAgencyUsers
{
    protected function setUpAgency(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function makeUser(string $role, array $attrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'is_active' => true,
        ], $attrs));
        $user->assignRole($role);

        return $user;
    }
}
