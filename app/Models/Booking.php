<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    // amount, status and the foreign keys are set in BookSeats, never from input
    protected $fillable = ['seats'];

    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'amount_cents' => 'integer',
            'status' => BookingStatus::class,
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', BookingStatus::Confirmed);
    }

    public function cancel(): void
    {
        $this->status = BookingStatus::Cancelled;
        $this->save();
    }
}
