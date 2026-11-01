<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\EventStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, SoftDeletes;

    // user_id is left out on purpose - it is always set from the logged-in user
    protected $fillable = [
        'title',
        'description',
        'venue',
        'starts_at',
        'ends_at',
        'capacity',
        'price_cents',
        'status',
    ];

    protected $attributes = [
        'status' => 'draft',
        'price_cents' => 0,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'price_cents' => 'integer',
            'status' => EventStatus::class,
        ];
    }

    public function organiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', EventStatus::Published);
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>', now());
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        // Escape LIKE wildcards so a search for "100%" doesn't match everything.
        // '!' is used as the escape char because it behaves the same on
        // MySQL, Postgres and SQLite (backslash doesn't, on SQLite).
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        $query->where(function (Builder $q) use ($like) {
            $q->whereRaw("title LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("venue LIKE ? ESCAPE '!'", [$like]);
        });
    }

    /**
     * Adds a booked_seats column so listing pages don't run one
     * count query per event.
     */
    public function scopeWithBookedSeats(Builder $query): void
    {
        $query->withSum([
            'bookings as booked_seats' => fn (Builder $q) => $q->where('status', BookingStatus::Confirmed),
        ], 'seats');
    }

    public function bookedSeats(): int
    {
        if (array_key_exists('booked_seats', $this->attributes)) {
            return (int) $this->attributes['booked_seats'];
        }

        return (int) $this->bookings()->confirmed()->sum('seats');
    }

    protected function seatsLeft(): Attribute
    {
        return Attribute::get(fn () => max(0, $this->capacity - $this->bookedSeats()));
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    public function isOpenForBooking(): bool
    {
        return $this->status === EventStatus::Published && $this->starts_at->isFuture();
    }
}
