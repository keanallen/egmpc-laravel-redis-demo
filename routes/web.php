<?php
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
Route::patch('/employees/{employee}/attendance/{attendanceRecord}', [EmployeeController::class, 'update'])->name('attendance.update');
