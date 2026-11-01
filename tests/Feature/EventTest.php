<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_get_401(): void
    {
        $this->getJson('/api/events')->assertUnauthorized();
    }

    public function test_listing_shows_only_published_upcoming_events(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $visible = Event::factory()->create();
        Event::factory()->draft()->create();
        Event::factory()->past()->create();

        $this->getJson('/api/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    }

    public function test_listing_reports_seats_left(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $event = Event::factory()->create(['capacity' => 10]);
        Booking::factory()->for($event)->create(['seats' => 3]);
        Booking::factory()->for($event)->cancelled()->create(['seats' => 2]);

        $this->getJson('/api/events')->assertJsonPath('data.0.seats_left', 7);
    }

    public function test_search_does_not_treat_percent_as_a_wildcard(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Event::factory()->create(['title' => 'Laravel meetup']);
        Event::factory()->create(['title' => '100% Testing']);

        $this->getJson('/api/events?q=%25')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', '100% Testing');
    }

    public function test_user_can_create_an_event_and_owns_it(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/events', [
            'title' => 'Intro to Laravel',
            'venue' => 'Salt Lake, Kolkata',
            'starts_at' => now()->addWeek()->toDateTimeString(),
            'ends_at' => now()->addWeek()->addHours(2)->toDateTimeString(),
            'capacity' => 25,
            'price_cents' => 0,
            'user_id' => 999, // should be ignored
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.organiser.id', $user->id);

        $this->assertDatabaseHas('events', ['title' => 'Intro to Laravel', 'user_id' => $user->id]);
    }

    public function test_create_validates_dates(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/events', [
            'title' => 'Bad dates',
            'venue' => 'Online',
            'starts_at' => now()->addDays(2)->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
            'capacity' => 10,
            'price_cents' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('ends_at');
    }

    public function test_drafts_are_hidden_from_other_users(): void
    {
        $draft = Event::factory()->draft()->create();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/events/{$draft->id}")->assertNotFound();

        Sanctum::actingAs($draft->organiser);
        $this->getJson("/api/events/{$draft->id}")->assertOk();
    }

    public function test_only_the_organiser_can_update(): void
    {
        $event = Event::factory()->create();

        Sanctum::actingAs(User::factory()->create());
        $this->patchJson("/api/events/{$event->id}", ['title' => 'Hijacked'])->assertForbidden();

        Sanctum::actingAs($event->organiser);
        $this->patchJson("/api/events/{$event->id}", ['title' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed');
    }

    public function test_capacity_cannot_drop_below_booked_seats(): void
    {
        $event = Event::factory()->create(['capacity' => 10]);
        Booking::factory()->for($event)->create(['seats' => 6]);

        Sanctum::actingAs($event->organiser);

        $this->patchJson("/api/events/{$event->id}", ['capacity' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capacity');
    }

    public function test_event_with_bookings_cannot_be_deleted(): void
    {
        $event = Event::factory()->create();
        Booking::factory()->for($event)->create();

        Sanctum::actingAs($event->organiser);

        $this->deleteJson("/api/events/{$event->id}")->assertConflict();
        $this->assertNotSoftDeleted($event);
    }

    public function test_organiser_can_delete_an_event_without_bookings(): void
    {
        $event = Event::factory()->create();

        Sanctum::actingAs($event->organiser);

        $this->deleteJson("/api/events/{$event->id}")->assertNoContent();
        $this->assertSoftDeleted($event);
    }
}
