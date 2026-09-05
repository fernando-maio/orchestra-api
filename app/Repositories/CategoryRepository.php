<?php

namespace App\Repositories;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Category>
 */
class CategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    public function __construct(Category $model)
    {
        parent::__construct($model);
    }

    public function getAllActive(): Collection
    {
        return $this->model->active()->ordered()->get();
    }

    public function getAllOrdered(): Collection
    {
        return $this->model->ordered()->get();
    }

    public function updateSortOrder(string $id, int $sortOrder): void
    {
        $this->model->newQuery()->where('id', $id)->update(['sort_order' => $sortOrder]);
    }

    public function findBySlug(string $slug): ?Category
    {
        return $this->model->where('slug', $slug)->first();
    }
}
