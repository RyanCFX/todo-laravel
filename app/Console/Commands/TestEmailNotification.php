<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Mail\TaskReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmailNotification extends Command
{
    protected $signature = 'test:email {email}';
    protected $description = 'Prueba el envío de emails de notificación';

    public function handle()
    {
        try {
            $email = $this->argument('email');

            // Crear un usuario de prueba
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                $this->error("No se encontró un usuario con el email: {$email}");
                return;
            }

            // Crear una tarea de prueba
            $task = Task::where('user_id', $user->user_id)
                ->whereNull('deleted_at')
                ->first();

            if (!$task) {
                $this->error("No se encontró una tarea para el usuario");
                return;
            }

            // Enviar el email
            Mail::to($email)->send(new TaskReminder($task));

            $this->info("Email de prueba enviado exitosamente a: {$email}");

        } catch (\Exception $e) {
            $this->error("Error al enviar el email: {$e->getMessage()}");
        }
    }
} 