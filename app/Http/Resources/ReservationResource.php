<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Reservation
 */
class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'hotel_id' => $this->hotel_id,
            'room_id' => $this->room_id,
            'check_in' => $this->check_in?->format('Y-m-d'),
            'check_out' => $this->check_out?->format('Y-m-d'),
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'fees' => $this->fees,
            'total' => $this->total,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            'hotel' => $this->whenLoaded('hotel', fn () => [
                'id' => $this->hotel->id,
                'external_id' => $this->hotel->external_id,
                'name' => $this->hotel->name,
            ]),

            'room' => $this->whenLoaded('room', fn () => [
                'id' => $this->room->id,
                'external_id' => $this->room->external_id,
                'name' => $this->room->name,
                'quantity' => $this->room->quantity,
            ]),

            'guests' => $this->whenLoaded('guests', fn () => $this->guests
                ->map(fn ($guest) => [
                    'id' => $guest->id,
                    'name' => $guest->name,
                    'phone' => $guest->phone,
                ])
                ->all()),

            'dailies' => $this->whenLoaded('dailies', fn () => $this->dailies
                ->map(fn ($daily) => [
                    'id' => $daily->id,
                    'date' => $daily->date?->format('Y-m-d'),
                    'amount' => $daily->amount,
                ])
                ->all()),
        ];
    }
}
