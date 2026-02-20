<?php

namespace App\Contracts\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface BaseServiceInterface
{
    public function all(): Collection;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function find(int|string $id): ?Model;

    public function findOrFail(int|string $id): Model;

    public function create(array $data): Model;

    public function update(int|string $id, array $data): Model;

    public function delete(int|string $id): bool;
}
