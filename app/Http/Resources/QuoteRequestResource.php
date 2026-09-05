<?php

namespace App\Http\Resources;

use App\Models\QuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource delega para o model por __get, entao a analise estatica nao
 * enxerga as propriedades. O @mixin diz de onde elas vem.
 *
 * @mixin QuoteRequest
 */
class QuoteRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'category_id' => $this->category_id,
            'title' => $this->title,
            'technical_description' => $this->technical_description,
            'specifications' => $this->specifications,
            'budget_min' => (float) $this->budget_min,
            'budget_max' => (float) $this->budget_max,
            'response_deadline' => $this->response_deadline?->toISOString(),
            'status' => $this->status,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'event' => new EventResource($this->whenLoaded('event')),
            'proposals' => ProposalResource::collection($this->whenLoaded('proposals')),
            'proposals_count' => $this->when(
                $this->proposals_count !== null,
                $this->proposals_count
            ),
            'vendors_invited_count' => $this->when(
                $this->vendors_invited_count !== null,
                $this->vendors_invited_count
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
