<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'venue' => $this->venue,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'capacity' => $this->capacity,
            'seats_left' => $this->seats_left,
            'price_cents' => $this->price_cents,
            'status' => $this->status->value,
            'organiser' => $this->whenLoaded('organiser', fn () => [
                'id' => $this->organiser->id,
                'name' => $this->organiser->name,
            ]),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
