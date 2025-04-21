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
        $user = Auth::user();
        $tasks = Task::where('user_id', $user->user_id)
            ->whereNull('deleted_at')
            ->where('status', true)
            ->get();

        return response()->json($tasks);
    }

    public function getTaskById(string $task_id): JsonResponse
    {
        $user = Auth::user();
        $task = Task::where('user_id', $user->user_id)
            ->whereNull('deleted_at')
            ->where('status', true)
            ->where('task_id', $task_id)
            ->first();

        return response()->json($task);
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
                'success' => false,
                'data' => $th,
                'message' => 'Tarea creada exitosamente'
            ], 500);
        }
    }

    public function update(Request $request, string $task_id)
    {
        $task = Task::where('task_id', $task_id)->where('user_id', Auth::id())->firstOrFail();

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
    }

    public function history(string $task_id): JsonResponse
    {
        $user = Auth::user();
        $history = [];
        $currentTask = Task::where('task_id', $task_id)
            ->where('user_id', $user->user_id)
            ->first();

        while ($currentTask) {
            $history[] = $currentTask;
            $currentTask = $currentTask->previous_task_id
                ? Task::where('task_id', $currentTask->previous_task_id)
                    ->where('user_id', $user->user_id)
                    ->first()
                : null;
        }

        return response()->json($history);
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
                'success' => false,
                'message' => 'Tarea no encontrada, no pertenece al usuario o ya está inactiva'
            ], 404);
        }

        // Realizar eliminación suave
        $task->delete();

        return response()->json([
            'message' => 'Tarea eliminada exitosamente'
        ], 200);
    }
}
