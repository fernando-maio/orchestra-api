<?php

namespace App\Contracts\Repositories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface VendorRepositoryInterface extends BaseRepositoryInterface
{
    public function getAllActive(): Collection;

    public function paginateWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function findByCnpj(string $cnpj): ?Vendor;

    public function existsByCnpj(string $cnpj): bool;

    public function existsByEmail(string $email): bool;

    public function findNearby(float $latitude, float $longitude, int $radiusKm, array $filters = []): Collection;

    public function findByCategory(string $categoryId): Collection;

    public function syncCategories(string $vendorId, array $categoryIds): void;
}
