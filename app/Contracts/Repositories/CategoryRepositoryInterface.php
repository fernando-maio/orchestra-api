<?php

namespace App\Contracts\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

interface CategoryRepositoryInterface extends BaseRepositoryInterface
{
    public function getAllActive(): Collection;

    public function getAllOrdered(): Collection;

    public function findBySlug(string $slug): ?Category;
}
