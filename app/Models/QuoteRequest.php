<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuoteRequest extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'event_id',
        'category_id',
        'created_by',
        'title',
        'technical_description',
        'specifications',
        'budget_min',
        'budget_max',
        'response_deadline',
        'delivery_deadline',
        'status',
        'max_proposals',
        'is_urgent',
        'requirements',
    ];

    protected $casts = [
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'response_deadline' => 'datetime',
        'delivery_deadline' => 'datetime',
        'max_proposals' => 'integer',
        'is_urgent' => 'boolean',
        'requirements' => 'array',
    ];

    // Relationships
    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<Vendor, $this>
     */
    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(Vendor::class, 'quote_request_vendor')
            ->withPivot(['id', 'token', 'token_expires_at', 'status', 'sent_at', 'viewed_at', 'responded_at', 'send_attempts', 'send_error'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Proposal, $this>
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    // Scopes
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeUrgent(Builder $query): Builder
    {
        return $query->where('is_urgent', true);
    }

    public function scopeExpiringSoon(Builder $query, int $days = 3): Builder
    {
        return $query->where('response_deadline', '<=', now()->addDays($days))
            ->where('response_deadline', '>', now());
    }

    // Helpers
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isExpired(): bool
    {
        return $this->response_deadline && $this->response_deadline->isPast();
    }

    public function canAcceptProposals(): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        if ($this->max_proposals && $this->proposals()->count() >= $this->max_proposals) {
            return false;
        }

        return true;
    }

    public function getProposalsStats(): array
    {
        $proposals = $this->proposals;

        return [
            'total' => $proposals->count(),
            'pending' => $proposals->where('status', 'pending')->count(),
            'under_review' => $proposals->where('status', 'under_review')->count(),
            'approved' => $proposals->where('status', 'approved')->count(),
            'contracted' => $proposals->where('status', 'contracted')->count(),
            'min_value' => $proposals->min('total_value'),
            'max_value' => $proposals->max('total_value'),
            'avg_value' => $proposals->avg('total_value'),
        ];
    }
}
