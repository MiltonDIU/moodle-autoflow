<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UniversityController;

// Public Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected Routes (Require Login)
Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');
    Route::post('/settings', [DashboardController::class, 'saveSettings'])->name('settings.save');
    Route::post('/settings/test', [DashboardController::class, 'testConnection'])->name('settings.test');
    Route::post('/courses/scan', [DashboardController::class, 'scanFolder'])->name('courses.scan');
    Route::post('/courses/{id}/process', [DashboardController::class, 'processCourse'])->name('courses.process');
    Route::get('/courses/{id}/preview', [DashboardController::class, 'preview'])->name('courses.preview');

    // Universities Multi-Tenant Management
    Route::get('/universities', [UniversityController::class, 'index'])->name('universities.index');
    Route::post('/universities', [UniversityController::class, 'store'])->name('universities.store');
    Route::post('/universities/{id}/switch', [UniversityController::class, 'switchUniversity'])->name('universities.switch');
});
