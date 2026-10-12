<?php

namespace Tests\Feature\Phase12;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Phase12\Concerns\BuildsLeads;
use Tests\TestCase;

class LeadImportTest extends TestCase
{
    use BuildsLeads, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLeads();
        Storage::fake('local');
    }

    protected function csv(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('leads.csv', $contents);
    }

    public function test_preview_then_import_counts_created_skipped_and_duplicates(): void
    {
        $am = $this->makeUser('account_manager');
        $client = Client::factory()->forManager($am)->create();
        $campaign = Campaign::factory()->create(['client_id' => $client->id]);
        $this->campaignLead($client, ['email' => 'existing@example.com']);

        $csv = "Full Name,Email Address,Phone,City,Lead ID\n"
            ."Asha Patel,asha@example.com,9876500001,Surat,L-1\n"
            ."Bad Email,not-an-email,,Surat,L-2\n"
            ."Existing Person,existing@example.com,,Vadodara,L-3\n"
            .",,,,\n";

        $preview = $this->actingAs($am)->postJson(route('leads.import.preview'), [
            'file' => $this->csv($csv),
            'lead_category' => Lead::CATEGORY_CAMPAIGN,
            'client_id' => $client->id,
            'campaign_id' => $campaign->id,
        ])->assertOk()->json();

        $this->assertSame(3, $preview['total']);
        $this->assertSame(2, $preview['valid']);
        $this->assertSame(1, $preview['invalid']);
        $this->assertSame(1, $preview['possible_duplicates']);
        $this->assertSame(1, Lead::count());

        $this->actingAs($am)->post(route('leads.import.store'), ['token' => $preview['token']])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(3, Lead::count());
        $asha = Lead::where('email', 'asha@example.com')->firstOrFail();
        $this->assertSame(Lead::CATEGORY_CAMPAIGN, $asha->lead_category);
        $this->assertSame($client->id, (int) $asha->client_id);
        $this->assertSame($campaign->id, (int) $asha->campaign_id);
        $this->assertSame(LeadSource::idFor('csv_import'), (int) $asha->lead_source_id);
        $this->assertSame('L-1', $asha->provider_lead_id);
        $this->assertNotNull(Lead::where('email', 'existing@example.com')->whereNotNull('possible_duplicate_of_id')->first());
    }

    public function test_reimporting_same_external_ids_is_idempotent(): void
    {
        $admin = $this->makeUser('admin');
        $csv = "name,email,external_id\nOne,one@example.com,X1\nTwo,two@example.com,X2\n";

        foreach ([1, 2] as $run) {
            $token = $this->actingAs($admin)->postJson(route('leads.import.preview'), [
                'file' => $this->csv($csv),
                'lead_category' => Lead::CATEGORY_AGENCY,
            ])->assertOk()->json('token');
            $this->actingAs($admin)->post(route('leads.import.store'), ['token' => $token])->assertRedirect();
        }

        $this->assertSame(2, Lead::count());
    }

    public function test_import_permissions_and_client_scope(): void
    {
        $team = $this->makeUser('team_member');
        $am = $this->makeUser('account_manager');
        $otherClient = Client::factory()->forManager($this->makeUser('account_manager'))->create();
        $csv = "name,email\nZed,zed@example.com\n";

        $this->actingAs($team)->postJson(route('leads.import.preview'), [
            'file' => $this->csv($csv), 'lead_category' => Lead::CATEGORY_AGENCY,
        ])->assertForbidden();
        $this->actingAs($team)->get(route('leads.import'))->assertForbidden();

        $this->actingAs($am)->postJson(route('leads.import.preview'), [
            'file' => $this->csv($csv), 'lead_category' => Lead::CATEGORY_CAMPAIGN, 'client_id' => $otherClient->id,
        ])->assertStatus(422);

        $this->actingAs($am)->postJson(route('leads.import.preview'), [
            'file' => $this->csv($csv), 'lead_category' => Lead::CATEGORY_CAMPAIGN,
        ])->assertStatus(422);

        $this->assertSame(0, Lead::count());
    }

    public function test_import_token_is_bound_to_the_uploading_user(): void
    {
        $admin = $this->makeUser('admin');
        $other = $this->makeUser('admin');
        $token = $this->actingAs($admin)->postJson(route('leads.import.preview'), [
            'file' => $this->csv("name,email\nA,a@example.com\n"), 'lead_category' => Lead::CATEGORY_AGENCY,
        ])->json('token');

        $this->actingAs($other)->post(route('leads.import.store'), ['token' => $token])->assertSessionHasErrors('token');
        $this->assertSame(0, Lead::count());
    }
}
