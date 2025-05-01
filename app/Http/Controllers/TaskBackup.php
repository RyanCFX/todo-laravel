<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Ramsey\Uuid\Uuid;

class TaskController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();
            $tasks = Task::where('user_id', $user->user_id)
                ->whereNull('deleted_at')
                ->where('status', true)
                ->get();

            return response()->json($tasks);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al recuperar las tareas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getTaskById(string $task_id): JsonResponse
    {
        try {
            $user = Auth::user();
            $task = Task::where('user_id', $user->user_id)
                ->whereNull('deleted_at')
                ->where('status', true)
                ->where('task_id', $task_id)
                ->first();

            if (!$task) {
                return response()->json([
                    'message' => 'Tarea no encontrada'
                ], 404);
            }

            return response()->json($task);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al recuperar la tarea',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'due_date' => 'required|date',
                'reminder_offset_minutes' => 'nullable|integer',
                'previous_task_id' => 'nullable|uuid|exists:tasks,task_id',
            ]);

            $user = Auth::user();
            $task = Task::create([
                'task_id' => Uuid::uuid4()->toString(),
                'user_id' => $user->user_id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'due_date' => $validated['due_date'],
                'reminder_offset_minutes' => $validated['reminder_offset_minutes'] ?? null,
                // 'status' => $validated['status'] ?? true,
                'previous_task_id' => $validated['previous_task_id'] ?? null,
            ]);

            return response()->json($task, 201);
        } catch (\Throwable $th) {
            return response()->json([
                'data' => $th,
                'message' => 'Tarea creada exitosamente'
            ], 500);
        }
    }

    public function update(Request $request, string $task_id): JsonResponse
    {
        try {
            $task = Task::where('task_id', $task_id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$task) {
                return response()->json([
                    'message' => 'Tarea no encontrada'
                ], 404);
            }

            // Guardar el estado actual en el historial antes de actualizar
            TaskHistory::create([
                'history_id' => \Str::uuid(),
                'task_id' => $task->task_id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'due_date' => $task->due_date,
                'reminder_offset_minutes' => $task->reminder_offset_minutes,
            ]);

            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'due_date' => 'nullable|date',
                'reminder_offset_minutes' => 'nullable|integer|in:5,10,15,20,30,60,1440',
                'status' => 'nullable|boolean',
                'file' => 'nullable|file|mimes:pdf,jpg,png|max:5120',
            ]);

            $task->update($validated);

            if ($request->hasFile('file')) {
                $task->file_path = $request->file('file')->store('tasks', 'public');
                $task->save();
            }

            return response()->json($task);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la tarea',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function history(string $task_id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Obtener la tarea actual
            $currentTask = Task::where('task_id', $task_id)
                ->where('user_id', $user->user_id)
                ->whereNull('deleted_at')
                ->first();

            if (!$currentTask) {
                return response()->json([
                    'message' => 'Tarea no encontrada'
                ], 404);
            }

            // Obtener el historial de la tarea
            $history = TaskHistory::where('task_id', $task_id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();

            // Agregar la versión actual de la tarea al inicio del historial
            $currentTaskData = [
                'history_id' => null,
                'task_id' => $currentTask->task_id,
                'title' => $currentTask->title,
                'description' => $currentTask->description,
                'status' => $currentTask->status,
                'due_date' => $currentTask->due_date,
                'reminder_offset_minutes' => $currentTask->reminder_offset_minutes,
                'is_current' => true
            ];

            array_unshift($history, $currentTaskData);

            return response()->json($history);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al recuperar el historial',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function remove(string $task_id): JsonResponse
    {
        $user = Auth::user();

        // Buscar la tarea
        $task = Task::where('task_id', $task_id)
            ->where('user_id', $user->user_id)
            ->whereNull('deleted_at')
            ->where('status', true)
            ->first();

        if (!$task) {
            return response()->json([
                'message' => 'Tarea no encontrada, no pertenece al usuario o ya está inactiva'
            ], 404);
        }

        $task->delete();

        return response()->json([
            'message' => 'Tarea eliminada exitosamente'
        ], 200);
    }
}
