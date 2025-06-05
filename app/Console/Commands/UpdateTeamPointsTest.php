<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\MatchPlayer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UpdateTeamPointsTest extends Command
{
    // Define the command signature and description
    protected $signature = 'update:team-points-test';
    protected $description = 'Fetch and update points scored for teams in events based on match player points.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {   Log::info('CreateRandomTeam job started.');
        // Step 1: Fetch all events whose go_live_date is today, yesterday, or tomorrow
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $tomorrow = Carbon::tomorrow();

        $events = Event::whereBetween('go_live_date', [$yesterday, $tomorrow])->get();

        if ($events->isEmpty()) {
            $this->info('No events found for today, yesterday, or tomorrow.');
            return;
        }

        // Step 2: Traverse through each event
        foreach ($events as $event) {
            $this->info("Processing Event ID: {$event->id}");

            // Fetch all teams associated with the event
            $teams = Team::where('event_id', $event->id)->get();

            if ($teams->isEmpty()) {
                $this->info("No teams found for Event ID: {$event->id}");
                continue;
            }

            // Step 3: Traverse through each team
            foreach ($teams as $team) {
                $this->info("Processing Team ID: {$team->id}");

                // Fetch all players for the team from the team_players table
                $teamPlayers = TeamPlayer::where('team_id', $team->id)->get();

                if ($teamPlayers->isEmpty()) {
                    $this->info("No players found for Team ID: {$team->id}");
                    continue;
                }

                $totalPoints = 0;

                // Step 4: Calculate the total points scored for the team based on match players
                foreach ($teamPlayers as $teamPlayer) {
                    $matchPlayer = MatchPlayer::where('id', $teamPlayer->match_player_id)
                                              ->first();

                    if ($matchPlayer) {
                        $this->info("Adding points for Player ID: {$matchPlayer->id}");
                        $totalPoints += $matchPlayer->points ?? 0; // Add points, default to 0 if null
                    } else {
                        $this->info("MatchPlayer not found for TeamPlayer ID: {$teamPlayer->id}");
                    }
                }

                // Step 5: Update the total points scored in the team table
                $team->points_scored = $totalPoints;
                $team->save();

                $this->info("Updated Team ID: {$team->id} with Total Points: {$totalPoints}");
            }
        }

        $this->info('Team points updating completed.');
    }
}
