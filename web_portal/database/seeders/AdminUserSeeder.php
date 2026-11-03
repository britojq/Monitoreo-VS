<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'britojq@gmail.com'],
            [
                'name' => 'Operador ATIT',
                'password' => Hash::make("PeneloPe91*"),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
    }
}
