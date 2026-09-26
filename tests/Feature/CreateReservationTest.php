<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_pending_reservation_within_capacity(): void
    {
        $resource = Resource::factory()->create(['capacity' => 10]);

        $response = $this->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'units' => 6,
            'start_time' => '2030-01-01T10:00:00Z',
            'end_time' => '2030-01-01T11:00:00Z',
        ], ['Idempotency-Key' => 'key-1']);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.units', 6);

        $this->assertDatabaseHas('reservations', [
            'resource_id' => $resource->id,
            'units' => 6,
            'status' => 'pending',
        ]);
    }

    public function test_tracks_every_reservation_change_in_history(): void
    {
        $resource = Resource::factory()->create(['capacity' => 10]);

        $createResponse = $this->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'units' => 2,
            'start_time' => '2030-01-01T10:00:00Z',
            'end_time' => '2030-01-01T11:00:00Z',
        ], ['Idempotency-Key' => 'history-key-create']);

        $createResponse->assertStatus(201);

        $reservation = Reservation::query()->firstOrFail();

        $this->assertDatabaseHas('reservation_histories', [
            'reservation_id' => $reservation->id,
            'action' => 'created',
        ]);

        $this->putJson('/api/reservations/'.$reservation->id, [
            'units' => 3,
            'start_time' => '2030-01-01T10:00:00Z',
            'end_time' => '2030-01-01T12:00:00Z',
        ], ['Idempotency-Key' => 'history-key-update'])->assertStatus(200);

        $this->assertDatabaseHas('reservation_histories', [
            'reservation_id' => $reservation->id,
            'action' => 'updated',
        ]);

        $this->postJson('/api/reservations/'.$reservation->id.'/confirm', [], ['Idempotency-Key' => 'history-key-confirm'])->assertStatus(200);

        $this->assertDatabaseHas('reservation_histories', [
            'reservation_id' => $reservation->id,
            'action' => 'confirmed',
        ]);

        $this->postJson('/api/reservations/'.$reservation->id.'/cancel', [], ['Idempotency-Key' => 'history-key-cancel'])->assertStatus(200);

        $this->assertDatabaseHas('reservation_histories', [
            'reservation_id' => $reservation->id,
            'action' => 'cancelled',
        ]);
    }

    public function test_rejects_reservation_that_would_exceed_capacity(): void
    {
        $resource = Resource::factory()->create(['capacity' => 10]);

        // matches the example in the spec: 6 booked from 10:00-11:00
        $this->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'units' => 6,
            'start_time' => '2030-01-01T10:00:00Z',
            'end_time' => '2030-01-01T11:00:00Z',
        ], ['Idempotency-Key' => 'key-a'])->assertStatus(201);

        // 4 units from 10:30-12:00 overlaps and fits exactly (6+4=10)
        $this->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'units' => 4,
            'start_time' => '2030-01-01T10:30:00Z',
            'end_time' => '2030-01-01T12:00:00Z',
        ], ['Idempotency-Key' => 'key-b'])->assertStatus(201);

        // one more unit overlapping 10:30-11:00 would push it to 11 > 10
        $response = $this->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'units' => 1,
            'start_time' => '2030-01-01T10:45:00Z',
            'end_time' => '2030-01-01T10:50:00Z',
        ], ['Idempotency-Key' => 'key-c']);

        $response->assertStatus(409)
            ->assertJsonPath('error', 'capacity_exceeded');
    }

    public function test_non_overlapping_reservations_do_not_compete_for_capacity(): void
    {
        $resource = Resource::factory()->create(['capacity' => 5]);

        $this->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'units' => 5,
            'start_time' => '2030-01-01T09:00:00Z',
            'end_time' => '2030-01-01T10:00:00Z',
        ], ['Idempotency-Key' => 'key-1'])->assertStatus(201);

        // starts exactly when the previous one ends -> no overlap
        $this->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'units' => 5,
            'start_time' => '2030-01-01T10:00:00Z',
            'end_time' => '2030-01-01T11:00:00Z',
        ], ['Idempotency-Key' => 'key-2'])->assertStatus(201);
    }

    public function test_returns_reservation_history_for_a_reservation(): void
    {
        $resource = Resource::factory()->create(['capacity' => 10]);

        $response = $this->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'units' => 2,
            'start_time' => '2030-01-01T10:00:00Z',
            'end_time' => '2030-01-01T11:00:00Z',
        ], ['Idempotency-Key' => 'history-endpoint-key']);

        $response->assertStatus(201);

        $reservation = Reservation::query()->firstOrFail();

        $historyResponse = $this->getJson('/api/reservations/'.$reservation->id.'/history');

        $historyResponse->assertStatus(200)
            ->assertJsonPath('data.0.reservation_id', $reservation->id)
            ->assertJsonPath('data.0.action', 'created');
    }

    public function test_requires_idempotency_key_header(): void
    {
        $resource = Resource::factory()->create();

        $response = $this->postJson('/api/reservations', [
            'resource_id' => $resource->id,
            'units' => 1,
            'start_time' => '2030-01-01T10:00:00Z',
            'end_time' => '2030-01-01T11:00:00Z',
        ]);

        $response->assertStatus(422);
    }
}
