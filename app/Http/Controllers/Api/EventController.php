<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\EventServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\EventResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function __construct(
        protected EventServiceInterface $eventService
    ) {}

    /**
     * Lista eventos com filtros e paginação
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'search',
            'status',
            'city',
            'state',
            'start_from',
            'start_to',
            'upcoming',
            'past',
        ]);

        // Filtra por organização do usuário (multi-tenancy)
        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            $filters['organization_id'] = $user->organization_id;
        } elseif ($request->has('organization_id')) {
            $filters['organization_id'] = $request->input('organization_id');
        }

        $events = $this->eventService->paginateWithFilters(
            $filters,
            $request->integer('per_page', 15)
        );

        return EventResource::collection($events);
    }

    /**
     * Cria um novo evento
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Define organization_id e created_by
        $data['organization_id'] = $request->user()->organization_id;
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'draft';

        $event = $this->eventService->create($data);
        $event->load('creator');

        return response()->json([
            'message' => 'Evento criado com sucesso.',
            'data' => new EventResource($event),
        ], 201);
    }

    /**
     * Exibe um evento específico
     */
    public function show(string $id): EventResource
    {
        $event = $this->eventService->getWithQuoteRequests($id);

        return new EventResource($event);
    }

    /**
     * Atualiza um evento
     */
    public function update(UpdateEventRequest $request, string $id): JsonResponse
    {
        $event = $this->eventService->findOrFail($id);

        // Verifica se o evento pode ser editado
        if (! $event->canBeEdited()) {
            return response()->json([
                'message' => 'Este evento não pode ser editado no status atual.',
            ], 422);
        }

        $event = $this->eventService->update($id, $request->validated());
        $event->load(['creator', 'organization']);

        return response()->json([
            'message' => 'Evento atualizado com sucesso.',
            'data' => new EventResource($event),
        ]);
    }

    /**
     * Remove um evento
     */
    public function destroy(string $id): JsonResponse
    {
        $this->authorize('events.delete');

        $event = $this->eventService->findOrFail($id);

        // Verifica se tem cotações associadas
        if ($event->quoteRequests()->count() > 0) {
            return response()->json([
                'message' => 'Não é possível excluir um evento com cotações associadas.',
            ], 422);
        }

        $this->eventService->delete($id);

        return response()->json([
            'message' => 'Evento excluído com sucesso.',
        ]);
    }

    /**
     * Altera o status do evento
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $this->authorize('events.update');

        $request->validate([
            'status' => ['required', 'string', 'in:draft,active,completed,canceled'],
        ]);

        try {
            $event = $this->eventService->updateStatus($id, $request->input('status'));

            return response()->json([
                'message' => 'Status do evento atualizado com sucesso.',
                'data' => new EventResource($event),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Duplica um evento
     */
    public function duplicate(string $id): JsonResponse
    {
        $this->authorize('events.create');

        $newEvent = $this->eventService->duplicate($id);
        $newEvent->load('creator');

        return response()->json([
            'message' => 'Evento duplicado com sucesso.',
            'data' => new EventResource($newEvent),
        ], 201);
    }

    /**
     * Retorna estatísticas do evento
     */
    public function statistics(string $id): JsonResponse
    {
        $this->authorize('events.view');

        $statistics = $this->eventService->getStatistics($id);

        return response()->json(['data' => $statistics]);
    }

    /**
     * Lista próximos eventos
     */
    public function upcoming(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $organizationId = $user->isSuperAdmin()
            ? $request->input('organization_id')
            : $user->organization_id;

        if (! $organizationId) {
            return EventResource::collection(collect());
        }

        $events = $this->eventService->getUpcoming(
            $organizationId,
            $request->integer('limit', 5)
        );

        return EventResource::collection($events);
    }
}
