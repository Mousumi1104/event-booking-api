<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class EventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'mine' => ['nullable', 'boolean'],
        ]);

        $events = Event::query()
            ->when(
                $request->boolean('mine'),
                // organisers see all their own events, including drafts
                fn ($query) => $query->whereBelongsTo($request->user(), 'organiser'),
                fn ($query) => $query->published()->upcoming(),
            )
            ->search($filters['q'] ?? null)
            ->with('organiser:id,name')
            ->withBookedSeats()
            ->orderBy('starts_at')
            ->paginate(15)
            ->withQueryString();

        return EventResource::collection($events);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = $request->user()->events()->create($request->validated());

        return EventResource::make($event->load('organiser:id,name'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Event $event): EventResource
    {
        Gate::authorize('view', $event);

        return EventResource::make($event->load('organiser:id,name'));
    }

    public function update(UpdateEventRequest $request, Event $event): EventResource
    {
        $event->update($request->validated());

        return EventResource::make($event->load('organiser:id,name'));
    }

    public function destroy(Event $event): JsonResponse|Response
    {
        Gate::authorize('delete', $event);

        if ($event->bookings()->confirmed()->exists()) {
            return response()->json([
                'message' => 'This event has bookings. Set its status to cancelled instead.',
            ], Response::HTTP_CONFLICT);
        }

        $event->delete();

        return response()->noContent();
    }
}
