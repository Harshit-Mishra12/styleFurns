<?php

namespace App\Console;

use App\Console\Commands\AutoRescheduleMissedBookings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\UpdateEventStatus;
use App\Console\Commands\FetchMatchPlayerPoints;
use App\Console\Commands\UpdateMatchSquad;
use App\Console\Commands\UpdateTeamPoints;
use App\Console\Commands\CreateRandomTeam;
use App\Console\Commands\UpdateTeamPointsTest;
use App\Console\Commands\SetTeamRanksAndCreditWinnings;
use App\Console\Commands\SetTechniciansOffline;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // Register your custom command here
        UpdateEventStatus::class,
        FetchMatchPlayerPoints::class,
        UpdateMatchSquad::class,
        UpdateTeamPoints::class,
        CreateRandomTeam::class,
        UpdateTeamPointsTest::class,
        SetTeamRanksAndCreditWinnings::class,
        SetTechniciansOffline::class,
        AutoRescheduleMissedBookings::class

    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        // Schedule the command to run periodically

        $schedule->command('technicians:set-offline')->dailyAt('22:00');
        $schedule->command('bookings:auto-reschedule')->everyThreeMinutes();

        $schedule->command('notifications:shift-start')->dailyAt('07:55');
        $schedule->command('notifications:shift-start')->dailyAt('08:00');
        $schedule->command('notifications:shift-start')->dailyAt('08:05');

        // Shift end reminders (9:55 PM, 10:00 PM, 10:05 PM)
        $schedule->command('notifications:shift-end')->dailyAt('21:55');
        $schedule->command('notifications:shift-end')->dailyAt('22:00');
        $schedule->command('notifications:shift-end')->dailyAt('22:05');

        $schedule->command('notifications:location-update')->everyFifteenMinutes();
        // $schedule->command('create:random-team')->everyThreeMinutes();
        // $schedule->command('events:update-status')->everyThreeMinutes(); // Runs every hour
        // $schedule->command('fetch:match-player-points')->everyThreeMinutes();
        // $schedule->command('update:team-points')->everyThreeMinutes();
        // $schedule->command('update:match-squad')->everyFiveMinutes();
        // $schedule->command('events:set-ranks-credit-winnings')->everyFiveMinutes();
        // $schedule->command('events:set-ranks')->everyThreeMinutes();
        // $schedule->command('update:team-points-test')->everyThreeMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
