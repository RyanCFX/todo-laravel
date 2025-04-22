<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 10);
            $perPage = min(max($perPage, 1), 100);
            $user = Auth::user();

            // Crear una clave única para el caché basada en los parámetros de la solicitud
            $cacheKey = 'tasks_' . $user->user_id . '_'
                . $perPage . '_'
                . $request->input('start_date', '') . '_'
                . $request->input('end_date', '') . '_'
                . $request->input('status_code', '') . '_'
                . $request->input('search', '');

            // Usar tags para todas las claves de caché
            return Cache::tags(['tasks_' . $user->user_id])->remember($cacheKey, now()->addMinutes(10), function () use ($request, $user, $perPage) {
                $query = Task::select('tasks.*', 'status.description as status_description', 'status.color as status_color')
                    ->join('status', 'tasks.status_code', '=', 'status.status_code')
                    ->where('user_id', $user->user_id)
                    ->whereNull('deleted_at')
                    ->where('tasks.status', true);

                // Filtro por rango de fechas de creación
                if ($request->has('start_date')) {
                    $query->whereDate('created_at', '>=', $request->input('start_date'));
                }
                if ($request->has('end_date')) {
                    $query->whereDate('created_at', '<=', $request->input('end_date'));
                }

                if ($request->has('status_code')) {
                    $query->where('status_code', '=', $request->input('status_code'));
                }

                // Filtro por búsqueda en título y descripción
                if ($request->has('search')) {
                    $searchTerm = $request->input('search');
                    $query->where(function ($q) use ($searchTerm) {
                        $q
                            ->where('title', 'like', '%' . $searchTerm . '%')
                            ->orWhere('description', 'like', '%' . $searchTerm . '%');
                    });
                }

                $tasks = $query->orderBy('created_at', 'desc')->paginate($perPage);

                return response()->json([
                    'data' => $tasks->items(),
                    'current_page' => $tasks->currentPage(),
                    'per_page' => $tasks->perPage(),
                    'last_page' => $tasks->lastPage(),
                    'total' => $tasks->total(),
                    'filters' => [
                        'search' => $request->input('search'),
                        'start_date' => $request->input('start_date'),
                        'end_date' => $request->input('end_date'),
                    ],
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al recuperar las tareas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getTaskById(string $task_id): JsonResponse
    {
        try {
            $user = Auth::user();
            $cacheKey = 'task_' . $task_id . '_' . $user->user_id;

            // Usar tags para la caché de tareas individuales
            $task = Cache::tags(['task_' . $user->user_id])->remember($cacheKey, now()->addMinutes(10), function () use ($user, $task_id) {
                return Task::where('user_id', $user->user_id)
                    ->whereNull('deleted_at')
                    ->where('status', true)
                    ->where('task_id', $task_id)
                    ->first();
            });

            if (!$task) {
                return response()->json([
                    'message' => 'Tarea no encontrada',
                ], 404);
            }

            return response()->json($task);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al recuperar la tarea',
                'error' => $e->getMessage(),
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

            // Invalidar la caché de la lista de tareas
            Cache::tags(['tasks_' . $user->user_id])->flush();

            $task = Task::create([
                'user_id' => $user->user_id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'due_date' => $validated['due_date'],
                'reminder_offset_minutes' => $validated['reminder_offset_minutes'] ?? null,
                'status' => true,
                'previous_task_id' => $validated['previous_task_id'] ?? null,
            ]);

            return response()->json($task, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la tarea',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, string $task_id): JsonResponse
    {
        try {
            $task = Task::where('task_id', $task_id)
                ->where('user_id', Auth::id())
                ->whereNull('deleted_at')
                ->first();

            if (!$task) {
                return response()->json([
                    'message' => 'Tarea no encontrada',
                ], 404);
            }

            // Invalidar la caché de la tarea específica y la lista de tareas
            Cache::tags(['task_' . Auth::id()])->flush();
            Cache::tags(['tasks_' . Auth::id()])->flush();

            TaskHistory::create([
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

            $task->update(array_filter($validated, fn($value) => !is_null($value)));

            if ($request->hasFile('file')) {
                $task->file_path = $request->file('file')->store('tasks', 'public');
                $task->save();
            }

            return response()->json($task);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la tarea',
                'error' => $e->getMessage(),
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
                    'message' => 'Tarea no encontrada',
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
                'is_current' => true,
            ];

            array_unshift($history, $currentTaskData);

            return response()->json($history);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al recuperar el historial',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function remove(string $task_id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Buscar la tarea
            $task = Task::where('task_id', $task_id)
                ->where('user_id', $user->user_id)
                ->whereNull('deleted_at')
                ->where('status', true)
                ->first();

            if (!$task) {
                return response()->json([
                    'message' => 'Tarea no encontrada, no pertenece al usuario o ya está inactiva',
                ], 404);
            }

            // Invalidar la caché de la tarea específica y la lista de tareas
            Cache::tags(['task_' . $user->user_id])->flush();
            Cache::tags(['tasks_' . $user->user_id])->flush();

            // Realizar eliminación suave
            $task->delete();

            return response()->json([
                'message' => 'Tarea eliminada exitosamente',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la tarea',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
