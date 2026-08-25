<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Farm;
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
        $user = User::updateOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'password' => 'password',
            'role' => 'farmOwner',
        ]);

        $farm = Farm::firstOrCreate([
            'name' => 'Green Valley Farm',
        ], [
            'location' => 'Kenya',
        ]);

        $user->farms()->syncWithoutDetaching([$farm->id]);
    }
}
