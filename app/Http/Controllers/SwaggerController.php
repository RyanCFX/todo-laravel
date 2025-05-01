<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="API de Tareas",
 *     description="API para gestionar tareas y backups"
 * )
 *
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Tag(
 *     name="Tareas",
 *     description="Endpoints para gestión de tareas"
 * )
 * 
 * @OA\Tag(
 *     name="Backups",
 *     description="Endpoints para gestión de backups"
 * )
 */
class SwaggerController extends Controller
{
} 