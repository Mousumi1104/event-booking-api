<?php

namespace App\Http\Requests;

use App\Enums\EventStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // any logged-in user can organise an event
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'venue' => ['required', 'string', 'max:150'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
            'price_cents' => ['required', 'integer', 'min:0', 'max:10000000'],
            // cancelling is a separate decision, so it's not allowed on create
            'status' => ['sometimes', Rule::enum(EventStatus::class)->only([EventStatus::Draft, EventStatus::Published])],
        ];
    }
}
