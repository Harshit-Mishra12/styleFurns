<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\UpdateEventStatus;
use App\Console\Commands\FetchMatchPlayerPoints;
use App\Console\Commands\UpdateMatchSquad;
use App\Console\Commands\UpdateTeamPoints;
use App\Console\Commands\CreateRandomTeam;
use App\Console\Commands\UpdateTeamPointsTest;
use App\Console\Commands\SetTeamRanksAndCreditWinnings;




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
        SetTeamRanksAndCreditWinnings::class

    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        // Schedule the command to run periodically
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
