<?php

namespace App\Contracts\Services;

use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface EventServiceInterface extends BaseServiceInterface
{
    public function getAllForOrganization(string $organizationId): Collection;

    public function paginateWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function getUpcoming(string $organizationId, int $limit = 5): Collection;

    public function getByStatus(string $organizationId, string $status): Collection;

    public function getWithQuoteRequests(string $id): Event;

    public function updateStatus(string $id, string $status): Event;

    public function duplicate(string $id): Event;

    public function getStatistics(string $id): array;
}
