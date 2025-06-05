<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Team;
use App\Models\Event;


class SetTeamRanks extends Command
{
    protected $signature = 'events:set-ranks';
    protected $description = 'Credit prizes to users for completed events';
    public function handle()
    {
        $liveEvents = Event::where('status', 'LIVE')
            ->where('is_winning_amount_transacted', false)
            // ->whereIn('id', [2])
            ->get();

            $this->info("set team rank cron job");
        foreach ($liveEvents as $event) {
            // Fetch all teams for the completed event
            $teams = Team::where('event_id', $event->id)->get();

            // Array to hold the teams and their points
            $teamPoints = [];

            foreach ($teams as $team) {
                // Assuming you have a points_scored attribute on the Team model
                $teamPoints[$team->id] = $team->points_scored; // Replace 'points_scored' with your actual attribute for points
            }

            // Sort teams by points in descending order
            arsort($teamPoints);
            $rank = 0; // Initialize rank
            $lastPoints = null; // To handle ties in points
            $rankedTeams = []; // To store team IDs with their ranks

            foreach ($teamPoints as $teamId => $points) {
                // Increment rank only if we have a new score
                if ($points !== $lastPoints) {
                    $rank++; // Increment rank for new unique score
                    $lastPoints = $points; // Update lastPoints to current
                }

                // Assign rank to the team
                $rankedTeams[$rank][] = $teamId; // Store multiple teams with the same rank
            }


            // Update rank in the Team table
            foreach ($rankedTeams as $rankValue => $teamIds) {
                foreach ($teamIds as $teamId) {
                    Team::where('id', $teamId)->update(['rank' => $rankValue]); // Update rank for each team
                    $this->info("Updated Team ID: {$teamId} with Rank: {$rankValue}");
                }
            }


            $this->info("Winning amounts have been transacted for Event ID: {$event->id}");
        }


    }
}
