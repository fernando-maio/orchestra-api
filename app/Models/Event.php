<?php

namespace App\Models;

use App\Traits\BelongsToOrganization;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, HasUuid, SoftDeletes, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'created_by',
        'name',
        'description',
        'briefing',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'venue_name',
        'address',
        'city',
        'state',
        'zip_code',
        'latitude',
        'longitude',
        'estimated_budget',
        'actual_budget',
        'currency',
        'expected_attendees',
        'status',
        'required_categories',
        'settings',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'estimated_budget' => 'decimal:2',
        'actual_budget' => 'decimal:2',
        'expected_attendees' => 'integer',
        'required_categories' => 'array',
        'settings' => 'array',
    ];

    // Relationships
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function quoteRequests(): HasMany
    {
        return $this->hasMany(QuoteRequest::class);
    }

    public function proposals(): HasManyThrough
    {
        return $this->hasManyThrough(Proposal::class, QuoteRequest::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(VendorRating::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_date', '>=', now()->toDateString());
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('start_date', '<', now()->toDateString());
    }

    // Helpers
    public function getContractedBudget(): float
    {
        return $this->proposals()
            ->where('proposals.status', 'contracted')
            ->sum('locked_value');
    }

    public function getBudgetProgress(): float
    {
        if (!$this->estimated_budget || $this->estimated_budget == 0) {
            return 0;
        }

        return ($this->getContractedBudget() / $this->estimated_budget) * 100;
    }

    public function getVendorProgress(): array
    {
        $totalCategories = count($this->required_categories ?? []);
        $contractedCategories = $this->quoteRequests()
            ->whereHas('proposals', fn($q) => $q->where('proposals.status', 'contracted'))
            ->distinct('category_id')
            ->count();

        return [
            'total' => $totalCategories,
            'contracted' => $contractedCategories,
            'percentage' => $totalCategories > 0 ? ($contractedCategories / $totalCategories) * 100 : 0,
        ];
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'active']);
    }
}
