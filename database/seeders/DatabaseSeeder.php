<?php

namespace Database\Seeders;

use App\Models\Reservation;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Resource::factory()->count(3)->create()->each(function ($resource) {

            Reservation::factory()->count(2)->create([
                'resource_id' => $resource->id,
            ]);

            Reservation::factory()->confirmed()->count(2)->create([
                'resource_id' => $resource->id,
            ]);

            Reservation::factory()->cancelled()->create([
                'resource_id' => $resource->id,
            ]);
        });

    }
}
