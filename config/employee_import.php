<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Columns excluded from template/import payload
    |--------------------------------------------------------------------------
    */
    'excluded_columns' => [
        'id',
        'company_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ],

    /*
    |--------------------------------------------------------------------------
    | Force additional required columns
    |--------------------------------------------------------------------------
    */
    'required_columns' => [
        // 'employee_code',
        // 'first_name',
    ],

    /*
    |--------------------------------------------------------------------------
    | Extra foreign key rules (for columns without DB-level FK constraints)
    |--------------------------------------------------------------------------
    */
    'foreign_keys' => [
        // 'role_id' => ['table' => 'roles', 'column' => 'id'],
        // 'department_id' => ['table' => 'departments', 'column' => 'id'],
    ],
];
