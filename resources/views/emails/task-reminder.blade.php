<!DOCTYPE html>
<html>
<head>
    <title>Recordatorio de tarea</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .content {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Recordatorio de tarea</h2>
        </div>
        
        <div class="content">
            <p>Hola {{ $userName }},</p>
            
            <p>Te recordamos que tienes una tarea pendiente:</p>
            
            <h3>{{ $task->title }}</h3>
            
            @if($task->description)
                <p><strong>Descripción:</strong><br>
                {{ $task->description }}</p>
            @endif
            
            <p><strong>Fecha de vencimiento:</strong><br>
            {{ $dueDate }}</p>
            
            <p>Por favor, asegúrate de completar esta tarea antes de la fecha de vencimiento.</p>
        </div>
        
        <div class="footer">
            <p>Este es un mensaje automático, por favor no respondas a este correo.</p>
        </div>
    </div>
</body>
</html> 