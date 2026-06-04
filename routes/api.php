<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailableSlotController;
use App\Http\Controllers\Api\ClinicController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DoctorTimeOffController;
use App\Http\Controllers\Api\DoctorWorkingHourController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/clinics/current', [ClinicController::class, 'current']);
    Route::patch('/clinics/current', [ClinicController::class, 'update']);

    Route::apiResource('users', UserController::class);
    Route::patch('/users/{user}/role', [UserController::class, 'changeRole']);

    Route::apiResource('doctors', DoctorController::class);

    Route::get('/doctors/{doctor}/working-hours', [DoctorWorkingHourController::class, 'index']);
    Route::put('/doctors/{doctor}/working-hours', [DoctorWorkingHourController::class, 'update']);

    Route::get('/doctors/{doctor}/time-offs', [DoctorTimeOffController::class, 'index']);
    Route::post('/doctors/{doctor}/time-offs', [DoctorTimeOffController::class, 'store']);
    Route::delete('/doctors/{doctor}/time-offs/{timeOff}', [DoctorTimeOffController::class, 'destroy']);

    Route::get('/doctors/{doctor}/available-slots', AvailableSlotController::class);

    Route::apiResource('patients', PatientController::class);

    Route::apiResource('appointments', AppointmentController::class)
        ->except(['destroy']);

    Route::post('/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::post('/appointments/{appointment}/complete', [AppointmentController::class, 'complete']);
    Route::post('/appointments/{appointment}/no-show', [AppointmentController::class, 'markNoShow']);
    Route::post('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);

    Route::apiResource('audit-logs', AuditLogController::class)
        ->only(['index', 'show']);
});
