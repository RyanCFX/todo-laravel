<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/auth/signup",
     *     summary="Registrar un nuevo usuario",
     *     description="Crea una nueva cuenta de usuario",
     *     operationId="signup",
     *     tags={"Autenticación"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="test01@test.com"),
     *             @OA\Property(property="password", type="string", format="password", example="12345")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Usuario creado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="user_id", type="string", format="uuid"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function signup(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'user_id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password)
        ]);

        return response()->json(['user' => $user], 201);
    }

    /**
     * @OA\Post(
     *     path="/auth/signin",
     *     summary="Iniciar sesión",
     *     description="Inicia sesión con email y contraseña",
     *     operationId="signin",
     *     tags={"Autenticación"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="test@test.com"),
     *             @OA\Property(property="password", type="string", format="password", example="12345")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login exitoso",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="user_id", type="string", format="uuid"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string", format="email")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Credenciales inválidas"
     *     )
     * )
     */
    public function signin(Request $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        // Guardar datos en cookie
        $userData = [
            'id' => $user->user_id,
            'name' => $user->name,
            'email' => $user->email,
        ];
        $cookie = Cookie::make(
            'user_data',
            json_encode($userData),
            1440,  // 1 día
            null,
            null,
            false,  // Cambia a true si usas HTTPS
            true  // HttpOnly
        );

        // Iniciar sesión para que el middleware 'auth' funcione
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Login successful',
            'user' => $user
        ], 200)->withCookie($cookie);
    }

    /**
     * @OA\Post(
     *     path="/auth/signout",
     *     summary="Cerrar sesión",
     *     description="Cierra la sesión del usuario actual",
     *     operationId="signout",
     *     tags={"Autenticación"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Sesión cerrada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     )
     * )
     */
    public function signout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $cookie = Cookie::forget('user_data');

        return response()->json(['message' => 'Logged out successfully'], 200)->withCookie($cookie);
    }
}
