<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class BookingException extends Exception
{
    public static function closed(): self
    {
        return new self('This event is not taking bookings.');
    }

    public static function alreadyBooked(): self
    {
        return new self('You already have a booking for this event.');
    }

    public static function notEnoughSeats(int $left): self
    {
        return new self($left === 0
            ? 'This event is sold out.'
            : "Only {$left} seat(s) left for this event.");
    }

    // Laravel calls this automatically when the exception isn't caught
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
