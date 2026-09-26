# Reservation System

A Laravel-based reservation service that lets a client book a number of `units`
from a shared `resource` for a specific time window, without ever allowing the
sum of active reservations to exceed the resource's `capacity` — even under
concurrent requests and multiple backend instances.

No authentication is required for this project (per spec), with one
deliberate exception described below for the admin capacity endpoint.

---

## Table of Contents

- [Requirements](#requirements)
- [Setup / How to Run](#setup--how-to-run)
- [API Endpoints](#api-endpoints)
- [Key Design Decisions](#key-design-decisions)
- [Challenges & How They Were Solved](#challenges--how-they-were-solved)
- [Running the Tests](#running-the-tests)
- [Project Structure](#project-structure)

---

## Requirements

- PHP ^8.4
- Composer
- SQLite (default) or any other Laravel-supported database

---

## Setup / How to Run

```bash
composer install

cp .env.example .env
php artisan key:generate

# SQLite is the default connection; create the file if it doesn't exist
touch database/database.sqlite

php artisan migrate

php artisan serve
```

To run the seeders (optional, for sample resources/reservations):

```bash
php artisan db:seed
```

To clean up expired reservations in the database (optional — see
[Expiry](#1-reservation-expiry-without-a-background-worker) below for why this
is a cleanup step, not a correctness requirement):

```bash
php artisan reservations:expire
```

You can schedule this command to run every minute in `routes/console.php`:

```php
Schedule::command('reservations:expire')->everyMinute();
```

---

## API Endpoints

All write endpoints require an `Idempotency-Key` header.

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/reservations` | Create a reservation |
| POST | `/api/reservations/{reservation}/confirm` | Confirm a pending reservation |
| POST | `/api/reservations/{reservation}/cancel` | Cancel a reservation |
| PUT | `/api/reservations/{reservation}` | Update units / time window |
| GET | `/api/resources/{resource}/availability` | Check availability for a period |
| PATCH | `/api/resources/{resource}/capacity` | Update a resource's capacity (admin only) |

---

## Key Design Decisions

### 1. Reservation expiry without a background worker

Rather than relying on a queued job or scheduled command to *flip* a
reservation's status the instant it expires, expiry is treated as a
**derived, lazily-evaluated property**:

- Every reservation gets an `expires_at` timestamp (created_at + 2 minutes).
- `AvailabilityService` — the single source of truth for "is this
  reservation currently occupying capacity" — only counts a `pending`
  reservation if `expires_at > now()`.
- This means correctness (no overbooking) **never depends on a cron job
  running on time**. Even if the scheduler is down for an hour, a booking
  request made right now will correctly ignore anything that should have
  expired.
- `php artisan reservations:expire` (via `ExpireStaleReservationsCommand`)
  exists purely as **housekeeping**: it flips stale rows to the `expired`
  status in the database and writes a history entry, so the data stays
  auditable and tidy. It is not load-bearing for correctness.

### 2. Concurrency safety without relying on the application layer

Every state-changing action (`CreateReservationAction`,
`UpdateReservationAction`, `ConfirmReservationAction`,
`CancelReservationAction`, `UpdateCapacityAction`) wraps its logic in
`DB::transaction()` and takes a `lockForUpdate()` row lock on the resource
(and reservation, where relevant) **before** re-checking capacity.

This means the capacity check and the write happen atomically at the
database level, so it doesn't matter how many backend instances are running
— two processes racing to book the last few units on the same resource will
be serialized by the database, and the second one to reach the lock will
correctly see the first one's reservation already counted.

### 3. Idempotency (a new concept for me — and a lesson in avoiding races within the race-prevention itself)

Every write request must send an `Idempotency-Key` header. The naive
approach — `SELECT` to check if the key exists, then `INSERT` if not — has
its own race window: two identical concurrent requests could both pass the
`SELECT` before either finishes the `INSERT`.

The solution used here (`EnsureIdempotency` middleware) flips the order:
**optimistically INSERT first**. The `idempotency_key` column has a UNIQUE
constraint, so:

- If the `INSERT` succeeds, this request is the "winner" and proceeds normally.
- If the `INSERT` fails with a unique constraint violation, another request
  already claimed that key — we look up its stored response and either
  return it directly (if it finished) or return a 409 (if it's still being
  processed).
- If the same key is reused with a *different* request body, that's a
  client error (422), not implicit re-use of the cached response.

This was a genuinely new pattern for me and a good lesson: don't just move
the race — remove it, by pushing the exclusivity guarantee down to something
the database already guarantees for free (a unique index).

### 4. Admin-only capacity updates without full authentication

The spec explicitly says "no authentication required," but also requires
that only an **admin** can change a resource's `capacity`. This was the main
design challenge: how do you gate a single endpoint by role when there's no
user/auth system at all?

The solution: a lightweight middleware in front of the capacity endpoint
that checks for a shared admin secret (an `X-Admin-Token` header, compared
against a value in config/`.env`) before the request is allowed through —
effectively a simple shared-password check, without needing users, guards,
sessions, or Sanctum. It's intentionally minimal:

- No user identity, roles, or permissions model — just "does the caller know
  the admin secret."
- Keeps the rest of the API completely auth-free, as required.
- Is easy to swap for real authentication later without touching the
  action/service layer, since the check lives entirely in the middleware.

### 5. Capacity reduction can't invalidate existing reservations

`UpdateCapacityAction` doesn't just blindly update the `capacity` column. If
the new value is *lower* than the current one, it computes the
**peak concurrent unit usage** across all currently active/future
reservations (`AvailabilityService::maxConcurrentUnits`, a sweep-line over
reservation start/end events) and rejects the change if the new capacity
would be lower than that peak. This guarantees the invariant "capacity is
never lower than what's already been promised to someone" holds at all times.

### 6. Append-only history

Every action that mutates a reservation (create, update, confirm, cancel,
expire) writes a `ReservationHistory` row **inside the same transaction** as
the state change itself — so history can never drift out of sync with the
actual reservation state, and a rolled-back transaction never leaves an
orphaned history entry.

---

## Challenges & How They Were Solved

| Challenge | Solution |
|---|---|
| Admin-only capacity control with **no** auth system in place | Lightweight shared-secret middleware in front of just that one endpoint |
| Preventing overbooking under real concurrent requests | Row-level locking (`lockForUpdate`) inside DB transactions, re-checking capacity right before the write |
| Idempotent writes without a race in the idempotency check itself | Optimistic `INSERT` + unique constraint, instead of `SELECT`-then-`INSERT` |
| Expiry correctness surviving server restarts / a stalled scheduler | Expiry is computed lazily from `expires_at` at read time, not flipped by a background process |
| Testing concurrent behavior | New territory for me — see below |

Writing **tests for concurrency** was the part I had the least prior
experience with. Standard feature tests run requests sequentially, so they
don't actually exercise the race condition the spec cares about. The
approach taken was to simulate overlapping requests against the same
resource and assert that the total committed units across all successful
reservations never exceeds `capacity`, rather than trusting the
locking logic by inspection alone.

---

## Running the Tests

```bash
php artisan test --compact
```

Or with Pest directly:

```bash
vendor/bin/pest
```

Run a single file or filter by test name:

```bash
php artisan test --filter=test_rejects_reservation_that_would_exceed_capacity
```

---

## Project Structure

```
app/
├── Actions/            # One class per write use-case (Create/Update/Confirm/Cancel/UpdateCapacity)
├── DTOs/                # Typed request payloads (CreateReservationData, UpdateReservationData)
├── Enums/               # ReservationStatus
├── Exceptions/          # Domain exceptions with their own JSON rendering
├── Http/
│   ├── Controllers/     # Thin controllers — just wire Requests → Actions → Resources
│   ├── Middleware/      # EnsureIdempotency (and the admin-capacity check)
│   ├── Requests/        # Form request validation
│   └── Resources/       # API response shaping
├── Models/              # Reservation, Resource, ReservationHistory, IdempotencyKey
└── Services/            # AvailabilityService — the single source of truth for booked units / peak usage
```
