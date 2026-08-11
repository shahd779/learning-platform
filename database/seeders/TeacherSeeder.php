<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = [
            [
                'name' => 'د. أحمد محمد',
                'phone' => '01012345678',
                'password' => 'password',
                'role' => 'teacher',
            ],
            [
                'name' => 'د. محمد علي',
                'phone' => '01098765432',
                'password' => 'password',
                'role' => 'teacher',
            ],
            [
                'name' => 'د. علي حسن',
                'phone' => '01011111111',
                'password' => 'password',
                'role' => 'teacher',
            ],
            [
                'name' => 'د. سارة خالد',
                'phone' => '01022222222',
                'password' => 'password',
                'role' => 'teacher',
            ],
        ];

        foreach ($teachers as $teacher) {
            User::create([
                'name' => $teacher['name'],
                'phone' => $teacher['phone'],
                'password' => Hash::make($teacher['password']),
                'role' => $teacher['role'],
                'is_active' => true,
            ]);
        }
    }
}