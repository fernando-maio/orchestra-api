<?php

namespace App\Services;

use App\Contracts\Repositories\EventRepositoryInterface;
use App\Contracts\Services\EventServiceInterface;
use App\Models\Event;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EventService extends BaseService implements EventServiceInterface
{
    protected EventRepositoryInterface $eventRepository;

    public function __construct(EventRepositoryInterface $repository)
    {
        parent::__construct($repository);
        $this->eventRepository = $repository;
    }

    public function getAllForOrganization(string $organizationId): Collection
    {
        return $this->eventRepository->getAllForOrganization($organizationId);
    }

    public function paginateWithFilters(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->eventRepository->paginateWithFilters($filters, $perPage);
    }

    public function getUpcoming(string $organizationId, int $limit = 5): Collection
    {
        return $this->eventRepository->getUpcoming($organizationId, $limit);
    }

    public function getByStatus(string $organizationId, string $status): Collection
    {
        return $this->eventRepository->getByStatus($organizationId, $status);
    }

    public function getWithQuoteRequests(string $id): Event
    {
        $event = $this->eventRepository->getWithQuoteRequests($id);

        if (!$event) {
            throw new ModelNotFoundException("Event not found with id: {$id}");
        }

        return $event;
    }

    public function updateStatus(string $id, string $status): Event
    {
        $event = $this->eventRepository->findOrFail($id);

        // Validar transições de status
        $allowedTransitions = [
            'draft' => ['active', 'canceled'],
            'active' => ['completed', 'canceled'],
            'completed' => [],
            'canceled' => ['draft'],
        ];

        $currentStatus = $event->status ?? 'draft';
        if (!in_array($status, $allowedTransitions[$currentStatus] ?? [])) {
            throw new \InvalidArgumentException(
                "Não é possível alterar o status de '{$currentStatus}' para '{$status}'."
            );
        }

        $event->status = $status;
        $event->save();

        return $event->fresh();
    }

    public function duplicate(string $id): Event
    {
        $event = $this->eventRepository->findOrFail($id);

        return DB::transaction(function () use ($event) {
            $newEvent = $event->replicate([
                'status',
                'actual_budget',
            ]);

            $newEvent->name = $event->name . ' (cópia)';
            $newEvent->status = 'draft';
            $newEvent->start_date = $event->start_date;
            $newEvent->end_date = $event->end_date;
            $newEvent->save();

            return $newEvent;
        });
    }

    public function getStatistics(string $id): array
    {
        $event = $this->eventRepository->findOrFail($id);

        $quoteRequests = $event->quoteRequests()->withCount([
            'proposals',
            'proposals as accepted_proposals_count' => function ($query) {
                $query->where('status', 'accepted');
            },
            'proposals as contracted_proposals_count' => function ($query) {
                $query->where('status', 'contracted');
            },
        ])->get();

        $totalQuotes = $quoteRequests->count();
        $totalProposals = $quoteRequests->sum('proposals_count');
        $acceptedProposals = $quoteRequests->sum('accepted_proposals_count');
        $contractedProposals = $quoteRequests->sum('contracted_proposals_count');

        $contractedBudget = $event->getContractedBudget();
        $budgetProgress = $event->getBudgetProgress();
        $vendorProgress = $event->getVendorProgress();

        return [
            'total_quote_requests' => $totalQuotes,
            'total_proposals' => $totalProposals,
            'accepted_proposals' => $acceptedProposals,
            'contracted_proposals' => $contractedProposals,
            'estimated_budget' => (float) $event->estimated_budget,
            'contracted_budget' => $contractedBudget,
            'budget_progress' => round($budgetProgress, 2),
            'vendor_progress' => $vendorProgress,
            'days_until_event' => $event->start_date
                ? now()->diffInDays($event->start_date, false)
                : null,
        ];
    }
}
