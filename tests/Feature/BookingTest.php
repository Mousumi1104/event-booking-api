<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_book_seats(): void
    {
        $event = Event::factory()->create(['capacity' => 10, 'price_cents' => 2000]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/events/{$event->id}/bookings", ['seats' => 2])
            ->assertCreated()
            ->assertJsonPath('data.amount_cents', 4000)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertSame(8, $event->fresh()->seats_left);
    }

    public function test_cannot_book_more_seats_than_are_left(): void
    {
        $event = Event::factory()->create(['capacity' => 3]);
        Booking::factory()->for($event)->create(['seats' => 2]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/events/{$event->id}/bookings", ['seats' => 2])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Only 1 seat(s) left for this event.');
    }

    public function test_cannot_book_the_same_event_twice(): void
    {
        $event = Event::factory()->create();
        $user = User::factory()->create();
        Booking::factory()->for($event)->for($user)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/events/{$event->id}/bookings", ['seats' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'You already have a booking for this event.');
    }

    public function test_cannot_book_a_past_event(): void
    {
        $event = Event::factory()->past()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/events/{$event->id}/bookings", ['seats' => 1])
            ->assertUnprocessable();
    }

    public function test_cannot_book_someone_elses_draft(): void
    {
        $event = Event::factory()->draft()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/events/{$event->id}/bookings", ['seats' => 1])
            ->assertNotFound();
    }

    public function test_seat_count_is_validated(): void
    {
        $event = Event::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/events/{$event->id}/bookings", ['seats' => 0])
            ->assertJsonValidationErrors('seats');
    }

    public function test_only_the_owner_can_cancel_a_booking(): void
    {
        $booking = Booking::factory()->create();

        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson("/api/bookings/{$booking->id}")->assertForbidden();

        Sanctum::actingAs($booking->user);
        $this->deleteJson("/api/bookings/{$booking->id}")->assertNoContent();

        $this->assertSame('cancelled', $booking->fresh()->status->value);
    }

    public function test_cancelled_seats_can_be_booked_again(): void
    {
        $event = Event::factory()->create(['capacity' => 1]);
        Booking::factory()->for($event)->cancelled()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/events/{$event->id}/bookings", ['seats' => 1])->assertCreated();
    }

    public function test_my_bookings_lists_only_my_own(): void
    {
        $user = User::factory()->create();
        Booking::factory()->for($user)->count(2)->create();
        Booking::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/bookings')->assertOk()->assertJsonCount(2, 'data');
    }
}
