<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class AuthController extends Controller
{
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
            'password' => bcrypt($request->password),
            'role_id' => $request->role_id ?? \Ramsey\Uuid\Uuid::uuid4()->toString(),
        ]);

        return response()->json(['user' => $user], 201);
    }

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

    public function signout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $cookie = Cookie::forget('user_data');

        return response()->json(['message' => 'Logged out successfully'], 200)->withCookie($cookie);
    }
}
