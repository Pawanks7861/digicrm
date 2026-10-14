<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DeployPreflightCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_completes_without_process_helpers_fatal(): void
    {
        $code = Artisan::call('deploy:preflight');
        $output = Artisan::output();

        $this->assertStringNotContainsString('Call to undefined function', $output);
        $this->assertStringNotContainsString('App\\Console\\Commands\\exec', $output);
        $this->assertContains($code, [0, 1]);
    }

    public function test_preflight_still_reports_database_ok_when_connected(): void
    {
        Artisan::call('deploy:preflight');
        $output = Artisan::output();

        $this->assertStringContainsString('Database connection', $output);
        $this->assertStringNotContainsString('× Database connection failed', $output);
    }
}
