<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientBrandProfile;
use App\Models\ClientContact;
use App\Models\ClientFile;
use App\Models\ClientOnboardingItem;
use App\Models\ClientOnboardingTemplate;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClientService
{
    public function __construct(
        protected ActivityLogger $activityLogger
    ) {
    }

    public function listFor(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Client::query()
            ->with(['accountManager:id,name,email'])
            ->withCount(['onboardingItems as onboarding_total'])
            ->withCount(['onboardingItems as onboarding_completed' => function ($q) {
                $q->where('is_completed', true);
            }]);

        if (! $user->canViewAllClients()) {
            if ($user->isAccountManager()) {
                $query->where('account_manager_id', $user->id);
            } elseif ($user->isClientUser() && $user->client_id) {
                $query->where('id', $user->client_id);
            } else {
                // Team members: no client list by default in Phase 1
                $query->whereRaw('1 = 0');
            }
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    public function create(array $data, User $actor): Client
    {
        return DB::transaction(function () use ($data, $actor) {
            $contacts = $data['contacts'] ?? [];
            unset($data['contacts']);

            $client = Client::create($data);

            ClientBrandProfile::create(['client_id' => $client->id]);

            $this->seedOnboarding($client);

            if ($client->contact_person) {
                ClientContact::create([
                    'client_id' => $client->id,
                    'name' => $client->contact_person,
                    'email' => $client->email,
                    'phone' => $client->mobile,
                    'is_primary' => true,
                ]);
            }

            foreach ($contacts as $contact) {
                $client->contacts()->create($contact);
            }

            $this->activityLogger->log('created', 'clients', $client, null, $client->toArray());

            return $client->fresh(['accountManager', 'brandProfile', 'onboardingItems', 'contacts']);
        });
    }

    public function update(Client $client, array $data): Client
    {
        $old = $client->toArray();
        $client->update($data);

        $this->activityLogger->log('updated', 'clients', $client, $old, $client->fresh()->toArray());

        return $client->fresh(['accountManager', 'brandProfile', 'onboardingItems', 'contacts', 'files']);
    }

    public function delete(Client $client): void
    {
        $this->activityLogger->log('deleted', 'clients', $client, $client->toArray(), null);
        $client->delete();
    }

    public function updateBrand(Client $client, array $data): ClientBrandProfile
    {
        $profile = $client->brandProfile()->firstOrCreate(['client_id' => $client->id]);
        $old = $profile->toArray();
        $profile->update($data);

        $this->activityLogger->log('brand_updated', 'clients', $client, $old, $profile->fresh()->toArray());

        return $profile->fresh();
    }

    public function seedOnboarding(Client $client): void
    {
        if ($client->onboardingItems()->exists()) {
            return;
        }

        $templates = ClientOnboardingTemplate::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($templates as $index => $template) {
            ClientOnboardingItem::create([
                'client_id' => $client->id,
                'label' => $template->label,
                'sort_order' => $template->sort_order ?: $index,
                'is_custom' => false,
            ]);
        }
    }

    public function toggleOnboardingItem(ClientOnboardingItem $item, User $actor, bool $completed): ClientOnboardingItem
    {
        $item->update([
            'is_completed' => $completed,
            'completed_at' => $completed ? now() : null,
            'completed_by' => $completed ? $actor->id : null,
        ]);

        $this->activityLogger->log(
            $completed ? 'onboarding_completed' : 'onboarding_reopened',
            'clients',
            $item->client,
            null,
            ['item' => $item->label, 'completed' => $completed]
        );

        return $item->fresh();
    }

    public function addOnboardingItem(Client $client, string $label): ClientOnboardingItem
    {
        $max = (int) $client->onboardingItems()->max('sort_order');

        $item = $client->onboardingItems()->create([
            'label' => $label,
            'sort_order' => $max + 1,
            'is_custom' => true,
        ]);

        $this->activityLogger->log('onboarding_item_added', 'clients', $client, null, ['label' => $label]);

        return $item;
    }

    public function storeFile(Client $client, UploadedFile $file, User $actor, string $folder = 'Documents', string $visibility = 'internal'): ClientFile
    {
        $folder = in_array($folder, ClientFile::FOLDERS, true) ? $folder : 'Documents';
        $stored = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("clients/{$client->id}/".Str::slug($folder), $stored, 'public');

        $record = $client->files()->create([
            'folder' => $folder,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $stored,
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'visibility' => $visibility === 'client' ? 'client' : 'internal',
            'uploaded_by' => $actor->id,
        ]);

        $this->activityLogger->log('file_uploaded', 'clients', $client, null, [
            'file' => $record->original_name,
            'folder' => $folder,
            'visibility' => $record->visibility,
        ], $record->visibility);

        return $record;
    }
}
