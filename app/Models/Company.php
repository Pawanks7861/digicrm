<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'legal_name', 'timezone', 'default_currency', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function designations(): HasMany
    {
        return $this->hasMany(Designation::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public static function default(): self
    {
        $existing = static::query()->where('code', 'DEFAULT')->first();
        if ($existing) {
            return $existing;
        }

        $billing = BillingSetting::query()->first();

        return static::create([
            'code' => 'DEFAULT',
            'name' => $billing?->trading_name ?: ($billing?->legal_name ?: config('app.name', 'Agency')),
            'legal_name' => $billing?->legal_name,
            'timezone' => config('hrms.default_attendance_rules.timezone', 'Asia/Kolkata'),
            'default_currency' => $billing?->default_currency ?: 'INR',
            'is_active' => true,
        ]);
    }
}
