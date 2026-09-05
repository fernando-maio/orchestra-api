<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorRating extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'vendor_id',
        'event_id',
        'proposal_id',
        'organization_id',
        'rated_by',
        'punctuality_rating',
        'quality_rating',
        'communication_rating',
        'price_rating',
        'overall_rating',
        'comment',
        'would_hire_again',
        'is_public',
        'contract_value',
        'status',
        'dispute_reason',
        'dispute_resolved_by',
        'disputed_at',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'punctuality_rating' => 'integer',
        'quality_rating' => 'integer',
        'communication_rating' => 'integer',
        'price_rating' => 'integer',
        'overall_rating' => 'decimal:2',
        'would_hire_again' => 'boolean',
        'is_public' => 'boolean',
        'contract_value' => 'decimal:2',
        'disputed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($rating) {
            $rating->overall_rating = $rating->calculateOverallRating();
        });

        static::updating(function ($rating) {
            if ($rating->isDirty(['punctuality_rating', 'quality_rating', 'communication_rating', 'price_rating'])) {
                $rating->overall_rating = $rating->calculateOverallRating();
            }
        });

        static::saved(function ($rating) {
            $rating->vendor->updateRating();
        });
    }

    // Relationships
    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Proposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispute_resolved_by');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeDisputed(Builder $query): Builder
    {
        return $query->where('status', 'disputed');
    }

    public function scopePublicVisible(Builder $query): Builder
    {
        return $query->where('is_public', true)->where('status', 'active');
    }

    // Helpers
    public function calculateOverallRating(): float
    {
        return round(
            ($this->punctuality_rating + $this->quality_rating + $this->communication_rating + $this->price_rating) / 4,
            2
        );
    }

    public function getWeightedScore(): float
    {
        $weight = log10(($this->contract_value ?? 0) + 1);

        return $this->overall_rating * $weight;
    }

    public function dispute(string $reason): void
    {
        $this->update([
            'status' => 'disputed',
            'dispute_reason' => $reason,
            'disputed_at' => now(),
        ]);
    }

    public function resolveDispute(User $resolver, string $resolution, bool $remove = false): void
    {
        $this->update([
            'status' => $remove ? 'removed' : 'active',
            'dispute_resolved_by' => $resolver->id,
            'resolved_at' => now(),
            'resolution_notes' => $resolution,
        ]);
    }
}
