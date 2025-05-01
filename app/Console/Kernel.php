<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\ProcessNotifications::class,
        Commands\TestEmailNotification::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('notifications:process')
            ->everyMinute()
            ->before(function () {
                Log::info('Iniciando scheduler de notificaciones');
            })
            ->after(function () {
                Log::info('Scheduler de notificaciones completado');
            })
            ->onFailure(function () {
                Log::error('Error en el scheduler de notificaciones');
            });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
