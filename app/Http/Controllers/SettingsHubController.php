<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsHubController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $cards = array_values(array_filter([
            ($user->can('company_settings.view') || $user->can('company_settings.manage') || $user->can('company_branding.manage')) ? [
                'title' => 'Company',
                'body' => 'Name, address, logo, and favicon.',
                'href' => route('settings.company'),
            ] : null,
            ($user->can('users.view') || $user->can('users.manage')) ? [
                'title' => 'Users',
                'body' => 'Create accounts, assign roles, and deactivate access.',
                'href' => route('settings.users.index'),
            ] : null,
            $user->can('roles.manage') ? [
                'title' => 'Menu access',
                'body' => 'Choose which sidebar menus each role sees. Override per user from the Users list.',
                'href' => route('settings.menu-access.index'),
            ] : null,
            ($user->can('audit_logs.view') || $user->can('users.manage')) ? [
                'title' => 'Audit log',
                'body' => 'Sign-ins and what each person changed in the CRM.',
                'href' => route('settings.audit-logs.index'),
            ] : null,
        ]));

        abort_if($cards === [], 403);

        return Inertia::render('Settings/Index', ['cards' => $cards]);
    }
}
