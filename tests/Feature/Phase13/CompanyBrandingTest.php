<?php

namespace Tests\Feature\Phase13;

use App\Models\BillingSetting;
use App\Services\CompanyBrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Phase13\Concerns\BuildsAgencyUsers;
use Tests\TestCase;

class CompanyBrandingTest extends TestCase
{
    use BuildsAgencyUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAgency();
        Storage::fake('public');
    }

    public function test_logo_is_embedded_for_documents_and_exposed_on_the_login_page(): void
    {
        $settings = BillingSetting::current();
        app(CompanyBrandingService::class)->storeLogo($settings, UploadedFile::fake()->image('logo.png', 64, 64));

        $html = view('pdf.partials.logo', ['settings' => $settings->fresh()])->render();
        $this->assertStringContainsString('data:image/png;base64', $html);

        $empty = view('pdf.partials.logo', ['settings' => new BillingSetting])->render();
        $this->assertStringNotContainsString('<img', $empty);

        $this->get(route('login'))->assertOk();
    }

    public function test_login_page_receives_the_current_logo_url(): void
    {
        $settings = BillingSetting::current();
        app(CompanyBrandingService::class)->storeLogo($settings, UploadedFile::fake()->image('logo.jpg', 40, 40));

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('branding.logo_url', fn ($url) => is_string($url) && $url !== ''));
    }
}
