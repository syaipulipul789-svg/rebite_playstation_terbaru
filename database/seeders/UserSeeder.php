<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Reza Pratama',
                'username' => 'owner',
                'email' => 'owner@rebite.test',
                'role' => UserRole::OWNER,
                'password' => 'password',
            ],
            [
                'name' => 'Budi Santoso',
                'username' => 'kasir01',
                'email' => 'kasir01@rebite.test',
                'role' => UserRole::KASIR,
                'password' => 'password',
            ],
            [
                'name' => 'Siti Aminah',
                'username' => 'kasir02',
                'email' => 'kasir02@rebite.test',
                'role' => UserRole::KASIR,
                'password' => 'password',
            ],
        ];

        foreach ($users as $data) {
            User::query()->updateOrCreate(
                ['username' => $data['username']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => $data['role'],
                    'password' => Hash::make($data['password']),
                    'is_active' => true,
                ],
            );
        }
    }
}
