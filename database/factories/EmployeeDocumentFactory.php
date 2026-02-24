<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeDocumentFactory extends Factory
{
    protected $model = EmployeeDocument::class;

    public function definition(): array
    {
        $employeeCode = 'EMP-' . str_pad((string) $this->faker->numberBetween(1, 999), 3, '0', STR_PAD_LEFT);

        return [
            'employee_id' => Employee::factory(),
            'id_card_file_path' => "documents/ids/{$employeeCode}-id-card.pdf",
            'contract_file_path' => "documents/contracts/{$employeeCode}-contract.pdf",
            'cv_file_path' => "documents/cv/{$employeeCode}-cv.pdf",
            'certificate_file_path' => $this->faker->optional()->randomElement([
                "documents/certificates/{$employeeCode}-certificate.pdf",
                "documents/certificates/{$employeeCode}-certificate.jpg",
            ]),
        ];
    }
}
