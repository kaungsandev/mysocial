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
        // 1. Categories
        $this->call(CategorySeeder::class);

        // 2. Specialized Users
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => 'password', // UserFactory uses Hash::make by default or I can pass it
        ]);

        User::factory()->create([
            'name' => 'User',
            'email' => 'user@gmail.com',
            'password' => 'password',
        ]);

        // 3. More users to reach 100
        User::factory()->count(98)->create();

        // 4. Posts
        $this->call(PostSeeder::class);
    }
}
