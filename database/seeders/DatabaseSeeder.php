<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create only one predefined admin user
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@laravel.com',
            'password' => bcrypt('admin123'), // You can change this password
            'role' => 'admin',
        ]);
        User::factory()->create([
            'name' => 'Editor',
            'email' => 'editor@laravel.com',
            'password' => bcrypt('editor123'), // You can change this password
            'role' => 'editor',
        ]);
        User::factory()->create([
            'name' => 'Viewer',
            'email' => 'viewer@laravel.com',
            'password' => bcrypt('viewer123'), // You can change this password
            'role' => 'viewer',
        ]);
        

        // Seed posts
        $this->call(PostSeeder::class);
    }
}
