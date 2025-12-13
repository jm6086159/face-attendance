<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed a default test user (Laravel auth)
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Seed a demo employee with recent attendance
        $this->call(EmployeeDemoSeeder::class);

        // Seed two years of attendance history for all employees
        // Note: This can take time depending on employee count.
        $this->call(EmployeeTwoYearAttendanceSeeder::class);
    }
}
