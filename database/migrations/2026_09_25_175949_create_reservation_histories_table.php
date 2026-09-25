<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservation_histories', function (Blueprint $table) {
            $table->id();

            $table->uuid('reservation_id');

            $table->string('action');

            $table->json('old_data')->nullable();
            $table->json('new_data');
            $table->timestamp('created_at')->useCurrent();

            // Foreign key
            $table->foreign('reservation_id')
                ->references('id')->on('reservations')
                ->cascadeOnDelete();

            // Index
            $table->index('reservation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_histories');
    }
};
