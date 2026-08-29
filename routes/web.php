<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

/*
|--------------------------------------------------------------------------
| Authenticated routes
|
| "active" re-checks account status on every request, so suspending a user
| ends their session rather than waiting for it to expire.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    Route::controller(CustomerController::class)->prefix('customers')->name('customers.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:customers.view')->name('index');
        Route::get('create', 'create')->middleware('permission:customers.create')->name('create');
        Route::post('/', 'store')->middleware('permission:customers.create')->name('store');
        Route::get('{customer}', 'show')->middleware('permission:customers.view')->name('show');
        Route::get('{customer}/edit', 'edit')->middleware('permission:customers.update')->name('edit');
        Route::put('{customer}', 'update')->middleware('permission:customers.update')->name('update');
        Route::delete('{customer}', 'destroy')->middleware('permission:customers.delete')->name('destroy');
        Route::post('{customer}/restore', 'restore')->middleware('permission:customers.delete')->name('restore');
    });

    // Administration. Abilities are enforced here as well as in the form
    // requests, so a hidden sidebar link is never the only thing stopping a
    // request from going through.
    Route::controller(UserController::class)->prefix('users')->name('users.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:users.view')->name('index');
        Route::get('create', 'create')->middleware('permission:users.create')->name('create');
        Route::post('/', 'store')->middleware('permission:users.create')->name('store');
        Route::get('{user}', 'show')->middleware('permission:users.view')->name('show');
        Route::get('{user}/edit', 'edit')->middleware('permission:users.update')->name('edit');
        Route::put('{user}', 'update')->middleware('permission:users.update')->name('update');
        Route::delete('{user}', 'destroy')->middleware('permission:users.delete')->name('destroy');
    });

    Route::controller(RoleController::class)->prefix('roles')->name('roles.')->group(function (): void {
        Route::get('/', 'index')->middleware('permission:roles.view')->name('index');
        Route::get('create', 'create')->middleware('permission:roles.manage')->name('create');
        Route::post('/', 'store')->middleware('permission:roles.manage')->name('store');
        Route::get('{role}/edit', 'edit')->middleware('permission:roles.manage')->name('edit');
        Route::put('{role}', 'update')->middleware('permission:roles.manage')->name('update');
        Route::delete('{role}', 'destroy')->middleware('permission:roles.manage')->name('destroy');
    });
});
