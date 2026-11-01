<?php

namespace App\Http\Controllers\Api;

use App\Actions\BookSeats;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $bookings = $request->user()->bookings()
            ->with('event:id,title,starts_at')
            ->latest()
            ->paginate(15);

        return BookingResource::collection($bookings);
    }

    public function store(StoreBookingRequest $request, Event $event, BookSeats $bookSeats): JsonResponse
    {
        Gate::authorize('view', $event);

        $booking = $bookSeats->handle($request->user(), $event, $request->integer('seats'));

        return BookingResource::make($booking->load('event:id,title,starts_at'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Booking $booking): Response
    {
        Gate::authorize('cancel', $booking);

        $booking->cancel();

        return response()->noContent();
    }
}
