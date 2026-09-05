<?php

namespace App\Http\Resources;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource delega para o model por __get, entao a analise estatica nao
 * enxerga as propriedades. O @mixin diz de onde elas vem.
 *
 * @mixin Vendor
 */
class VendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'trade_name' => $this->trade_name,
            'legal_name' => $this->legal_name,
            'cnpj' => $this->cnpj,
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'website' => $this->website,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'service_radius_km' => $this->service_radius_km,
            'logo_url' => $this->logo_path ? asset('storage/'.$this->logo_path) : null,
            'description' => $this->description,
            'accepts_urgent' => $this->accepts_urgent,
            'is_verified' => $this->is_verified,
            'average_rating' => (float) $this->average_rating,
            'total_ratings' => $this->total_ratings,
            'is_local_business' => $this->is_local_business,
            'is_sustainable' => $this->is_sustainable,
            'is_minority_owned' => $this->is_minority_owned,
            'is_active' => $this->is_active,
            // Marketplace fields
            'approval_status' => $this->approval_status,
            'rejection_reason' => $this->rejection_reason,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toISOString(),
            'source' => $this->source,
            'subscription_tier' => $this->subscription_tier,
            'subscription_expires_at' => $this->subscription_expires_at?->toISOString(),
            'contact_name' => $this->contact_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            // Relationships
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'distance' => $this->when(isset($this->distance), fn () => round($this->distance, 2)),
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
