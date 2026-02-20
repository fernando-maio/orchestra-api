<?php

namespace App\Contracts\Services;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface VendorServiceInterface extends BaseServiceInterface
{
    public function getAllActive(): Collection;

    public function paginateWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function findByCnpj(string $cnpj): ?Vendor;

    public function findNearby(float $latitude, float $longitude, int $radiusKm, array $filters = []): Collection;

    public function findByCategory(string $categoryId): Collection;

    public function createWithCategories(array $data, array $categoryIds): Vendor;

    public function updateWithCategories(string $id, array $data, array $categoryIds): Vendor;

    public function toggleActive(string $id): Vendor;

    public function verify(string $id): Vendor;

    public function getComplianceStatus(string $id): array;
}
