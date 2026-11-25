<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'store_id' => $this->store_id,

            'category_id' => $this->category_id,

            'title' => $this->title,

            'description' => $this->description,

            'price' => (string) $this->price,

            'image' => $this->image,

            'badge' => $this->badge,

            'is_available' => (bool) $this->is_available
                && (
                    $this->stock_quantity === null
                    || (int) $this->stock_quantity > 0
                ),

            'stock_quantity' => $this->stock_quantity === null
                ? null
                : (int) $this->stock_quantity,

            'sales_count' => (int) $this->sales_count,

            'sort_order' => (int) $this->sort_order,

            'store' => new StoreResource(
                $this->whenLoaded('store')
            ),

            'category' => new CategoryResource(
                $this->whenLoaded('category')
            ),
        ];
    }
}
