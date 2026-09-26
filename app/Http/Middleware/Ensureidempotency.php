<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every write request (POST/PUT/PATCH) must send an `Idempotency-Key` header.
 *
 * Race safety comes from the UNIQUE constraint on idempotency_keys.idempotency_key,
 * not from a SELECT-then-INSERT check (which would itself have a race window).
 * We optimistically INSERT; if it fails on the unique constraint, someone else
 * already claimed the key and we branch on their record instead.
 */
class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return response()->json([
                'message' => 'The Idempotency-Key header is required for write requests.',
            ], 422);
        }

        $requestHash = hash('sha256', $request->getContent());

        try {
            IdempotencyKey::query()->create([
                'idempotency_key' => $key,
                'request_path' => $request->path(),
                'request_hash' => $requestHash,
                'status' => 'processing',
            ]);
        } catch (QueryException $e) {
            return $this->handleExistingKey($key, $requestHash);
        }

        // We won the race to process this key: run the real request.
        $response = $next($request);

        IdempotencyKey::query()->where('idempotency_key', $key)->update([
            'status' => 'completed',
            'response_status' => $response->getStatusCode(),
            'response_body' => json_decode($response->getContent(), true),
        ]);

        return $response;
    }

    private function handleExistingKey(string $key, string $requestHash): Response
    {
        $existing = IdempotencyKey::query()->where('idempotency_key', $key)->first();

        if (! $existing) {
            // Extremely unlikely (deleted between the failed insert and this
            // read) — treat as a transient conflict rather than crash.
            return response()->json([
                'message' => 'Could not process idempotency key, please retry.',
            ], 409);
        }

        if ($existing->request_hash !== $requestHash) {
            return response()->json([
                'message' => 'This Idempotency-Key was already used with a different request body.',
            ], 422);
        }

        if ($existing->status === 'completed') {
            return response()->json(
                $existing->response_body,
                $existing->response_status ?? 200
            );
        }

        // Another request with the same key is still being processed right now.
        return response()->json([
            'message' => 'A request with this Idempotency-Key is already being processed.',
        ], 409);
    }
}
