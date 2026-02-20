<?php

namespace App\Traits;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToOrganization
{
    /**
     * Boot the trait
     */
    protected static function bootBelongsToOrganization(): void
    {
        // Apply global scope to filter by organization
        static::addGlobalScope('organization', function (Builder $builder) {
            if (!Auth::check()) {
                // Not authenticated: return nothing for safety
                $builder->whereRaw('1 = 0');
                return;
            }

            $user = Auth::user();

            // SuperAdmin can see all records
            if ($user->hasRole('super-admin')) {
                return;
            }

            // Filter by user's organization
            if ($user->organization_id) {
                $builder->where('organization_id', $user->organization_id);
            } else {
                // Authenticated but no organization: return nothing
                $builder->whereRaw('1 = 0');
            }
        });

        // Automatically set organization_id when creating
        static::creating(function ($model) {
            if (Auth::check() && empty($model->organization_id)) {
                $user = Auth::user();
                if ($user->organization_id) {
                    $model->organization_id = $user->organization_id;
                }
            }
        });
    }

    /**
     * Get the organization that owns the model
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scope to filter by organization
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
