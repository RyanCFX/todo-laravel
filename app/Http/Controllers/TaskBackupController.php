<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskBackup;
use App\Models\RestoreHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Ramsey\Uuid\Uuid;

/**
 * @OA\Tag(
 *     name="Backups",
 *     description="API Endpoints para la gestión de backups de tareas"
 * )
 */
class TaskBackupController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/backups",
     *     tags={"Backups"},
     *     summary="Crear backup de tareas",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=201,
     *         description="Backup creado exitosamente"
     *     )
     * )
     */
    public function createBackup(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Obtener todas las tareas del usuario
            $tasks = Task::where('user_id', $user->user_id)
                ->with(['attachments'])
                ->get();

            // Crear estructura XML
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><tasks></tasks>');
            
            foreach ($tasks as $task) {
                $taskNode = $xml->addChild('task');
                $taskNode->addChild('task_id', $task->task_id);
                $taskNode->addChild('title', $task->title);
                $taskNode->addChild('description', $task->description);
                $taskNode->addChild('status', $task->status ? 'true' : 'false');
                $taskNode->addChild('status_code', $task->status_code);
                $taskNode->addChild('due_date', $task->due_date);
                $taskNode->addChild('reminder_offset_minutes', $task->reminder_offset_minutes);
                $taskNode->addChild('created_at', $task->created_at);
                $taskNode->addChild('deleted_at', $task->deleted_at);

                // Agregar archivos adjuntos
                if ($task->attachments->count() > 0) {
                    $attachmentsNode = $taskNode->addChild('attachments');
                    foreach ($task->attachments as $attachment) {
                        $attachmentNode = $attachmentsNode->addChild('attachment');
                        $attachmentNode->addChild('attachment_id', $attachment->attachment_id);
                        $attachmentNode->addChild('file_name', $attachment->file_name);
                        $attachmentNode->addChild('file_path', $attachment->file_path);
                        $attachmentNode->addChild('file_type', $attachment->file_type);
                        $attachmentNode->addChild('file_size', $attachment->file_size);
                    }
                }
            }

            // Generar nombre único para el archivo
            $fileName = 'backup_' . Str::uuid() . '.xml';
            $filePath = 'backups/' . $fileName;

            // Guardar el archivo XML
            Storage::disk('public')->put($filePath, $xml->asXML());

            // Crear registro en la base de datos
            $backup = TaskBackup::create([
                'user_id' => $user->user_id,
                'file_path' => $filePath,
                'created_at' => now()
            ]);

            return response()->json([
                'message' => 'Backup creado exitosamente',
                'backup' => $backup
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el backup',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/backups",
     *     tags={"Backups"},
     *     summary="Listar backups",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de backups"
     *     )
     * )
     */
    public function getBackups(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $backups = TaskBackup::where('user_id', $user->user_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($backups);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los backups',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/backups/{backup_id}/restore",
     *     tags={"Backups"},
     *     summary="Restaurar backup",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="backup_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="force", type="boolean", example=false, description="Forzar restauración incluso si el backup está vacío")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Backup restaurado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Backup restaurado exitosamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="El backup no contiene tareas"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Backup no encontrado"
     *     )
     * )
     */
    public function restoreBackup(string $backup_id, Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verificar que el backup existe y pertenece al usuario
            $backup = TaskBackup::where('backup_id', $backup_id)
                ->where('user_id', $user->user_id)
                ->first();

            if (!$backup) {
                return response()->json([
                    'message' => 'Backup no encontrado'
                ], 404);
            }

            // Leer el archivo XML
            $xmlContent = Storage::disk('public')->get($backup->file_path);
            $xml = new SimpleXMLElement($xmlContent);

            if ((!isset($xml->task) || count($xml->task) === 0) && !$request->has('force')) {
                return response()->json([
                    'message' => 'El backup no contiene tareas, estas seguro de querer restaurar (se eliminaran todas las tareas actuales)?'
                ], 400);
            }
            
            // Obtener todas las tareas actuales del usuario
            $currentTasks = Task::where('user_id', $user->user_id)->get();
            $currentTaskIds = $currentTasks->pluck('task_id')->toArray();

            // Obtener todas las tareas del backup
            $backupTaskIds = [];
            foreach ($xml->task as $task) {
                $backupTaskIds[] = (string)$task->task_id;
            }

            // Tareas que están en el backup pero no en la base de datos actual
            $tasksToRestore = array_diff($backupTaskIds, $currentTaskIds);
            
            // Tareas que están en la base de datos actual pero no en el backup
            $tasksToDelete = array_diff($currentTaskIds, $backupTaskIds);
            // Restaurar tareas del backup
            foreach ($xml->task as $task) {
                $taskId = (string)$task->task_id;
                
                if (in_array($taskId, $tasksToRestore)) {
                    // Crear nueva tarea
                    Task::create([
                        'task_id' => $taskId,
                        'user_id' => $user->user_id,
                        'title' => (string)$task->title,
                        'description' => (string)$task->description,
                        'status' => (string)$task->status === 'true',
                        'status_code' => (string)$task->status_code,
                        'due_date' => (string)$task->due_date,
                        'reminder_offset_minutes' => (int)$task->reminder_offset_minutes,
                        'created_at' => (string)$task->created_at,
                        'deleted_at' => (string)$task->deleted_at
                    ]);
                } else {
                    // Actualizar tarea existente
                    $existingTask = Task::where('task_id', $taskId)->first();
                    if ($existingTask) {
                        $existingTask->update([
                            'title' => (string)$task->title,
                            'description' => (string)$task->description,
                            'status' => (string)$task->status === 'true',
                            'status_code' => (string)$task->status_code,
                            'due_date' => (string)$task->due_date,
                            'reminder_offset_minutes' => (int)$task->reminder_offset_minutes,
                            'deleted_at' => (string)$task->deleted_at
                        ]);
                    }
                }
            }

            // Eliminar tareas que no están en el backup
            foreach ($tasksToDelete as $taskId) {
                $task = Task::where('task_id', $taskId)->first();
                if ($task) {
                    $task->delete();
                }
            }

            // Registrar la restauración
            RestoreHistory::create([
                'restore_id' => Str::uuid(),
                'user_id' => $user->user_id,
                'backup_id' => $backup_id,
                'restored_at' => now()
            ]);

            Cache::tags(['task_' . Auth::id()])->flush();
            Cache::tags(['tasks_' . Auth::id()])->flush();

            return response()->json([
                'message' => 'Backup restaurado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al restaurar el backup',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/backups/{backup_id}",
     *     tags={"Backups"},
     *     summary="Eliminar backup",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="backup_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Backup eliminado exitosamente"
     *     )
     * )
     */
    public function deleteBackup(string $backup_id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verificar que el backup existe y pertenece al usuario
            $backup = TaskBackup::where('backup_id', $backup_id)
                ->where('user_id', $user->user_id)
                ->first();

            if (!$backup) {
                return response()->json([
                    'message' => 'Backup no encontrado'
                ], 404);
            }

            // Crear directorio de backups eliminados si no existe
            $deletedBackupsPath = 'deleted_backups';
            if (!Storage::disk('public')->exists($deletedBackupsPath)) {
                Storage::disk('public')->makeDirectory($deletedBackupsPath);
            }

            // Mover el archivo XML a la carpeta de backups eliminados
            $originalPath = $backup->file_path;
            $fileName = basename($originalPath);
            $newPath = $deletedBackupsPath . '/' . $fileName;
            
            if (Storage::disk('public')->exists($originalPath)) {
                Storage::disk('public')->move($originalPath, $newPath);
            }

            // Eliminar el backup (soft delete)
            $backup->delete();

            return response()->json([
                'message' => 'Backup eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el backup',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 