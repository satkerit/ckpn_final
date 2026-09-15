<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@ckpn.local'],
            [
                'name' => 'Admin CKPN',
                'password' => Hash::make('password'),
            ]
        );

        // Assign super_admin role jika belum punya role apapun
        if ($user->roles->isEmpty()) {
            $user->assignRole('super_admin');
        }
    }
}
