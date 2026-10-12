<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($this->getTable().'.company_id', $companyId);
    }

    public function scopeVisibleToHrUser(Builder $query, User $user): Builder
    {
        if ($user->can('hr.view_all') || $user->isSuperAdmin()) {
            return $query;
        }

        $employee = $user->employee;
        if (! $employee) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->getTable().'.company_id', $employee->company_id);
    }
}
