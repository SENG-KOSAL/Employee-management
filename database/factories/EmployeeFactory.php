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

        $nationality = $this->faker->randomElement(['khmer', 'foreign']);
        $passport = $nationality === 'foreign'
            ? strtoupper($this->faker->randomLetter) . strtoupper($this->faker->randomLetter) . $this->faker->numerify('#######')
            : null;
        $workPermit = $nationality === 'foreign'
            ? 'WP-' . $this->faker->numerify('########')
            : null;

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

            // National & legal information
            'national_id_number' => $nationality === 'khmer' ? $this->faker->numerify('#########') : null,
            'nssf_number' => 'NSSF-' . $this->faker->numerify('########'),
            'passport_number' => $passport,
            'work_permit_number' => $workPermit,
            'nationality' => $nationality,

            // Emergency contact
            'emergency_contact_name' => $this->faker->name,
            'emergency_contact_phone' => $this->faker->phoneNumber,
            'emergency_contact_relationship' => $this->faker->randomElement(['Father', 'Mother', 'Spouse', 'Sibling', 'Friend']),
        ];
    }
}