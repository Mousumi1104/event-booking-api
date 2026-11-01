# Event Booking API

A small REST API for running workshops and meetups. Organisers create events with a fixed number of seats, and other users book seats until the event is full.

I built it to practise the parts of Laravel that usually go wrong in real projects: authorization, validation that depends on existing data, and two people booking the last seat at the same time.

**Stack:** Laravel 12, PHP 8.2, Sanctum (token auth), MySQL or PostgreSQL (SQLite for tests)

## Running it

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Tests:

```bash
php artisan test
```

## Endpoints

Everything except register/login needs a `Bearer` token.

| Method | URL | What it does |
|---|---|---|
| POST | `/api/register` | Create an account, returns a token |
| POST | `/api/login` | Returns a token |
| POST | `/api/logout` | Revokes the current token |
| GET | `/api/events?q=&mine=` | Published upcoming events (or your own with `mine=1`) |
| POST | `/api/events` | Create an event (starts as a draft) |
| GET | `/api/events/{id}` | Show one event |
| PATCH | `/api/events/{id}` | Update (organiser only) |
| DELETE | `/api/events/{id}` | Delete (organiser only, and only if nobody has booked) |
| POST | `/api/events/{id}/bookings` | Book seats `{ "seats": 2 }` |
| GET | `/api/bookings` | My bookings |
| DELETE | `/api/bookings/{id}` | Cancel my booking |

## Where things live

- `app/Http/Controllers/Api` – kept thin. They take a validated request, call the model or an action, and return a resource.
- `app/Http/Requests` – all validation. `UpdateEventRequest` won't let capacity drop below seats already booked.
- `app/Policies` – who can see or change what. Drafts return 404 to other users, not 403, so their existence isn't leaked.
- `app/Actions/BookSeats.php` – the booking logic. It runs inside a transaction with `lockForUpdate()` on the event row, so concurrent bookings can't oversell the event.
- `app/Http/Resources` – the JSON shape. Models are never returned directly.
- `app/Enums` – event and booking statuses as backed enums, cast on the models.

## Decisions worth mentioning

- **Money is stored in cents** (integer), not floats.
- **The booking amount is copied when the booking is made**, so a later price change doesn't change what someone already paid.
- **`user_id` is not mass assignable.** It always comes from the authenticated user, never from the request body (there's a test for this).
- **Listing pages use `withSum`** to get booked seats in the same query instead of one query per event.
- **Search escapes `%` and `_`**, so searching for "100%" doesn't match every row.

## What I'd add next

- When an event is cancelled, cancel its bookings and notify attendees through a queued job.
- Payments (Stripe Checkout + webhook) so a booking is only confirmed once paid.
- A waiting list when an event is sold out.
