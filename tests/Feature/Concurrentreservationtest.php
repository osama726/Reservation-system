<?php

namespace Tests\Feature;

use App\Actions\Reservation\CreateReservationAction;
use App\DTOs\CreateReservationData;
use App\Enums\ReservationStatus;
use App\Exceptions\CapacityExceededException;
use App\Models\Reservation;
use App\Models\Resource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * This test proves the "no overbooking" invariant holds under *real*
 * concurrent requests — not just sequential ones (see CreateReservationTest,
 * which already covers the sequential/logical case).
 *
 * Why pcntl_fork() instead of firing async HTTP calls:
 * A single PHPUnit process is inherently single-threaded, so calling the
 * app twice "at the same time" from within one process doesn't actually
 * exercise a race condition — one call always fully finishes before the
 * next starts. To get genuine concurrency we fork real OS processes, each
 * with its own PHP runtime and its own database connection, and have them
 * all attempt to book the same resource at (as close to) the same instant
 * as the OS scheduler allows.
 *
 * Why a real file-backed SQLite database instead of ":memory:":
 * The test suite's default connection (see phpunit.xml) is an in-memory
 * SQLite database, which only exists inside a single connection/process.
 * Forked children need to see and write to the *same* underlying data, so
 * this test points the "sqlite" connection at a temporary file for its
 * duration only, and restores nothing extra afterwards because Laravel
 * rebuilds the whole application container fresh for every test method.
 *
 * A note on what this actually proves on SQLite specifically:
 * Laravel's query grammar for SQLite does not emit "SELECT ... FOR UPDATE"
 * (SQLite has no such syntax), so `lockForUpdate()` is effectively a no-op
 * at the SQL level here. What still serializes the competing transactions
 * is SQLite's own whole-database writer lock: only one write transaction
 * can be in flight at a time, and every other writer blocks (up to
 * `busy_timeout`) until it commits. On a row-locking engine (Postgres,
 * MySQL/InnoDB) the same `lockForUpdate()` call additionally narrows that
 * to just the contended resource row, but the end result asserted below --
 * that the sum of booked units can never exceed capacity -- holds either
 * way, which is exactly the guarantee this test is verifying.
 */
class ConcurrentReservationTest extends TestCase
{
    private string $dbPath = '';

    private string $resultsDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pcntl')
            || ! function_exists('pcntl_fork')
            || ! function_exists('pcntl_waitpid')) {
            $this->markTestSkipped(
                'The pcntl extension is required to simulate real concurrent (multi-process) requests.'
            );
        }

        $this->dbPath = storage_path('framework/testing/concurrency_test_'.uniqid().'.sqlite');
        $this->resultsDir = storage_path('framework/testing/concurrency_results_'.uniqid());

        @unlink($this->dbPath);
        touch($this->dbPath);
        mkdir($this->resultsDir, 0777, true);

        // Point the sqlite connection at a real file (shared across
        // processes) instead of the suite's default ":memory:" database,
        // and give competing writers a chance to queue up behind the lock
        // rather than immediately failing with "database is locked".
        config([
            'database.connections.sqlite.database' => $this->dbPath,
            'database.connections.sqlite.busy_timeout' => 5000,
        ]);
        DB::purge('sqlite');

        $this->artisan('migrate', ['--force' => true])->run();
    }

    protected function tearDown(): void
    {
        if (config('database.default') === 'sqlite') {
            try {
                DB::disconnect('sqlite');
            } catch (Throwable) {
                // Ignore disconnected/absent database connections during skipped runs.
            }
        }

        if ($this->dbPath !== '') {
            @unlink($this->dbPath);
            @unlink($this->dbPath.'-journal');
            @unlink($this->dbPath.'-wal');
            @unlink($this->dbPath.'-shm');
        }

        if ($this->resultsDir !== '') {
            foreach (glob($this->resultsDir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->resultsDir);
        }

        parent::tearDown();
    }

    public function test_concurrent_requests_never_overbook_a_resource(): void
    {
        if (! function_exists('pcntl_fork') || ! function_exists('pcntl_waitpid')) {
            $this->markTestSkipped(
                'The pcntl extension is required to simulate real concurrent (multi-process) requests.'
            );
        }

        $capacity = 10;
        $unitsPerRequest = 3;
        $workers = 8; // 8 x 3 = 24 requested units against a capacity of 10

        $resource = Resource::create([
            'name' => 'Concurrency Test Room',
            'capacity' => $capacity,
        ]);

        $start = CarbonImmutable::parse('2030-06-01T10:00:00Z');
        $end = CarbonImmutable::parse('2030-06-01T11:00:00Z');

        // Disconnect before forking so each child process is forced to
        // establish its own, independent PDO connection/file handle rather
        // than inheriting (and corrupting) the parent's open connection.
        DB::disconnect('sqlite');

        $pids = [];

        for ($i = 0; $i < $workers; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('Could not fork a child process for the concurrency test.');
            }

            if ($pid === 0) {
                // --- Child process ---
                $this->attemptBookingInChildProcess(
                    resourceId: $resource->id,
                    units: $unitsPerRequest,
                    start: $start,
                    end: $end,
                    workerIndex: $i,
                );
                exit(0);
            }

            $pids[] = $pid;
        }

        // --- Back in the parent process: wait for every child to finish ---
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::reconnect('sqlite');

        $results = [];
        foreach (glob($this->resultsDir.'/*.json') as $file) {
            $results[] = json_decode(file_get_contents($file), true);
        }

        $this->assertCount($workers, $results, 'One or more worker processes did not report a result.');

        $successes = array_filter($results, fn ($r) => $r['outcome'] === 'created');
        $conflicts = array_filter($results, fn ($r) => $r['outcome'] === 'capacity_exceeded');
        $unexpected = array_filter($results, fn ($r) => ! in_array($r['outcome'], ['created', 'capacity_exceeded'], true));

        $this->assertEmpty(
            $unexpected,
            'Unexpected errors during concurrent booking: '.json_encode(array_values($unexpected))
        );

        // Since every request asks for the same number of units, the number
        // of requests that *can* succeed is fixed by simple arithmetic,
        // regardless of which process happens to win the race with the OS
        // scheduler. This makes the assertion deterministic rather than
        // just "less than or equal", giving much stronger confidence that
        // the locking is actually doing its job and not just getting lucky.
        $expectedSuccesses = intdiv($capacity, $unitsPerRequest);
        $expectedConflicts = $workers - $expectedSuccesses;

        $this->assertCount(
            $expectedSuccesses,
            $successes,
            "Expected exactly {$expectedSuccesses} successful bookings out of {$workers} concurrent attempts."
        );
        $this->assertCount($expectedConflicts, $conflicts);

        // The assertion that actually matters: whatever ended up persisted
        // in the database must never exceed the resource's capacity.
        $bookedUnits = Reservation::query()
            ->where('resource_id', $resource->id)
            ->where('status', ReservationStatus::Pending->value)
            ->sum('units');

        $this->assertLessThanOrEqual(
            $capacity,
            $bookedUnits,
            "Overbooking detected: {$bookedUnits} units booked against a capacity of {$capacity}."
        );

        $this->assertSame(count($successes) * $unitsPerRequest, $bookedUnits);
    }

    private function attemptBookingInChildProcess(
        int $resourceId,
        int $units,
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $workerIndex,
    ): void {
        // Force a brand new connection object in this process (as opposed
        // to relying on the disconnected one copied over at fork time),
        // then intentionally jitter the start slightly so the workers
        // don't all hit the lock in perfect lock-step, which is closer to
        // how real concurrent HTTP requests actually arrive.
        DB::purge('sqlite');
        usleep(random_int(0, 20_000));

        $outcome = 'unknown';
        $message = null;

        try {
            /** @var CreateReservationAction $action */
            $action = app(CreateReservationAction::class);

            $action(new CreateReservationData(
                resourceId: $resourceId,
                units: $units,
                startTime: $start,
                endTime: $end,
            ));

            $outcome = 'created';
        } catch (CapacityExceededException $e) {
            $outcome = 'capacity_exceeded';
            $message = $e->getMessage();
        } catch (Throwable $e) {
            $outcome = 'error:'.$e::class;
            $message = $e->getMessage();
        }

        file_put_contents(
            $this->resultsDir."/worker-{$workerIndex}.json",
            json_encode(['worker' => $workerIndex, 'outcome' => $outcome, 'message' => $message])
        );
    }
}
