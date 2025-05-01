<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Models\Task;
use App\Mail\TaskReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ProcessNotifications extends Command
{
    protected $signature = 'notifications:process';
    protected $description = 'Procesa las notificaciones pendientes y las envía según el tipo de notificación del usuario';

    public function handle()
    {
        Log::info('Iniciando proceso de notificaciones');
        $this->info('Iniciando proceso de notificaciones...');

        try {
            // Obtener todas las notificaciones pendientes
            $notifications = Notification::with(['user', 'task'])
                ->where('status', true)
                ->whereNull('sent_at')
                ->where('scheduled_at', '<=', now())
                ->get();

            Log::info("Notificaciones pendientes encontradas: {$notifications->count()}");
            $this->info("Procesando {$notifications->count()} notificaciones pendientes...");

            if ($notifications->isEmpty()) {
                Log::info('No hay notificaciones pendientes para procesar');
                $this->info('No hay notificaciones pendientes para procesar');
                return;
            }

            foreach ($notifications as $notification) {
                try {
                    Log::info("Procesando notificación ID: {$notification->notification_id}");
                    $this->info("Procesando notificación ID: {$notification->notification_id}");

                    $user = $notification->user;
                    $task = $notification->task;

                    if (!$user || !$task) {
                        $errorMsg = "Notificación {$notification->notification_id} tiene usuario o tarea inválida";
                        Log::error($errorMsg, [
                            'notification_id' => $notification->notification_id,
                            'user' => $user ? $user->toArray() : null,
                            'task' => $task ? $task->toArray() : null
                        ]);
                        $this->error($errorMsg);
                        continue;
                    }

                    Log::info("Enviando notificación a usuario: {$user->email}, tipo: {$user->notification_type}");
                    $this->info("Enviando notificación a usuario: {$user->email}, tipo: {$user->notification_type}");

                    // Enviar notificación según el tipo
                    if ($user->notification_type === 'email') {
                        $this->sendEmailNotification($user, $task);
                    } else {
                        $this->sendPushNotification($user, $task);
                    }

                    // Marcar como enviada
                    $notification->markAsSent();
                    Log::info("Notificación {$notification->notification_id} enviada exitosamente");
                    $this->info("Notificación {$notification->notification_id} enviada exitosamente");

                } catch (\Exception $e) {
                    $errorMsg = "Error al procesar notificación {$notification->notification_id}: {$e->getMessage()}";
                    Log::error($errorMsg, [
                        'error' => $e->getMessage(),
                        'notification' => $notification->toArray(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->error($errorMsg);
                }
            }

            Log::info('Proceso de notificaciones completado');
            $this->info('Proceso de notificaciones completado');

        } catch (\Exception $e) {
            $errorMsg = "Error general: {$e->getMessage()}";
            Log::error($errorMsg, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error($errorMsg);
        }
    }

    private function sendEmailNotification(User $user, Task $task)
    {
        Log::info("Enviando email a {$user->email} para la tarea {$task->task_id}");
        Mail::to($user->email)->send(new TaskReminder($task));
        Log::info("Email enviado exitosamente a {$user->email}");
    }

    private function sendPushNotification(User $user, Task $task)
    {
        Log::info("Enviando push notification a usuario {$user->user_id} para la tarea {$task->task_id}");
        if (!isset($user->push_token)) {
            Log::info("El usuario {$user->user_id} no tiene un token de push");
            return;
        }
        $payload = [
            'to' => $user->push_token,
            'title' => 'Recordatorio de tarea: ' . $task->title,
            'body' => 'Tienes una tarea pendiente: ' . $task->title,
            'sound' => 'default',
            'priority' => 'high',
        ];
        $response = Http::post(env('PUSH_HOST'), $payload);

        if ($response->successful()) {
            Log::info("Push notification enviada exitosamente");
        } else {
            Log::error("Error al enviar push notification: {$response->body()}");
        }
    }
}