<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'store_name' => $this->store_name,

            'slug' => $this->slug,

            'house_number' => $this->house_number,

            'is_open' => (bool) $this->is_open,

            'is_active' => (bool) $this->is_active,

            'bonus_text' => $this->bonus_text,

            'description' => $this->description,

            'logo' => $this->logo,

            'products' => ProductResource::collection(
                $this->whenLoaded('products')
            ),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}