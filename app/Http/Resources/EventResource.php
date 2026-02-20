<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'briefing' => $this->briefing,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'venue_name' => $this->venue_name,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'estimated_budget' => (float) $this->estimated_budget,
            'actual_budget' => (float) $this->actual_budget,
            'currency' => $this->currency ?? 'BRL',
            'expected_attendees' => $this->expected_attendees,
            'status' => $this->status ?? 'draft',
            'required_categories' => $this->required_categories,
            'settings' => $this->settings,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'quote_requests' => QuoteRequestResource::collection($this->whenLoaded('quoteRequests')),
            'quote_requests_count' => $this->when(
                $this->quote_requests_count !== null,
                $this->quote_requests_count
            ),
            'proposals_count' => $this->when(
                $this->proposals_count !== null,
                $this->proposals_count
            ),
            'budget_progress' => $this->when(
                method_exists($this->resource, 'getBudgetProgress'),
                fn() => round($this->getBudgetProgress(), 2)
            ),
            'vendor_progress' => $this->when(
                method_exists($this->resource, 'getVendorProgress'),
                fn() => $this->getVendorProgress()
            ),
            'can_be_edited' => $this->canBeEdited(),
            'days_until_event' => $this->start_date
                ? now()->diffInDays($this->start_date, false)
                : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
