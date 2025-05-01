<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskBackupController;
use App\Http\Controllers\TaskXmlController;
use App\Http\Controllers\PushNotificationController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController as ApiAuthController;

/*
 * API Routes
 */

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('auth/signup', [AuthController::class, 'signup'])->name('auth.signup');
Route::post('auth/signin', [AuthController::class, 'signin'])->name('auth.signin');
Route::post('auth/signout', [AuthController::class, 'signout'])->name('auth.signout');

Route::middleware('auth')->group(function () {
    // Rutas para importación/exportación XML
    Route::prefix('tasks-xml')->group(function () {
        Route::post('', [TaskXmlController::class, 'import'])->name('tasks.import-xml');
        Route::get('', [TaskXmlController::class, 'export'])->name('tasks.export-xml');
    });

    // Rutas de tareas
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task_id}', [TaskController::class, 'getTaskById'])->name('tasks.byId');
    Route::put('/tasks/{task_id}', [TaskController::class, 'update'])->name('tasks.update');
    Route::get('/tasks/{task_id}/history', [TaskController::class, 'history'])->name('tasks.history');
    Route::delete('/tasks/{task_id}', [TaskController::class, 'remove'])->name('tasks.remove');

    // Rutas para backups
    Route::post('/backups', [TaskBackupController::class, 'createBackup'])->name('backups.create');
    Route::get('/backups', [TaskBackupController::class, 'getBackups'])->name('backups.index');
    Route::post('/backups/{backup_id}/restore', [TaskBackupController::class, 'restoreBackup'])->name('backups.restore');
    Route::delete('/backups/{backup_id}', [TaskBackupController::class, 'deleteBackup'])->name('backups.delete');

    // Rutas para push notifications
    Route::post('/push-token', [PushNotificationController::class, 'registerToken'])->name('push-token.register');
    Route::delete('/push-token', [PushNotificationController::class, 'removeToken'])->name('push-token.remove');
});
