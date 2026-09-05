<?php

namespace App\Http\Resources;

use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource delega para o model por __get, entao a analise estatica nao
 * enxerga as propriedades. O @mixin diz de onde elas vem.
 *
 * @mixin Proposal
 */
class ProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quote_request_id' => $this->quote_request_id,
            'vendor_id' => $this->vendor_id,
            'value' => (float) $this->value,
            'locked_value' => (float) $this->locked_value,
            'description' => $this->description,
            'validity_days' => $this->validity_days,
            'delivery_days' => $this->delivery_days,
            'payment_terms' => $this->payment_terms,
            'notes' => $this->notes,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
            'quote_request' => new QuoteRequestResource($this->whenLoaded('quoteRequest')),
            'reviewed_by' => new UserResource($this->whenLoaded('reviewer')),
            'attachments' => $this->when(
                $this->relationLoaded('attachments'),
                fn () => $this->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'url' => asset('storage/'.$a->path),
                ])
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
