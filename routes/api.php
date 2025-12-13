<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\AttendanceController;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    // attendance actions
    Route::post('attendances/clock-in', [AttendanceController::class, 'clockIn'])->middleware('auth:sanctum');
    Route::post('attendances/clock-out', [AttendanceController::class, 'clockOut'])->middleware('auth:sanctum');

    // CRUD / listing / summary (protected)
    Route::get('attendances', [AttendanceController::class, 'index'])->middleware('auth:sanctum');
    Route::get('attendances/summary', [AttendanceController::class, 'summary'])->middleware('auth:sanctum');
    // Optionally register resource routes for Admin/HR to create/edit/delete attendances:
    // Route::apiResource('attendances', AttendanceController::class)->except(['store','update','destroy'])->middleware('auth:sanctum');
});

// Protected routes
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('users', [AuthController::class, 'createUser'])->middleware('can:create-user');
    Route::apiResource('employees', EmployeeController::class);
});