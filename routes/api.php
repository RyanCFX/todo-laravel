<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

/*
 * API Routes
 */

Route::post('auth/signup', [AuthController::class, 'signup'])->name('auth.signup');
Route::post('auth/signin', [AuthController::class, 'signin'])->name('auth.signin');
Route::post('auth/signout', [AuthController::class, 'signout'])->name('auth.signout');

Route::middleware('auth')->group(function () {
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/tasks/{task_id}', [TaskController::class, 'getTaskById'])->name('tasks.byId');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('/tasks/{task_id}', [TaskController::class, 'update'])->name('tasks.update');
    Route::get('/tasks/{task_id}/history', [TaskController::class, 'history'])->name('tasks.history');
    Route::delete('/tasks/{task_id}', [TaskController::class, 'remove'])->name('tasks.remove');
});
