<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkShift extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'category', 'shift_type', 'start_time', 'end_time', 'break_minutes',
        'lunch_break_minutes', 'tea_break_minutes', 'is_overnight', 'full_day_minutes', 'half_day_minutes',
        'grace_minutes', 'weekly_off_days', 'overtime_eligible', 'half_day_rule', 'late_half_day_cutoff_minutes',
        'effective_from', 'timezone', 'is_active',
    ];

    protected $casts = [
        'is_overnight' => 'boolean',
        'is_active' => 'boolean',
        'overtime_eligible' => 'boolean',
        'break_minutes' => 'integer',
        'lunch_break_minutes' => 'integer',
        'tea_break_minutes' => 'integer',
        'full_day_minutes' => 'integer',
        'half_day_minutes' => 'integer',
        'grace_minutes' => 'integer',
        'late_half_day_cutoff_minutes' => 'integer',
        'weekly_off_days' => 'array',
        'effective_from' => 'date',
    ];

    public function unpaidBreakMinutes(): int
    {
        $parts = (int) $this->lunch_break_minutes + (int) $this->tea_break_minutes;
        if ($parts > 0) {
            return $parts;
        }

        return (int) $this->break_minutes;
    }

    public function scheduledElapsedMinutes(): int
    {
        if (! $this->start_time || ! $this->end_time) {
            return (int) $this->full_day_minutes + $this->unpaidBreakMinutes();
        }
        $start = \Carbon\Carbon::parse('2000-01-01 '.$this->start_time);
        $end = \Carbon\Carbon::parse('2000-01-01 '.$this->end_time);
        if ($this->is_overnight || $this->shift_type === 'overnight' || $end->lte($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end);
    }
}
