<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            // UUID primary key: lets clients pre-generate the id (useful together
            // with the Idempotency-Key) and avoids leaking sequential ids.
            $table->uuid('id')->primary();

            $table->unsignedBigInteger('reservation_number')->unique();
            $table->unsignedInteger('units');
            $table->string('status')->default('pending');

            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->dateTime('expires_at')->nullable();

            // optimistic-locking guard on top of the DB row lock, mainly useful
            // if an update ever happens outside the pessimistic-lock transaction
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            // Foreign key
            $table->foreignId('resource_id')
                ->constrained('resources')
                ->cascadeOnDelete();

            $table->index(
                ['resource_id', 'status', 'start_time', 'end_time'],
                'idx_reservations_conflict'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
