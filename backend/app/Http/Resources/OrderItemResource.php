<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'product_id' => $this->product_id,

            'product_title' => $this->product_title,

            'quantity' => (int) $this->quantity,

            'unit_price' => (string) $this->unit_price,

            'subtotal' => (string) $this->subtotal,
        ];
    }
}