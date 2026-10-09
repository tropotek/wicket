<?php

namespace Database\Seeders;

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
        // Dev/test accounts, password "password".
        User::factory()->admin()->create([
            'username' => 'admin',
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        User::factory()->editor()->create([
            'username' => 'editor',
            'name' => 'Editor User',
            'email' => 'editor@example.com',
        ]);

        User::factory()->create([
            'username' => 'member',
            'name' => 'Member User',
            'email' => 'member@example.com',
        ]);
    }
}
