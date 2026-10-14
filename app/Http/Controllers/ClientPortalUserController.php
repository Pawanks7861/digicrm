<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ClientPortalUserController extends Controller
{
    public const FLAGS = [
        'can_approve_content',
        'can_submit_requests',
        'can_comment_portal',
        'can_view_finance',
        'can_accept_quotations',
    ];

    public function __construct(protected ActivityLogger $activity)
    {
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeManage($request, $client);
        $data = $this->validated($request);

        $user = DB::transaction(function () use ($data, $client, $request) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'password' => Hash::make($data['password']),
                'client_id' => $client->id,
                'is_active' => $request->boolean('is_active', true),
                'email_verified_at' => now(),
                ...$this->flags($request),
            ]);
            $user->assignRole('client');

            return $user;
        });

        $this->activity->log('portal_user_created', 'clients', $client, null, [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return redirect()->route('clients.show', [$client, 'tab' => 'credentials'])
            ->with('success', "Portal login created for {$user->name}.");
    }

    public function update(Request $request, Client $client, User $portalUser): RedirectResponse
    {
        $this->authorizeManage($request, $client);
        $this->assertBelongsToClient($client, $portalUser);
        $data = $this->validated($request, $portalUser);

        $old = $portalUser->only(['name', 'email', 'is_active', ...self::FLAGS]);

        $portalUser->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'is_active' => $request->boolean('is_active'),
            ...$this->flags($request),
        ]);
        if (! empty($data['password'])) {
            $portalUser->password = Hash::make($data['password']);
        }
        $portalUser->save();

        $this->activity->log('portal_user_updated', 'clients', $client, $old, [
            ...$portalUser->only(['name', 'email', 'is_active', ...self::FLAGS]),
            'user_id' => $portalUser->id,
            'password_changed' => ! empty($data['password']),
        ]);

        return redirect()->route('clients.show', [$client, 'tab' => 'credentials'])
            ->with('success', "Portal login updated for {$portalUser->name}.");
    }

    protected function authorizeManage(Request $request, Client $client): void
    {
        $this->authorize('update', $client);
        abort_unless($request->user()->can('portal_settings.edit'), 403);
    }

    protected function assertBelongsToClient(Client $client, User $user): void
    {
        abort_unless((int) $user->client_id === (int) $client->id && $user->isClientUser(), 404);
    }

    protected function validated(Request $request, ?User $existing = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($existing?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'password' => [$existing ? 'nullable' : 'required', 'string', Password::min(8)],
            ...collect(self::FLAGS)->mapWithKeys(fn ($flag) => [$flag => ['nullable', 'boolean']])->all(),
        ]);
    }

    protected function flags(Request $request): array
    {
        return collect(self::FLAGS)->mapWithKeys(fn ($flag) => [$flag => $request->boolean($flag)])->all();
    }
}
