<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'order_id' =>
                $this->order_id,

            'store_id' =>
                $this->store_id,

            'buyer_id' =>
                $this->buyer_id,

            'rating' =>
                (int) $this->rating,

            'comment' =>
                $this->comment,

            'is_published' =>
                (bool) $this->is_published,

            'user' =>
                new UserResource(
                    $this->whenLoaded('user')
                ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}