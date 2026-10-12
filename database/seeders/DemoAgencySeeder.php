<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use App\Services\ClientPackageService;
use App\Services\ClientService;
use App\Services\ServicePackageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAgencySeeder extends Seeder
{
    public function run(): void
    {
        // Demo password for all seeded accounts: password
        $super = $this->seedUser(
            'superadmin@digicrm.test',
            'Super Admin',
            'Owner',
            ['super_admin']
        );

        $admin = $this->seedUser(
            'admin@digicrm.test',
            'Agency Admin',
            'Administrator',
            ['admin']
        );

        $manager = $this->seedUser(
            'manager@digicrm.test',
            'Agency Manager',
            'People Manager',
            ['admin', 'manager']
        );

        $am = $this->seedUser(
            'am@digicrm.test',
            'Priya Account Manager',
            'Account Manager',
            ['account_manager']
        );

        $employee = $this->seedUser(
            'employee@digicrm.test',
            'Demo Employee',
            'Team Member',
            ['team_member', 'employee']
        );

        $designer = $this->seedUser(
            'designer@digicrm.test',
            'Aman Designer',
            'Graphic Designer',
            ['team_member']
        );

        /** @var ClientService $clients */
        $clients = app(ClientService::class);
        /** @var ServicePackageService $packages */
        $packages = app(ServicePackageService::class);
        /** @var ClientPackageService $clientPackages */
        $clientPackages = app(ClientPackageService::class);

        $growth = \App\Models\ServicePackage::where('code', 'SM-GROWTH')->first();
        if (! $growth) {
            $growth = $packages->create([
                'name' => 'Social Media Growth',
                'code' => 'SM-GROWTH',
                'description' => 'Monthly social media retainer',
                'billing_cycle' => 'monthly',
                'base_price' => '35000.00',
                'currency' => 'INR',
                'status' => 'active',
                'items' => [
                    ['service_name' => 'Static Post', 'service_code' => 'STATIC', 'quantity' => 12, 'unit' => 'Post', 'overage_rate' => 800, 'is_unlimited' => false],
                    ['service_name' => 'Reel', 'service_code' => 'REEL', 'quantity' => 8, 'unit' => 'Reel', 'overage_rate' => 2000, 'is_unlimited' => false],
                    ['service_name' => 'Story', 'service_code' => 'STORY', 'quantity' => 20, 'unit' => 'Story', 'overage_rate' => 300, 'is_unlimited' => false],
                    ['service_name' => 'Photoshoot', 'service_code' => 'SHOOT', 'quantity' => 1, 'unit' => 'Shoot', 'overage_rate' => 15000, 'is_unlimited' => false],
                    ['service_name' => 'Meta Ads Management', 'service_code' => 'META', 'quantity' => 0, 'unit' => 'Month', 'overage_rate' => 0, 'is_unlimited' => true],
                ],
            ], $super);
        }

        $abc = Client::where('company_name', 'ABC Developers')->first();
        if (! $abc) {
            $abc = $clients->create([
                'company_name' => 'ABC Developers',
                'contact_person' => 'Ravi Sharma',
                'email' => 'ravi@abcdevelopers.test',
                'mobile' => '9876543210',
                'website' => 'https://example.com',
                'industry' => 'Real Estate',
                'city' => 'Jaipur',
                'state' => 'Rajasthan',
                'country' => 'India',
                'account_manager_id' => $am->id,
                'status' => 'onboarding',
                'start_date' => now()->toDateString(),
                'notes' => 'Demo client for DigiCRM.',
            ], $super);
        }

        if (! Client::where('company_name', 'Glow Beauty Studio')->exists()) {
            $clients->create([
                'company_name' => 'Glow Beauty Studio',
                'contact_person' => 'Neha Kapoor',
                'email' => 'neha@glowbeauty.test',
                'mobile' => '9123456780',
                'industry' => 'Beauty',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'India',
                'account_manager_id' => $am->id,
                'status' => 'active',
                'start_date' => now()->subMonths(2)->toDateString(),
                'renewal_date' => now()->addMonths(10)->toDateString(),
            ], $super);
        }

        if (! $abc->packages()->exists()) {
            $clientPackages->assign($abc, [
                'service_package_id' => $growth->id,
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
                'agreed_price' => '32000.00',
                'currency' => 'INR',
                'billing_cycle' => 'monthly',
                'auto_renew' => true,
                'renewal_type' => 'auto',
            ], $super);
        }
    }

    /**
     * @param  list<string>  $roles
     */
    protected function seedUser(string $email, string $name, string $jobTitle, array $roles): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'job_title' => $jobTitle,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $user->syncRoles($roles);

        return $user;
    }
}
