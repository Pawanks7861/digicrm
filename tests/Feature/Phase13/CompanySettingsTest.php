<?php

namespace Tests\Feature\Phase13;

use App\Models\ActivityLog;
use App\Models\BillingSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Phase13\Concerns\BuildsAgencyUsers;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use BuildsAgencyUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAgency();
        Storage::fake('public');
    }

    public function test_authorized_user_uploads_logo_and_replaces_the_previous_file(): void
    {
        $admin = $this->makeUser('super_admin');
        $first = UploadedFile::fake()->image('logo.png', 120, 40);
        $this->actingAs($admin)->post(route('settings.company.logo'), ['logo' => $first])
            ->assertRedirect()->assertSessionHasNoErrors();

        $settings = BillingSetting::current();
        $old = $settings->logo_path;
        $this->assertNotNull($old);
        Storage::disk('public')->assertExists($old);
        $this->assertTrue(ActivityLog::where('action', 'logo_changed')->exists());

        $second = UploadedFile::fake()->image('next.png', 80, 80);
        $this->actingAs($admin)->post(route('settings.company.logo'), ['logo' => $second])
            ->assertRedirect()->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($old);
        $this->assertNotSame($old, $settings->fresh()->logo_path);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('branding.logo_url', fn ($url) => is_string($url) && str_contains($url, 'v=')));
    }

    public function test_invalid_logo_is_rejected(): void
    {
        $admin = $this->makeUser('super_admin');
        $this->actingAs($admin)->post(route('settings.company.logo'), [
            'logo' => UploadedFile::fake()->create('payload.exe', 20, 'application/octet-stream'),
        ])->assertSessionHasErrors('logo');

        $svg = UploadedFile::fake()->createWithContent('mark.svg', '<svg onload="alert(1)"></svg>');
        $this->actingAs($admin)->post(route('settings.company.logo'), ['logo' => $svg])
            ->assertSessionHasErrors('logo');
        $this->assertNull(BillingSetting::query()->first()?->logo_path);
    }

    public function test_favicon_upload_is_versioned_and_square(): void
    {
        $admin = $this->makeUser('super_admin');
        $this->actingAs($admin)->post(route('settings.company.favicon'), [
            'favicon' => UploadedFile::fake()->image('icon.png', 32, 16),
        ])->assertSessionHasErrors('favicon');

        $this->actingAs($admin)->post(route('settings.company.favicon'), [
            'favicon' => UploadedFile::fake()->image('icon.png', 32, 32),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $settings = BillingSetting::current();
        $this->assertNotNull($settings->favicon_path);
        $this->assertTrue(ActivityLog::where('action', 'favicon_changed')->exists());

        $this->actingAs($admin)->delete(route('settings.company.favicon.remove'))->assertRedirect();
        $this->assertNull($settings->fresh()->favicon_path);
        $this->assertTrue(ActivityLog::where('action', 'favicon_removed')->exists());
    }

    public function test_unauthorized_user_cannot_modify_branding_unless_granted(): void
    {
        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->get(route('settings.company'))->assertForbidden();
        $this->actingAs($admin)->post(route('settings.company.logo'), [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertForbidden();

        $admin->givePermissionTo(['company_settings.view', 'company_branding.manage']);
        $this->actingAs($admin)->post(route('settings.company.logo'), [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_company_details_update_reuses_billing_record_without_clearing_prefixes(): void
    {
        $admin = $this->makeUser('super_admin');
        $settings = BillingSetting::current();
        $settings->update(['quotation_prefix' => 'QT', 'legal_name' => 'Old Legal']);

        $this->actingAs($admin)->put(route('settings.company.update'), [
            'trading_name' => 'Digi Studio',
            'legal_name' => 'Digi Studio Pvt Ltd',
            'email' => 'hello@digi.test',
            'city' => 'Ahmedabad',
            'gstin' => '24AAAAA0000A1Z5',
            'pan' => 'AAAAA0000A',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $settings->refresh();
        $this->assertSame('Digi Studio', $settings->trading_name);
        $this->assertSame('QT', $settings->quotation_prefix);
        $this->assertTrue(ActivityLog::where('action', 'company_information_updated')->exists());
    }
}
