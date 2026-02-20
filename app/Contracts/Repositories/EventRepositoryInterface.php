<?php

namespace App\Contracts\Repositories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface EventRepositoryInterface extends BaseRepositoryInterface
{
    public function getAllForOrganization(string $organizationId): Collection;

    public function paginateWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function getUpcoming(string $organizationId, int $limit = 5): Collection;

    public function getByStatus(string $organizationId, string $status): Collection;

    public function getWithQuoteRequests(string $id): ?Event;
}
