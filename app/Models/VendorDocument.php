<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorDocument extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'type',
        'name',
        'description',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'issue_date',
        'expiry_date',
        'status',
        'verified_by',
        'verified_at',
        'verification_notes',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'verified_at' => 'datetime',
    ];

    // Relationships
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // Scopes
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('status', 'verified');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expiry_date', '<', now()->toDateString());
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('expiry_date', '<=', now()->addDays($days)->toDateString())
            ->where('expiry_date', '>=', now()->toDateString());
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    // Helpers
    public function isValid(): bool
    {
        if ($this->status !== 'verified') {
            return false;
        }

        if ($this->expiry_date && $this->expiry_date->isPast()) {
            return false;
        }

        return true;
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->expiry_date) {
            return false;
        }

        return $this->expiry_date->isBetween(now(), now()->addDays($days));
    }
}
