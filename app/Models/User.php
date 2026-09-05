<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, HasUuid, Notifiable, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'phone',
        'password',
        'avatar_path',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    /**
     * O generic no retorno permite que a analise estatica enxergue os metodos
     * de Organization em $user->organization->...  Sem ele o PHPStan so ve
     * Eloquent\Model e acusa metodo inexistente.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    /**
     * @return HasMany<QuoteRequest, $this>
     */
    public function quoteRequests(): HasMany
    {
        return $this->hasMany(QuoteRequest::class, 'created_by');
    }

    /**
     * @return HasMany<Proposal, $this>
     */
    public function proposalReviews(): HasMany
    {
        return $this->hasMany(Proposal::class, 'reviewed_by');
    }

    // Helpers
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function isOrganizationAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function canAccessOrganization(string $organizationId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->organization_id === $organizationId;
    }
}
