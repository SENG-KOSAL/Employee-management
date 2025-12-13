<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_employees(): void
    {
        Employee::factory()->count(3)->create();

        $resp = $this->getJson('/api/v1/employees');

        $resp->assertStatus(200)
             ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_create_employee(): void
    {
        $payload = Employee::factory()->make()->toArray();
        $payload['employee_code'] = 'EMP-500';
        $payload['email'] = 'unique@example.com';

        $resp = $this->postJson('/api/v1/employees', $payload);

        $resp->assertStatus(201)
             ->assertJsonFragment(['employee_code' => 'EMP-500', 'email' => 'unique@example.com']);
    }
}