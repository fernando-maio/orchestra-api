<?php

namespace App\Services;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Services\CategoryServiceInterface;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CategoryService extends BaseService implements CategoryServiceInterface
{
    protected CategoryRepositoryInterface $categoryRepository;

    public function __construct(CategoryRepositoryInterface $repository)
    {
        parent::__construct($repository);
        $this->categoryRepository = $repository;
    }

    public function getAllActive(): Collection
    {
        return $this->categoryRepository->getAllActive();
    }

    public function getAllOrdered(): Collection
    {
        return $this->categoryRepository->getAllOrdered();
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->categoryRepository->findBySlug($slug);
    }

    public function toggleActive(string $id): Category
    {
        $category = $this->categoryRepository->findOrFail($id);
        $category->is_active = ! $category->is_active;
        $category->save();

        return $category->fresh();
    }

    public function reorder(array $orderedIds): bool
    {
        return DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                Category::where('id', $id)->update(['sort_order' => $index + 1]);
            }

            return true;
        });
    }
}
