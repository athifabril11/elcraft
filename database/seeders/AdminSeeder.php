<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@elcraft.id'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('Admin@123'),
                'phone' => '081234567890',
                'role' => 'admin',
                'is_active' => true,
            ]
        );
    }
}