<?php

namespace App\Mail;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class TaskReminder extends Mailable
{
    use Queueable, SerializesModels;

    public $task;
    public $dueDate;
    public $userName;

    public function __construct(Task $task)
    {
        $this->task = $task;
        $this->dueDate = Carbon::parse($task->due_date)->format('d/m/Y H:i');
        $this->userName = $task->user->name;
    }

    public function build()
    {
        return $this->view('emails.task-reminder')
                    ->subject('Recordatorio de tarea: ' . $this->task->title)
                    ->with([
                        'task' => $this->task,
                        'dueDate' => $this->dueDate,
                        'userName' => $this->userName
                    ]);
    }
} 