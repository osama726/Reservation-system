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

### Postman collection

For a ready-to-use API collection with sample requests and responses, use the Postman workspace here:

<a href="https://app.getpostman.com/join-team?invite_code=69ed1fee6d60d55a8deca8c49a16a211b25debb9a54feca7bfb62b5bda74b2e9&target_code=bce7e483dc15a1672c27f0ed944d11e6" target="_blank" rel="noopener noreferrer">
      Pstman link — Check My Api Collection in postman
</a> 

### Common conventions

- All write requests require an `Idempotency-Key` header.
- All timestamps are ISO 8601 strings.
- `resource_id` is the numeric ID of the resource.
- `units` is the number of units being booked for that time window.
- If a request violates capacity, the API returns a `409 Conflict` with an error payload.
- Successful reservation responses are returned in the same flat JSON structure as the app's resource serializer.

### Response shape

Successful reservation responses look like this:

```json
{
    "data": {
        "id": "8d4a0f9d-0d5e-4d69-b43a-9f96a454e2c1",
        "resource_id": 1,
        "reservation_number": 48243501,
        "units": 2,
        "status": "pending",
        "start_time": "2030-01-01T10:00:00.000000Z",
        "end_time": "2030-01-01T11:00:00.000000Z",
        "expires_at": "2030-01-01T10:30:00.000000Z",
        "created_at": "2030-01-01T09:59:00.000000Z",
        "updated_at": "2030-01-01T09:59:00.000000Z"
    }
}
```

Error responses look like this:

```json
{
    "error": "capacity_exceeded",
    "message": "The requested reservation would exceed the resource capacity."
}
```

### Endpoint details

| Method | Endpoint                                  | Description                                    | Body / Parameters                                                               |
| ------ | ----------------------------------------- | ---------------------------------------------- | ------------------------------------------------------------------------------- |
| POST   | `/api/reservations`                       | Create a reservation                           | `resource_id`, `units`, `start_time`, `end_time`, plus `Idempotency-Key` header |
| POST   | `/api/reservations/{reservation}/confirm` | Confirm a pending reservation                  | No body required; `Idempotency-Key` header required                             |
| POST   | `/api/reservations/{reservation}/cancel`  | Cancel a reservation                           | No body required; `Idempotency-Key` header required                             |
| PUT    | `/api/reservations/{reservation}`         | Update a reservation                           | Optional: `units`, `start_time`, `end_time`; `Idempotency-Key` header required  |
| GET    | `/api/resources/{resource}/availability`  | Check total booked units vs available capacity | Query params: `start_time`, `end_time`                                          |
| PATCH  | `/api/resources/{resource}/capacity`      | Update a resource's capacity (admin only)      | JSON body: `capacity` and `X-Admin-Token` header                                |
| GET    | `/api/reservations/{reservation}/history` | View a reservation's full audit trail          | No body required                                                                |

#### 1) Create a reservation

`POST /api/reservations`

Headers:

```http
Idempotency-Key: create-res-001
Content-Type: application/json
```

Body:

```json
{
    "resource_id": 1,
    "units": 2,
    "start_time": "2030-01-01T10:00:00Z",
    "end_time": "2030-01-01T11:00:00Z"
}
```

Expected result:

```json
{
    "data": {
        "id": "...",
        "resource_id": 1,
        "units": 2,
        "status": "pending",
        "start_time": "2030-01-01T10:00:00.000000Z",
        "end_time": "2030-01-01T11:00:00.000000Z"
    }
}
```

If the booking would exceed capacity, the request returns `409` with:

```json
{
    "error": "capacity_exceeded"
}
```

#### 2) Confirm a reservation

`POST /api/reservations/{reservation}/confirm`

Headers:

```http
Idempotency-Key: confirm-res-001
Content-Type: application/json
```

Body:

```json
{}
```

Expected result: the same reservation object, but with `status` set to `confirmed`.

#### 3) Cancel a reservation

`POST /api/reservations/{reservation}/cancel`

Headers:

```http
Idempotency-Key: cancel-res-001
Content-Type: application/json
```

Body:

```json
{}
```

Expected result: the reservation status becomes `cancelled`.

#### 4) Update a reservation

`PUT /api/reservations/{reservation}`

Headers:

```http
Idempotency-Key: update-res-001
Content-Type: application/json
```

Body:

```json
{
    "units": 3,
    "start_time": "2030-01-01T10:15:00Z",
    "end_time": "2030-01-01T12:00:00Z"
}
```

The update re-checks capacity against overlapping bookings and rejects the change if it would overbook the resource.

#### 5) Check availability

`GET /api/resources/{resource}/availability?start_time=...&end_time=...`

Example:

```http
GET /api/resources/1/availability?start_time=2030-01-01T10:00:00Z&end_time=2030-01-01T11:00:00Z
```

Example response:

```json
{
    "resource_id": 1,
    "capacity": 10,
    "booked_units": 7,
    "available_units": 3,
    "start_time": "2030-01-01T10:00:00.000000Z",
    "end_time": "2030-01-01T11:00:00.000000Z"
}
```

#### 6) Update resource capacity (admin only)

`PATCH /api/resources/{resource}/capacity`

Headers:

```http
X-Admin-Token: your-admin-secret
Content-Type: application/json
```

Body:

```json
{
    "capacity": 12
}
```

This endpoint is protected by the admin token middleware and is the only endpoint without public access.

#### 7) View reservation history

`GET /api/reservations/{reservation}/history`

Example response:

```json
{
    "data": [
        {
            "id": "...",
            "reservation_id": "...",
            "action": "created",
            "old_data": null,
            "new_data": {
                "id": "...",
                "resource_id": 1,
                "units": 2,
                "status": "pending",
                "start_time": "2030-01-01T10:00:00.000000Z",
                "end_time": "2030-01-01T11:00:00.000000Z"
            },
            "created_at": "2030-01-01T09:59:00.000000Z"
        },
        {
            "id": "...",
            "reservation_id": "...",
            "action": "confirmed",
            "old_data": {
                "status": "pending"
            },
            "new_data": {
                "status": "confirmed"
            },
            "created_at": "2030-01-01T10:05:00.000000Z"
        }
    ]
}
```

---

## Key Design Decisions

### 1. Reservation expiry without a background worker

Rather than relying on a queued job or scheduled command to _flip_ a
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
- If the same key is reused with a _different_ request body, that's a
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
the new value is _lower_ than the current one, it computes the
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

| Challenge                                                          | Solution                                                                                                |
| ------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------- |
| Admin-only capacity control with **no** auth system in place       | Lightweight shared-secret middleware in front of just that one endpoint                                 |
| Preventing overbooking under real concurrent requests              | Row-level locking (`lockForUpdate`) inside DB transactions, re-checking capacity right before the write |
| Idempotent writes without a race in the idempotency check itself   | Optimistic `INSERT` + unique constraint, instead of `SELECT`-then-`INSERT`                              |
| Expiry correctness surviving server restarts / a stalled scheduler | Expiry is computed lazily from `expires_at` at read time, not flipped by a background process           |
| Testing concurrent behavior                                        | New territory for me — see below                                                                        |

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
