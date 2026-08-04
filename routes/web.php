<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Models\Plan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
    ]);
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('projects', ProjectController::class);
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
