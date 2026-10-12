<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'job_title',
        'client_id',
        'is_active',
        'can_approve_content',
        'can_submit_requests',
        'can_comment_portal',
        'can_view_finance',
        'can_receive_finance_notifications',
        'can_accept_quotations',
        'notifications_paused',
        'notifications_paused_until',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'notification_timezone',
        'notification_sounds_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'is_active' => 'boolean',
        'can_approve_content' => 'boolean',
        'can_submit_requests' => 'boolean',
        'can_comment_portal' => 'boolean',
        'can_view_finance' => 'boolean',
        'can_receive_finance_notifications' => 'boolean',
        'can_accept_quotations' => 'boolean',
        'notifications_paused' => 'boolean',
        'notifications_paused_until' => 'datetime',
        'quiet_hours_enabled' => 'boolean',
        'notification_sounds_enabled' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function managedClients(): HasMany
    {
        return $this->hasMany(Client::class, 'account_manager_id');
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function getRoleNamesAttribute(): array
    {
        return $this->getRoleNames()->values()->all();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin']);
    }

    public function isAccountManager(): bool
    {
        return $this->hasRole('account_manager');
    }

    public function isTeamMember(): bool
    {
        return $this->hasRole('team_member');
    }

    public function isClientUser(): bool
    {
        return $this->hasRole('client');
    }

    public function canViewAllClients(): bool
    {
        return $this->can('clients.view_all') || $this->isSuperAdmin();
    }
}
