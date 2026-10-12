<?php

namespace App\Http\Controllers;

use App\Models\BillingSetting;
use App\Services\ActivityLogger;
use App\Services\CompanyBrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    public function __construct(
        protected CompanyBrandingService $branding,
        protected ActivityLogger $activity
    ) {
    }

    public function edit(Request $request): Response
    {
        $this->authorizeView($request);
        $settings = BillingSetting::current();

        return Inertia::render('Settings/Company', [
            'settings' => $this->payload($settings),
            'can' => [
                'manage' => $request->user()->can('company_settings.manage'),
                'branding' => $request->user()->can('company_branding.manage'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('company_settings.manage'), 403);
        $data = $request->validate([
            'trading_name' => ['nullable', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'pin' => ['nullable', 'string', 'max:20'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'pan' => ['nullable', 'string', 'max:20'],
        ]);

        $settings = BillingSetting::current();
        $old = $settings->only(array_keys($data));
        $settings->update($data);
        $this->activity->log('company_information_updated', 'company', $settings, $old, $data);

        return back()->with('success', 'Company information saved.');
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('company_branding.manage'), 403);
        $request->validate(['logo' => ['required', 'file', 'max:5120']]);
        $settings = $this->branding->storeLogo(BillingSetting::current(), $request->file('logo'));
        $this->activity->log('logo_changed', 'company', $settings, null, [
            'filename' => $request->file('logo')->getClientOriginalName(),
        ]);

        return back()->with('success', 'Company logo updated.');
    }

    public function removeLogo(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('company_branding.manage'), 403);
        $settings = $this->branding->removeLogo(BillingSetting::current());
        $this->activity->log('logo_removed', 'company', $settings);

        return back()->with('success', 'Company logo removed.');
    }

    public function uploadFavicon(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('company_branding.manage'), 403);
        $request->validate(['favicon' => ['required', 'file', 'max:1024']]);
        $settings = $this->branding->storeFavicon(BillingSetting::current(), $request->file('favicon'));
        $this->activity->log('favicon_changed', 'company', $settings, null, [
            'filename' => $request->file('favicon')->getClientOriginalName(),
        ]);

        return back()->with('success', 'Favicon updated.');
    }

    public function removeFavicon(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('company_branding.manage'), 403);
        $settings = $this->branding->removeFavicon(BillingSetting::current());
        $this->activity->log('favicon_removed', 'company', $settings);

        return back()->with('success', 'Favicon removed.');
    }

    protected function authorizeView(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->can('company_settings.view') || $user->can('company_settings.manage') || $user->can('company_branding.manage'),
            403
        );
    }

    protected function payload(BillingSetting $settings): array
    {
        return [
            'trading_name' => $settings->trading_name,
            'legal_name' => $settings->legal_name,
            'email' => $settings->email,
            'phone' => $settings->phone,
            'website' => $settings->website,
            'address' => $settings->address,
            'city' => $settings->city,
            'state' => $settings->state,
            'country' => $settings->country,
            'pin' => $settings->pin,
            'gstin' => $settings->gstin,
            'pan' => $settings->pan,
            'logo_url' => $this->branding->publicUrl($settings->logo_path, $settings->updated_at),
            'favicon_url' => $this->branding->publicUrl($settings->favicon_path, $settings->updated_at),
        ];
    }
}
