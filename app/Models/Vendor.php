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

class Vendor extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'trade_name',
        'legal_name',
        'cnpj',
        'email',
        'phone',
        'whatsapp',
        'website',
        'address',
        'city',
        'state',
        'zip_code',
        'latitude',
        'longitude',
        'service_radius_km',
        'logo_path',
        'description',
        'accepts_urgent',
        'is_verified',
        'average_rating',
        'total_ratings',
        'is_local_business',
        'is_sustainable',
        'is_minority_owned',
        'is_active',
        // Marketplace fields
        'approval_status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'source',
        'invite_token',
        'invite_expires_at',
        'invited_by',
        'subscription_tier',
        'subscription_expires_at',
        'contact_name',
        'contact_email',
        'contact_phone',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'service_radius_km' => 'integer',
        'accepts_urgent' => 'boolean',
        'is_verified' => 'boolean',
        'average_rating' => 'decimal:2',
        'total_ratings' => 'integer',
        'is_local_business' => 'boolean',
        'is_sustainable' => 'boolean',
        'is_minority_owned' => 'boolean',
        'is_active' => 'boolean',
        // Marketplace casts
        'approved_at' => 'datetime',
        'invite_expires_at' => 'datetime',
        'subscription_expires_at' => 'datetime',
    ];

    // Relationships
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    public function quoteRequests(): BelongsToMany
    {
        return $this->belongsToMany(QuoteRequest::class)
            ->withPivot(['token', 'token_expires_at', 'status', 'sent_at', 'viewed_at', 'responded_at'])
            ->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VendorDocument::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(VendorRating::class);
    }

    // Relationships for approval/invite
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopeAcceptsUrgent(Builder $query): Builder
    {
        return $query->where('accepts_urgent', true);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopePublicVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('approval_status', 'approved');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->whereIn('subscription_tier', ['featured', 'premium'])
            ->where(function ($q) {
                $q->whereNull('subscription_expires_at')
                    ->orWhere('subscription_expires_at', '>', now());
            });
    }

    public function scopePremium(Builder $query): Builder
    {
        return $query->where('subscription_tier', 'premium')
            ->where(function ($q) {
                $q->whereNull('subscription_expires_at')
                    ->orWhere('subscription_expires_at', '>', now());
            });
    }

    public function scopeInCategory(Builder $query, string $categoryId): Builder
    {
        return $query->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId));
    }

    /**
     * Scope to find vendors within a radius of a given point using Haversine formula
     */
    public function scopeWithinRadius(Builder $query, float $latitude, float $longitude, int $radiusKm): Builder
    {
        $haversine = '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))';

        return $query
            ->selectRaw("*, {$haversine} AS distance", [$latitude, $longitude, $latitude])
            ->whereRaw("{$haversine} <= service_radius_km", [$latitude, $longitude, $latitude])
            ->whereRaw("{$haversine} <= ?", [$latitude, $longitude, $latitude, $radiusKm])
            ->orderBy('distance');
    }

    /**
     * Scope for ESG filters
     */
    public function scopeEsgFiltered(Builder $query, array $filters): Builder
    {
        if (! empty($filters['local'])) {
            $query->where('is_local_business', true);
        }
        if (! empty($filters['sustainable'])) {
            $query->where('is_sustainable', true);
        }
        if (! empty($filters['minority_owned'])) {
            $query->where('is_minority_owned', true);
        }

        return $query;
    }

    // Helpers
    public function hasValidCompliance(): bool
    {
        $requiredDocs = ['cnpj_card', 'alvara'];

        foreach ($requiredDocs as $docType) {
            $doc = $this->documents()
                ->where('type', $docType)
                ->where('status', 'verified')
                ->where(function ($query) {
                    $query->whereNull('expiry_date')
                        ->orWhere('expiry_date', '>', now());
                })
                ->first();

            if (! $doc) {
                return false;
            }
        }

        return true;
    }

    public function updateRating(): void
    {
        $this->recalculateRating();
    }

    public function recalculateRating(): void
    {
        $activeRatings = $this->ratings()->active()->get();

        if ($activeRatings->isEmpty()) {
            $this->average_rating = 0;
            $this->total_ratings = 0;
            $this->save();

            return;
        }

        // Weighted average based on contract value
        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($activeRatings as $rating) {
            $weight = log10(($rating->contract_value ?? 0) + 1) + 1; // +1 to ensure minimum weight of 1
            $weightedSum += $rating->overall_rating * $weight;
            $totalWeight += $weight;
        }

        $this->average_rating = $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : 0;
        $this->total_ratings = $activeRatings->count();
        $this->save();
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function approve(User $approver): void
    {
        $this->update([
            'approval_status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    public function reject(User $approver, string $reason): void
    {
        $this->update([
            'approval_status' => 'rejected',
            'rejection_reason' => $reason,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }
}
