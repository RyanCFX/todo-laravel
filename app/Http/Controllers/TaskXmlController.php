<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Services\XmlTaskService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;

/**
 * @OA\Tag(
 *     name="XML",
 *     description="API Endpoints para importar y exportar tareas en formato XML"
 * )
 */
class TaskXmlController extends Controller
{
    protected $xmlService;

    public function __construct(XmlTaskService $xmlService)
    {
        $this->xmlService = $xmlService;
    }

    /**
     * @OA\Post(
     *     path="/tasks-xml",
     *     summary="Importar tareas desde XML",
     *     description="Importa tareas desde un archivo XML",
     *     operationId="importTasksXml",
     *     tags={"XML"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="xml_file",
     *                     type="string",
     *                     format="binary",
     *                     description="Archivo XML con las tareas"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tareas importadas correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Tareas importadas correctamente"),
     *             @OA\Property(
     *                 property="tasks",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="task_id", type="string", format="uuid"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(property="due_date", type="string", format="date"),
     *                     @OA\Property(property="priority", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Formato XML inválido"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'xml_file' => 'required|file|mimes:xml'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $xmlContent = file_get_contents($request->file('xml_file')->path());

        if (!$this->xmlService->validateXml($xmlContent)) {
            return response()->json([
                'message' => 'El archivo XML no tiene el formato correcto'
            ], 400);
        }

        try {
            $tasks = $this->xmlService->xmlToTasks($xmlContent);
            $importedTasks = [];

            foreach ($tasks as $taskData) {
                $task = Task::create([
                    'task_id' => Uuid::uuid4()->toString(),
                    'user_id' => Auth::id(),
                    'title' => $taskData['title'],
                    'description' => $taskData['description'],
                    'status' => $taskData['status'],
                    'due_date' => $taskData['due_date'],
                    'priority' => $taskData['priority'],
                ]);

                $importedTasks[] = $task;
            }

            return response()->json([
                'message' => 'Tareas importadas correctamente',
                'tasks' => $importedTasks
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al importar las tareas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/tasks-xml",
     *     summary="Exportar tareas a XML",
     *     description="Exporta todas las tareas del usuario a un archivo XML",
     *     operationId="exportTasksXml",
     *     tags={"XML"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="XML con las tareas",
     *         @OA\MediaType(
     *             mediaType="application/xml",
     *             @OA\Schema(
     *                 type="string",
     *                 example="<?xml version='1.0' encoding='UTF-8'?><tasks><task><title>Ejemplo tarea</title><description>Descripción de ejemplo</description><status>ACTIVE</status><due_date>2024-03-25</due_date><priority>HIGH</priority></task></tasks>"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error del servidor"
     *     )
     * )
     */
    public function export()
    {
        $tasks = Task::where('user_id', Auth::id())->get();
        $xml = $this->xmlService->tasksToXml($tasks);

        return response($xml, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Content-Disposition', 'attachment; filename="tasks.xml"');
    }
} 