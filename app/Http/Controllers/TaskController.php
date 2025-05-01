<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Task;
use App\Models\TaskHistory;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use SimpleXMLElement;

class TaskController extends Controller
{
    /**
     * @OA\Get(
     *     path="/tasks",
     *     tags={"Tareas"},
     *     summary="Listar tareas",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de tareas obtenida exitosamente"
     *     )
     * )
     */
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
                $query = Task::select(
                    'tasks.task_id',
                    'tasks.title',
                    'tasks.description',
                    'tasks.due_date',
                    'tasks.status',
                    'tasks.status_code',
                    'status.description as status_description',
                    'status.color as status_color'
                )
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

                $formattedTasks = collect($tasks->items())->map(function ($task) {
                    return [
                        'task_id' => $task->task_id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'due_date' => $task->due_date,
                        'status' => [
                            'code' => $task->status_code,
                            'description' => $task->status_description,
                            'color' => $task->status_color,
                        ],
                    ];
                });

                return response()->json([
                    'data' => $formattedTasks,
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

    /**
     * @OA\Get(
     *     path="/tasks/{task_id}",
     *     tags={"Tareas"},
     *     summary="Obtener tarea",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="task_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tarea obtenida exitosamente"
     *     )
     * )
     */
    public function getTaskById(string $task_id): JsonResponse
    {
        try {
            $user = Auth::user();
            $cacheKey = 'task_' . $task_id . '_' . $user->user_id;

            // Usar tags para la caché de tareas individuales
            $task = Cache::tags(['task_' . $user->user_id])->remember($cacheKey, now()->addMinutes(10), function () use ($user, $task_id) {
                $task = Task::select(
                    'tasks.*',
                    'status.description as status_description',
                    'status.color as status_color'
                )
                    ->join('status', 'tasks.status_code', '=', 'status.status_code')
                    ->where('tasks.user_id', $user->user_id)
                    ->whereNull('tasks.deleted_at')
                    ->where('tasks.status', true)
                    ->where('tasks.task_id', $task_id)
                    ->first();

                if ($task) {
                    // Obtener los archivos adjuntos
                    $attachments = $task
                        ->attachments()
                        ->select([
                            'attachment_id',
                            'file_name',
                            'file_path',
                            'file_type',
                            'file_size',
                            'created_at'
                        ])
                        ->get()
                        ->map(function ($attachment) {
                            return [
                                'attachment_id' => $attachment->attachment_id,
                                'file_name' => $attachment->file_name,
                                'file_path' => url('storage/' . $attachment->file_path),
                                'file_type' => $attachment->file_type,
                                'file_size' => $attachment->file_size,
                                'created_at' => $attachment->created_at
                            ];
                        });

                    $task->attachments = $attachments;
                }

                return $task;
            });

            if (!$task) {
                return response()->json([
                    'message' => 'Tarea no encontrada',
                ], 404);
            }

            $formattedTask = [
                'task_id' => $task->task_id,
                'user_id' => $task->user_id,
                'title' => $task->title,
                'description' => $task->description,
                'due_date' => $task->due_date,
                'reminder_offset_minutes' => $task->reminder_offset_minutes,
                'previous_task_id' => $task->previous_task_id,
                'created_at' => $task->created_at,
                'updated_at' => $task->updated_at,
                'file_path' => $task->file_path,
                'status' => [
                    'code' => $task->status_code,
                    'description' => $task->status_description,
                    'color' => $task->status_color
                ],
                'attachments' => $task->attachments
            ];

            return response()->json($formattedTask);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al recuperar la tarea',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function createNotification(Task $task): void
    {
            if ($task->reminder_offset_minutes) {
            $scheduledAt = Carbon::parse($task->due_date)
                ->subMinutes($task->reminder_offset_minutes);

            Notification::create([
                'task_id' => $task->task_id,
                'user_id' => $task->user_id,
                'scheduled_at' => $scheduledAt,
                'status' => true
            ]);
        }
    }

    private function deactivateTaskNotifications(string $taskId): void
    {
        Notification::where('task_id', $taskId)
            ->where('status', true)
            ->update(['status' => false]);
    }

    /**
     * @OA\Post(
     *     path="/tasks",
     *     tags={"Tareas"},
     *     summary="Crear tarea",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "due_date"},
     *             @OA\Property(property="title", type="string", example="Titulo de tarea"),
     *             @OA\Property(property="description", type="string", example="Descripción de tarea"),
     *             @OA\Property(property="due_date", type="string", format="date", example="2024-03-25"),
     *             @OA\Property(property="reminder_offset_minutes", type="integer", enum={5,10,15,20,30,60,1440}, example=30),
     *             @OA\Property(property="attachments", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="file", type="string", description="Archivo en base64"),
     *                     @OA\Property(property="name", type="string", example="documento.pdf"),
     *                     @OA\Property(property="type", type="string", enum={"pdf","jpg","jpeg","png"}, example="pdf")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tarea creada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="task_id", type="string", format="uuid"),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="due_date", type="string", format="date"),
     *             @OA\Property(property="status_code", type="string"),
     *             @OA\Property(property="created_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'due_date' => 'required|date',
                'reminder_offset_minutes' => 'nullable|integer|in:5,10,15,20,30,60,1440',
                'attachments' => 'nullable|array',
                'attachments.*.file' => 'required|string',  // Base64
                'attachments.*.name' => 'required|string',
                'attachments.*.type' => 'required|string|in:pdf,jpg,jpeg,png',
            ]);

            // Validar número máximo de archivos
            if (isset($validated['attachments']) && count($validated['attachments']) > env('MAX_ATTACHMENTS', 5)) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => ['attachments' => ['No se pueden subir más de ' . env('MAX_ATTACHMENTS', 5) . ' archivos']],
                ], 422);
            }

            $user = Auth::user();

            // Invalidar la caché de la lista de tareas
            Cache::tags(['tasks_' . $user->user_id])->flush();

            $taskId = Str::uuid();

            // Crear la tarea
            $task = new Task();
            $task->task_id = $taskId;
            $task->user_id = $user->user_id;
            $task->title = $validated['title'];
            $task->description = $validated['description'] ?? null;
            $task->due_date = $validated['due_date'];
            $task->reminder_offset_minutes = $validated['reminder_offset_minutes'] ?? null;
            $task->status = true;
            $task->status_code = 'ACTIVE';
            $task->created_at = now();
            $task->save();

            // Crear notificación si hay reminder_offset_minutes
            $this->createNotification($task);

            // Procesar archivos adjuntos
            if (isset($validated['attachments'])) {
                foreach ($validated['attachments'] as $attachment) {
                    // Decodificar el archivo base64
                    $fileData = base64_decode(preg_replace('#^data:.*?;base64,#', '', $attachment['file']));

                    // Validar tamaño del archivo
                    $fileSize = strlen($fileData);
                    if ($fileSize > env('MAX_ATTACHMENT_SIZE', 10240) * 1024) {
                        continue;  // Saltar archivos que excedan el tamaño máximo
                    }

                    // Generar nombre único para el archivo
                    $extension = $attachment['type'];
                    $fileName = $attachment['name'];
                    $uniqueName = Str::uuid() . '_' . $fileName;

                    // Guardar el archivo
                    $filePath = env('ATTACHMENTS_PATH', 'attachments') . '/' . $uniqueName;
                    Storage::disk('public')->put($filePath, $fileData);

                    // Crear registro en la tabla attachments usando el modelo
                    Attachment::create([
                        'attachment_id' => Str::uuid(),
                        'task_id' => $taskId,
                        'file_name' => $fileName,
                        'file_path' => $filePath,
                        'file_type' => $attachment['type'],
                        'file_size' => $fileSize,
                        'created_at' => now(),
                    ]);
                }
            }

            // Recargar la tarea con sus archivos adjuntos
            $task = $task->fresh(['attachments']);

            return response()->json([
                'task_id' => $task->task_id,
                'user_id' => $task->user_id,
                'title' => $task->title,
                'description' => $task->description,
                'due_date' => $task->due_date,
                'reminder_offset_minutes' => $task->reminder_offset_minutes,
                'status_code' => $task->status_code,
                'created_at' => $task->created_at,
                'attachments' => $task->attachments->map(function ($attachment) {
                    return [
                        'attachment_id' => $attachment->attachment_id,
                        'file_name' => $attachment->file_name,
                        'file_path' => url('storage/' . $attachment->file_path),
                        'file_type' => $attachment->file_type,
                        'file_size' => $attachment->file_size,
                        'created_at' => $attachment->created_at
                    ];
                })
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la tarea',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/tasks/{task_id}",
     *     tags={"Tareas"},
     *     summary="Actualizar tarea",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="task_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Titulo de tarea actualizado"),
     *             @OA\Property(property="description", type="string", example="Nueva descripción del informe"),
     *             @OA\Property(property="due_date", type="string", format="date", example="2024-03-26"),
     *             @OA\Property(property="reminder_offset_minutes", type="integer", enum={5,10,15,20,30,60,1440}, example=60),
     *             @OA\Property(property="status", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tarea actualizada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="task_id", type="string", format="uuid"),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="due_date", type="string", format="date"),
     *             @OA\Property(property="status_code", type="string"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tarea no encontrada"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
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

            // Si se actualiza el reminder_offset_minutes, desactivar notificaciones existentes
            if (isset($validated['reminder_offset_minutes']) && $validated['reminder_offset_minutes'] !== $task->reminder_offset_minutes) {
                $this->deactivateTaskNotifications($task_id);
            }

            $task->update(array_filter($validated, fn($value) => !is_null($value)));

            // Si se actualizó el reminder_offset_minutes, crear nueva notificación
            if (isset($validated['reminder_offset_minutes'])) {
                $this->createNotification($task);
            }

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

    /**
     * @OA\Get(
     *     path="/tasks/{task_id}/history",
     *     summary="Historial de tarea",
     *     description="Obtiene el historial de cambios de estado de una tarea",
     *     operationId="taskHistory",
     *     tags={"Tareas"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="task_id",
     *         in="path",
     *         description="ID de la tarea",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Historial de la tarea",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="task_id", type="string", format="uuid"),
     *                 @OA\Property(property="old_status", type="string"),
     *                 @OA\Property(property="new_status", type="string"),
     *                 @OA\Property(property="changed_by", type="string", format="uuid"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/tasks/{task_id}",
     *     tags={"Tareas"},
     *     summary="Eliminar tarea",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="task_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tarea eliminada exitosamente"
     *     )
     * )
     */
    public function remove(string $task_id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Buscar la tarea
            $task = Task::where('task_id', $task_id)
                ->where('user_id', $user->user_id)
                ->whereNull('deleted_at')
                ->first();

            if (!$task) {
                return response()->json([
                    'message' => 'Tarea no encontrada, no pertenece al usuario o ya está inactiva'
                ], 404);
            }

            // Desactivar notificaciones de la tarea
            $this->deactivateTaskNotifications($task_id);

            // Realizar eliminación suave
            $task->delete();

            return response()->json([
                'message' => 'Tarea eliminada exitosamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la tarea',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
