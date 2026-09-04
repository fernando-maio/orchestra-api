<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'cnpj' => $this->cnpj,
            'email' => $this->email,
            'phone' => $this->phone,
            'logo_url' => $this->logo_path ? asset('storage/'.$this->logo_path) : null,
            'subscription_status' => $this->subscription_status,
            'subscription_plan' => $this->subscription_plan,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
