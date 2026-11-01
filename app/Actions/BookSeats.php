<?php

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BookSeats
{
    /**
     * @throws BookingException
     */
    public function handle(User $user, Event $event, int $seats): Booking
    {
        return DB::transaction(function () use ($user, $event, $seats) {
            // Lock the event row until we commit. Two people booking the last
            // seat at the same moment now queue up here instead of both
            // reading "1 seat left" and overbooking the event.
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

            if (! $event->isOpenForBooking()) {
                throw BookingException::closed();
            }

            if ($event->bookings()->confirmed()->where('user_id', $user->id)->exists()) {
                throw BookingException::alreadyBooked();
            }

            $left = $event->capacity - $event->bookedSeats();

            if ($seats > $left) {
                throw BookingException::notEnoughSeats($left);
            }

            $booking = new Booking(['seats' => $seats]);
            $booking->user()->associate($user);
            // price is copied at booking time so later price changes don't rewrite history
            $booking->amount_cents = $seats * $event->price_cents;
            $booking->status = BookingStatus::Confirmed;

            $event->bookings()->save($booking);

            return $booking;
        });
    }
}
