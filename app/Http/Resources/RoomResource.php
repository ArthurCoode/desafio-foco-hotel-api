<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Room
 */
class RoomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hotel_id' => $this->hotel_id,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'hotel' => $this->whenLoaded('hotel', fn () => [
                'id' => $this->hotel->id,
                'external_id' => $this->hotel->external_id,
                'name' => $this->hotel->name,
            ]),
        ];
    }
}
