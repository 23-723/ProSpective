<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AcademicDashboardController;
use App\Http\Controllers\AcademicExportController;

Route::get('/', [AcademicDashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/data', [AcademicDashboardController::class, 'data'])->name('dashboard.data');
Route::get('/dashboard/table', [AcademicDashboardController::class, 'table'])->name('dashboard.table');
Route::get('/dashboard/export', AcademicExportController::class)->name('dashboard.export');
