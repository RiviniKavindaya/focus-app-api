<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@focusapp.com',
            'password' => Hash::make('password123'),

            // optional fields
            'avatar' => null,
            'provider' => null,
            'provider_id' => null,
        ]);

        User::create([
            'name' => 'Test User',
            'email' => 'test@focusapp.com',
            'password' => Hash::make('password123'),

            'avatar' => null,
            'provider' => null,
            'provider_id' => null,
        ]);
    }
}
