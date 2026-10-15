<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');
Route::post('/settings', [DashboardController::class, 'saveSettings'])->name('settings.save');
Route::post('/settings/test', [DashboardController::class, 'testConnection'])->name('settings.test');
Route::post('/courses/scan', [DashboardController::class, 'scanFolder'])->name('courses.scan');
Route::post('/courses/{id}/process', [DashboardController::class, 'processCourse'])->name('courses.process');
Route::get('/courses/{id}/preview', [DashboardController::class, 'preview'])->name('courses.preview');
