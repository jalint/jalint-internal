<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerTypeController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\RegulationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SampleMatrixController;
use App\Http\Controllers\SampleTypeController;
use App\Http\Controllers\TestMethodController;
use App\Http\Controllers\TestPackageController;
use App\Http\Controllers\TestParameterController;
// use App\Http\Controllers\UserController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/users/login', [AuthController::class, 'login']);
Route::post('/customer/login', [AuthController::class, 'loginCustomer']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('roles', RoleController::class);
    Route::patch('/customers/{customer}/reset-password', [CustomerController::class, 'resetPassword']);
    Route::apiResource('customers/types', CustomerTypeController::class);

    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('sample-types', SampleTypeController::class);
    Route::apiResource('sample-matrices', SampleMatrixController::class);
    Route::apiResource('regulations', RegulationController::class);
    Route::apiResource('test-methods', TestMethodController::class);
    Route::apiResource('test-parameters', TestParameterController::class);
    Route::apiResource('test-packages', TestPackageController::class);
    Route::apiResource('positions', PositionController::class);
    Route::apiResource('certifications', CertificationController::class);
    Route::apiResource('employees', EmployeeController::class);

    Route::post('/employees/{employee}/photo', [EmployeeController::class, 'uploadPhoto']);

    Route::apiResource('users', UserController::class);
});
