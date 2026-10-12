<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id', 'name', 'code', 'campaign_type', 'objective', 'description',
        'start_date', 'end_date', 'budget', 'internal_cost', 'client_charge', 'standalone_charge', 'currency',
        'campaign_manager_id', 'status', 'priority', 'client_request_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget' => 'decimal:2',
        'internal_cost' => 'decimal:2',
        'client_charge' => 'decimal:2',
        'standalone_charge' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'campaign_manager_id');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(CampaignDeliverable::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public static function generateNextCode(): string
    {
        $year = now()->format('Y');
        $prefix = "CAM-{$year}-";

        $last = static::withTrashed()
            ->where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->value('code');

        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function progressSummary(): array
    {
        $deliverables = $this->relationLoaded('deliverables')
            ? $this->deliverables
            : $this->deliverables()->get();

        $total = $deliverables->count();
        $completed = $deliverables->whereIn('status', ['completed', 'ready_for_client_review'])->count();
        $inReview = $deliverables->whereIn('status', ['internal_review', 'changes_required'])->count();
        $pending = $total - $completed - $inReview;

        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->get();
        $taskTotal = $tasks->count();
        $taskCompleted = $tasks->where('status', 'completed')->count();
        $taskOverdue = $tasks->filter(fn ($t) => $t->isOverdue())->count();
        $taskActive = $tasks->whereNotIn('status', ['completed', 'cancelled'])->count();

        return [
            'deliverables' => [
                'total' => $total,
                'completed' => $completed,
                'in_review' => $inReview,
                'pending' => max(0, $pending),
                'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            ],
            'tasks' => [
                'total' => $taskTotal,
                'completed' => $taskCompleted,
                'overdue' => $taskOverdue,
                'active' => $taskActive,
            ],
        ];
    }
}
