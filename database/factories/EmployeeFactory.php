<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'emp_code' => $this->faker->unique()->bothify('EMP###'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'department' => $this->faker->randomElement(['Engineering', 'HR', 'Sales', 'Marketing']),
            'course' => $this->faker->optional()->word(),
            'position' => $this->faker->jobTitle(),
            'photo_url' => null,
            'active' => true,
            'password' => null,
        ];
    }
}
