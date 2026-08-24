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
        // User::factory(10)->create();

        

        $this->call([
            AdminUserSeeder::class,
            TeacherSeeder::class,
            GradeSeeder::class,
            SubjectSeeder::class,
            PackageSeeder::class,
            StudentSeeder::class,
            PaymentSeeder::class,
            ComplaintSeeder::class,

        ]);
    }
}
