<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $organiser = User::factory()->create([
            'name' => 'Demo Organiser',
            'email' => 'organiser@example.com',
        ]);

        $events = Event::factory()->count(5)->for($organiser, 'organiser')->create();
        Event::factory()->draft()->for($organiser, 'organiser')->create();

        User::factory()->count(10)->create()->each(function (User $user) use ($events) {
            Booking::factory()->for($user)->for($events->random())->create();
        });
    }
}
