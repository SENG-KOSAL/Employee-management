<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $role = $this->faker->randomElement(User::ROLES);

        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            // Use a fixed password for local/dev seeds; it will be hashed by the model mutator.
            'password' => 'password', // hashed by User::setPasswordAttribute
            'role' => $role,
            'employee_id' => null, // populate later if needed
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Convenience states
     */
    public function admin()
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    public function hr()
    {
        return $this->state(fn () => ['role' => 'hr']);
    }

    public function manager()
    {
        return $this->state(fn () => ['role' => 'manager']);
    }

    public function employee()
    {
        return $this->state(fn () => ['role' => 'employee']);
    }
}