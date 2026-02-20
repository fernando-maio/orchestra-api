<?php

namespace App\Contracts\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

interface CategoryServiceInterface extends BaseServiceInterface
{
    public function getAllActive(): Collection;

    public function getAllOrdered(): Collection;

    public function findBySlug(string $slug): ?Category;

    public function toggleActive(string $id): Category;

    public function reorder(array $orderedIds): bool;
}
