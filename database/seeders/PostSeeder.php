<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the admin user
        $admin = \App\Models\User::where('email', 'admin@laravel.com')->first();

        if ($admin) {
            // Create posts for the admin user
            \App\Models\Post::factory(3)->create([
                'user_id' => $admin->id,
            ]);

            // Create some additional sample posts with fake users
            \App\Models\Post::factory(3)->create();
        }
    }
}
