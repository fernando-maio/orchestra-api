<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'name',
        'legal_name',
        'cnpj',
        'email',
        'phone',
        'logo_path',
        'address',
        'city',
        'state',
        'zip_code',
        'subscription_status',
        'subscription_ends_at',
        'subscription_plan',
        'white_label_enabled',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'subscription_ends_at' => 'datetime',
        'white_label_enabled' => 'boolean',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    // Relationships
    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return HasMany<Vendor, $this>
     */
    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    // Helpers
    public function isSubscriptionActive(): bool
    {
        if ($this->subscription_status === 'active') {
            return true;
        }

        if ($this->subscription_status === 'trial' && $this->subscription_ends_at) {
            return $this->subscription_ends_at->isFuture();
        }

        return false;
    }

    public function hasFeature(string $feature): bool
    {
        $planFeatures = [
            'basic' => ['events', 'vendors', 'rfp'],
            'professional' => ['events', 'vendors', 'rfp', 'compliance', 'reports'],
            'enterprise' => ['events', 'vendors', 'rfp', 'compliance', 'reports', 'api', 'webhooks', 'white_label'],
        ];

        return in_array($feature, $planFeatures[$this->subscription_plan] ?? []);
    }
}
