<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'firstName' => 'Test',
            'lastName' => 'User',
            'username' => 'Test User',
            'slug' => 'test-user',
            'email' => 'test@example.com',
        ]);

        $this->call(RolePermissionSeeder::class);
    }
}
