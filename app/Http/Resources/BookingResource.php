<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Booking */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seats' => $this->seats,
            'amount_cents' => $this->amount_cents,
            'status' => $this->status->value,
            'event' => $this->whenLoaded('event', fn () => [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'starts_at' => $this->event->starts_at->toIso8601String(),
            ]),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
