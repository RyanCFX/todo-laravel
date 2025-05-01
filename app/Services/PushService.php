<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushService
{
    protected $apiUrl = 'https://exp.host/--/api/v2/push/send';

    public function send($token, $title, $body, $data = [])
    {
        try {
            $response = Http::post($this->apiUrl, [
                'to' => $token,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'sound' => 'default',
                'badge' => 1,
                'priority' => 'high'
            ]);

            if ($response->successful()) {
                Log::info('Push notification enviada exitosamente', [
                    'token' => $token,
                    'response' => $response->json()
                ]);
                return true;
            }

            Log::error('Error al enviar push notification', [
                'token' => $token,
                'response' => $response->json()
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('Error al enviar push notification', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
} 