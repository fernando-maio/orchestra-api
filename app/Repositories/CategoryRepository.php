<?php

namespace App\Repositories;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

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

    public function findBySlug(string $slug): ?Category
    {
        return $this->model->where('slug', $slug)->first();
    }
}
