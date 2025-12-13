<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        static $count = 1;
        $code = 'EMP-' . str_pad($count++, 3, '0', STR_PAD_LEFT);

        return [
            'employee_code' => $code,
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->phoneNumber,
            'gender' => $this->faker->randomElement(['male','female','other']),
            'date_of_birth' => $this->faker->optional()->date(),
            'address' => $this->faker->address,
            'department' => $this->faker->randomElement(['Engineering','HR','Finance','Sales']),
            'position' => $this->faker->jobTitle,
            'start_date' => $this->faker->date(),
            'salary' => $this->faker->randomFloat(2, 300, 10000),
            'status' => $this->faker->randomElement(['active','inactive']),
        ];
    }
}