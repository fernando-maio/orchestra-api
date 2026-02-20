<?php

namespace App\Repositories;

use App\Contracts\Repositories\EventRepositoryInterface;
use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EventRepository extends BaseRepository implements EventRepositoryInterface
{
    public function __construct(Event $model)
    {
        parent::__construct($model);
    }

    public function getAllForOrganization(string $organizationId): Collection
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function paginateWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['creator', 'organization']);

        // Filtro por organização (multi-tenancy)
        if (!empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        // Busca por nome ou descrição
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('venue_name', 'like', "%{$search}%");
            });
        }

        // Filtro por status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filtro por cidade
        if (!empty($filters['city'])) {
            $query->where('city', 'like', "%{$filters['city']}%");
        }

        // Filtro por estado
        if (!empty($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        // Filtro por período (data início)
        if (!empty($filters['start_from'])) {
            $query->where('start_date', '>=', $filters['start_from']);
        }
        if (!empty($filters['start_to'])) {
            $query->where('start_date', '<=', $filters['start_to']);
        }

        // Filtro por eventos futuros ou passados
        if (isset($filters['upcoming']) && $filters['upcoming']) {
            $query->upcoming();
        } elseif (isset($filters['past']) && $filters['past']) {
            $query->past();
        }

        return $query->orderBy('start_date', 'desc')->paginate($perPage);
    }

    public function getUpcoming(string $organizationId, int $limit = 5): Collection
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->upcoming()
            ->orderBy('start_date', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getByStatus(string $organizationId, string $status): Collection
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->where('status', $status)
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function getWithQuoteRequests(string $id): ?Event
    {
        return $this->model
            ->with(['quoteRequests.proposals', 'quoteRequests.category', 'creator'])
            ->find($id);
    }
}
