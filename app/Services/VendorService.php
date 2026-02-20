<?php

namespace App\Services;

use App\Contracts\Repositories\VendorRepositoryInterface;
use App\Contracts\Services\VendorServiceInterface;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class VendorService extends BaseService implements VendorServiceInterface
{
    protected VendorRepositoryInterface $vendorRepository;

    public function __construct(VendorRepositoryInterface $repository)
    {
        parent::__construct($repository);
        $this->vendorRepository = $repository;
    }

    public function getAllActive(): Collection
    {
        return $this->vendorRepository->getAllActive();
    }

    public function paginateWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->vendorRepository->paginateWithFilters($filters, $perPage);
    }

    public function findByCnpj(string $cnpj): ?Vendor
    {
        return $this->vendorRepository->findByCnpj($cnpj);
    }

    public function findNearby(float $latitude, float $longitude, int $radiusKm, array $filters = []): Collection
    {
        return $this->vendorRepository->findNearby($latitude, $longitude, $radiusKm, $filters);
    }

    public function findByCategory(string $categoryId): Collection
    {
        return $this->vendorRepository->findByCategory($categoryId);
    }

    public function createWithCategories(array $data, array $categoryIds): Vendor
    {
        return DB::transaction(function () use ($data, $categoryIds) {
            $vendor = $this->vendorRepository->create($data);

            if (!empty($categoryIds)) {
                $this->vendorRepository->syncCategories($vendor->id, $categoryIds);
            }

            return $vendor->load('categories');
        });
    }

    public function updateWithCategories(string $id, array $data, array $categoryIds): Vendor
    {
        return DB::transaction(function () use ($id, $data, $categoryIds) {
            $vendor = $this->vendorRepository->update($id, $data);

            if (!empty($categoryIds)) {
                $this->vendorRepository->syncCategories($id, $categoryIds);
            }

            return $vendor->load('categories');
        });
    }

    public function toggleActive(string $id): Vendor
    {
        $vendor = $this->vendorRepository->findOrFail($id);
        $vendor->is_active = !$vendor->is_active;
        $vendor->save();

        return $vendor->fresh();
    }

    public function verify(string $id): Vendor
    {
        $vendor = $this->vendorRepository->findOrFail($id);
        $vendor->is_verified = true;
        $vendor->save();

        return $vendor->fresh();
    }

    public function getComplianceStatus(string $id): array
    {
        $vendor = $this->vendorRepository->findOrFail($id);
        $vendor->load(['documents' => function ($query) {
            $query->orderBy('type');
        }]);

        $requiredDocTypes = [
            'cnpj_card' => 'Cartão CNPJ',
            'alvara' => 'Alvará de Funcionamento',
            'social_contract' => 'Contrato Social',
        ];

        $compliance = [];
        foreach ($requiredDocTypes as $type => $label) {
            $doc = $vendor->documents->where('type', $type)->first();
            $compliance[$type] = [
                'label' => $label,
                'status' => $doc ? $doc->status : 'missing',
                'uploaded_at' => $doc?->created_at?->toISOString(),
                'expiry_date' => $doc?->expiry_date?->toISOString(),
                'is_expired' => $doc?->expiry_date ? $doc->expiry_date->isPast() : false,
            ];
        }

        return [
            'is_compliant' => $vendor->hasValidCompliance(),
            'documents' => $compliance,
            'total_documents' => $vendor->documents->count(),
        ];
    }
}
