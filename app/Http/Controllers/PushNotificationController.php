<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PushNotificationController extends Controller
{
    public function registerToken(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'push_token' => 'required|string'
            ]);

            $user = Auth::user();
            
            // Actualizar el token del usuario
            $user->update([
                'push_token' => $validated['push_token']
            ]);

            Log::info("Token de push notification registrado para usuario {$user->user_id}");

            return response()->json([
                'message' => 'Token registrado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error("Error al registrar token de push notification", [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al registrar el token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function removeToken(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Eliminar el token del usuario
            $user->update([
                'push_token' => null
            ]);

            Log::info("Token de push notification eliminado para usuario {$user->user_id}");

            return response()->json([
                'message' => 'Token eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error("Error al eliminar token de push notification", [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al eliminar el token',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 