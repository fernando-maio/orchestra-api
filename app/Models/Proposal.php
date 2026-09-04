<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Proposal extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'quote_request_id',
        'vendor_id',
        'total_value',
        'labor_value',
        'material_value',
        'transport_value',
        'taxes_value',
        'discount_value',
        'currency',
        'payment_terms',
        'payment_days',
        'payment_conditions',
        'delivery_date',
        'setup_hours',
        'delivery_notes',
        'description',
        'items',
        'included_services',
        'excluded_services',
        'observations',
        'pdf_path',
        'ai_extracted_data',
        'locked_value',
        'locked_at',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'valid_until',
    ];

    protected $casts = [
        'total_value' => 'decimal:2',
        'labor_value' => 'decimal:2',
        'material_value' => 'decimal:2',
        'transport_value' => 'decimal:2',
        'taxes_value' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'locked_value' => 'decimal:2',
        'payment_days' => 'integer',
        'setup_hours' => 'integer',
        'delivery_date' => 'date',
        'valid_until' => 'date',
        'locked_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'items' => 'array',
        'included_services' => 'array',
        'excluded_services' => 'array',
        'ai_extracted_data' => 'array',
    ];

    // Relationships
    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ProposalHistory::class);
    }

    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeContracted(Builder $query): Builder
    {
        return $query->where('status', 'contracted');
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('valid_until')
                ->orWhere('valid_until', '>=', now()->toDateString());
        });
    }

    // Helpers
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    public function canBeEdited(): bool
    {
        return ! $this->isLocked() && in_array($this->status, ['draft', 'pending']);
    }

    public function lock(): void
    {
        if (! $this->isLocked()) {
            $this->locked_value = $this->total_value;
            $this->locked_at = now();
            $this->save();
        }
    }

    public function approve(User $reviewer, ?string $notes = null): void
    {
        DB::transaction(function () use ($reviewer, $notes) {
            $this->status = 'approved';
            $this->reviewed_by = $reviewer->id;
            $this->reviewed_at = now();
            $this->review_notes = $notes;
            $this->save();

            $this->logHistory('approved', $reviewer);
        });
    }

    public function reject(User $reviewer, ?string $notes = null): void
    {
        DB::transaction(function () use ($reviewer, $notes) {
            $this->status = 'rejected';
            $this->reviewed_by = $reviewer->id;
            $this->reviewed_at = now();
            $this->review_notes = $notes;
            $this->save();

            $this->logHistory('rejected', $reviewer);
        });
    }

    public function contract(?User $user = null): void
    {
        DB::transaction(function () use ($user) {
            $this->lock();
            $this->status = 'contracted';
            $this->save();

            $this->logHistory('contracted', $user);
        });
    }

    protected function logHistory(string $action, ?User $user = null): void
    {
        $this->history()->create([
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $action,
            'new_values' => $this->toArray(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
