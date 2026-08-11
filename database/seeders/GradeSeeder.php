<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Grade;

class GradeSeeder extends Seeder
{
    public function run(): void
    {
        $grades = [
            ['name' => 'الصف الأول الثانوي', 'code' => 'G1'],
            ['name' => 'الصف الثاني الثانوي', 'code' => 'G2'],
            ['name' => 'الصف الثالث الثانوي', 'code' => 'G3'],
        ];

        foreach ($grades as $grade) {
            Grade::create($grade);
        }
    }
}