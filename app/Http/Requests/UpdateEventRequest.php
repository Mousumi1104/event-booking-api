<?php

namespace App\Http\Requests;

use App\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event'));
    }

    public function rules(): array
    {
        $event = $this->route('event');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'venue' => ['sometimes', 'required', 'string', 'max:150'],
            'starts_at' => ['sometimes', 'required', 'date', 'after:now'],
            // compare against the new start time if sent, otherwise the saved one
            'ends_at' => [
                'sometimes', 'required', 'date',
                $this->has('starts_at') ? 'after:starts_at' : 'after:'.$event->starts_at->toDateTimeString(),
            ],
            // can't shrink below the seats people have already booked
            'capacity' => ['sometimes', 'required', 'integer', 'min:'.max(1, $event->bookedSeats()), 'max:10000'],
            'price_cents' => ['sometimes', 'required', 'integer', 'min:0', 'max:10000000'],
            'status' => ['sometimes', 'required', Rule::enum(EventStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'capacity.min' => 'Capacity cannot be lower than the :min seats already booked.',
        ];
    }
}
