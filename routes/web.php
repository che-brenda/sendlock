<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\OrganizationManagementController;
use App\Http\Controllers\AuditLogController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});


Route::post(
    '/users/{user}/activate',
    [UserManagementController::class, 'activate']
)->name('users.activate');

Route::post(
    '/users/{user}/deactivate',
    [UserManagementController::class, 'deactivate']
)->name('users.deactivate');


Route::middleware(['auth'])->group(function () {

    Route::resource('departments', DepartmentController::class);
    Route::resource('users', UserManagementController::class);

});

Route::get('/super-admin-test', function () {
    return 'Super Admin Middleware Works';
})->middleware(['auth', 'superadmin']);

Route::middleware([
    'auth',
    'superadmin'
])->group(function () {

    Route::resource(
        'organizations',
        OrganizationManagementController::class
    );

});

require __DIR__.'/auth.php';
