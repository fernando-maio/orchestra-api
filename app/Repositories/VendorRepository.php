<?php

namespace App\Repositories;

use App\Contracts\Repositories\VendorRepositoryInterface;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class VendorRepository extends BaseRepository implements VendorRepositoryInterface
{
    public function __construct(Vendor $model)
    {
        parent::__construct($model);
    }

    public function getAllActive(): Collection
    {
        return $this->model->active()->with('categories')->get();
    }

    public function paginateWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with('categories');

        // Search by name or CNPJ
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('trade_name', 'like', "%{$search}%")
                    ->orWhere('legal_name', 'like', "%{$search}%")
                    ->orWhere('cnpj', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if (! empty($filters['category_id'])) {
            $query->inCategory($filters['category_id']);
        }

        // Filter by city
        if (! empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        // Filter by state
        if (! empty($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        // Filter by active status
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Filter by verified status
        if (isset($filters['is_verified'])) {
            $query->where('is_verified', $filters['is_verified']);
        }

        // Filter by urgent acceptance
        if (! empty($filters['accepts_urgent'])) {
            $query->acceptsUrgent();
        }

        // ESG filters
        if (! empty($filters['esg'])) {
            $query->esgFiltered($filters['esg']);
        }

        // Marketplace filters
        if (! empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['subscription_tier'])) {
            $query->where('subscription_tier', $filters['subscription_tier']);
        }

        // Sorting (whitelist to prevent SQL injection)
        $allowedSortColumns = ['trade_name', 'legal_name', 'city', 'state', 'average_rating', 'total_ratings', 'created_at', 'updated_at'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSortColumns) ? $filters['sort_by'] : 'trade_name';
        $sortDir = in_array(strtolower($filters['sort_dir'] ?? ''), ['asc', 'desc']) ? $filters['sort_dir'] : 'asc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    public function findByCnpj(string $cnpj): ?Vendor
    {
        return $this->model->where('cnpj', $cnpj)->first();
    }

    public function existsByCnpj(string $cnpj): bool
    {
        // O SoftDeletes ja exclui os removidos do escopo padrao, entao nao
        // precisa de whereNull('deleted_at') aqui.
        return $this->model->where('cnpj', $cnpj)->exists();
    }

    public function existsByEmail(string $email): bool
    {
        return $this->model->where('email', $email)->exists();
    }

    public function findNearby(float $latitude, float $longitude, int $radiusKm, array $filters = []): Collection
    {
        $query = $this->model
            ->active()
            ->withinRadius($latitude, $longitude, $radiusKm)
            ->with('categories');

        // Apply category filter if provided
        if (! empty($filters['category_id'])) {
            $query->inCategory($filters['category_id']);
        }

        // ESG filters
        if (! empty($filters['esg'])) {
            $query->esgFiltered($filters['esg']);
        }

        return $query->get();
    }

    public function findByCategory(string $categoryId): Collection
    {
        return $this->model->active()->inCategory($categoryId)->get();
    }

    public function syncCategories(string $vendorId, array $categoryIds): void
    {
        $vendor = $this->findOrFail($vendorId);
        $vendor->categories()->sync($categoryIds);
    }
}
