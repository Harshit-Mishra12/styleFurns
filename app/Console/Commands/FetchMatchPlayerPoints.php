<?php

namespace App\Console\Commands;

use App\Helpers\Helper;
use Illuminate\Console\Command;
use App\Models\Event;
use App\Models\EventMatch;
use App\Models\MatchPlayer;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class FetchMatchPlayerPoints extends Command
{
    // Define the command signature and description
    protected $signature = 'fetch:match-player-points';
    protected $description = 'Fetch player points for matches of live events and update MatchPlayer table';

    public function __construct()
    {
        parent::__construct();
    }

    // public function handle()
    // {
    //     // Step 1: Fetch all live events
    //     // $liveEvents = Event::where('status', 'LIVE')->get();
    //     $today = Carbon::today();
    //     $yesterday = Carbon::yesterday();
    //     $tomorrow = Carbon::tomorrow();

    //     $liveEvents = Event::where(function ($query) use ($today, $yesterday, $tomorrow) {
    //             $query->whereDate('go_live_date', $today)
    //                 ->orWhereDate('go_live_date', $yesterday)
    //                 ->orWhereDate('go_live_date', $tomorrow);
    //         })
    //         ->get();

    //     if ($liveEvents->isEmpty()) {
    //         $this->info('No live events found.');
    //         return;
    //     }

    //     // Step 2: Loop through each event
    //     foreach ($liveEvents as $event) {
    //         // Get matches related to the event
    //         $eventMatches = EventMatch::where('event_id', $event->id)->get();

    //         if ($eventMatches->isEmpty()) {
    //             $this->info("No matches found for event ID: {$event->id}");
    //             continue;
    //         }

    //         // Step 3: Fetch match points for each match
    //         foreach ($eventMatches as $eventMatch) {
    //             $match = $eventMatch->match; // Assuming a relationship 'match' exists on the EventMatch model
    //             if (!$match) {
    //                 $this->info("No match data found for EventMatch ID: {$eventMatch->id}");
    //                 continue;
    //             }

    //             $externalMatchId = $match->external_match_id;
    //             $apiKey = Helper::getApiKey(); // Assuming the API key is stored in services config

    //             $response = Http::get("https://api.cricapi.com/v1/match_points", [
    //                 'apikey' => $apiKey,
    //                 'id' => $externalMatchId,
    //                 'ruleset' => 101323 // You can change the ruleset if required
    //             ]);

    //             if ($response->successful()) {
    //                 $matchPointsData = $response->json();
    //                 // Check if 'totals' key exists and is not empty
    //                 if (!isset($matchPointsData['data']['totals']) || empty($matchPointsData['data']['totals'])) {
    //                     $this->info("No totals data found for match ID: {$externalMatchId}");
    //                     continue; // Skip to the next match
    //                 }
    //                 // Step 4: Process and store match points for each player
    //                 foreach ($matchPointsData['data']['totals'] as $playerData) {
    //                     $externalPlayerId = $playerData['id'];
    //                     $playerPoints = $playerData['points']; // Assuming points are stored under 'points'

    //                     // Update MatchPlayer with points
    //                     $matchPlayer = MatchPlayer::where('event_id', $event->id)
    //                         ->where('external_player_id', $externalPlayerId)
    //                         ->first();

    //                     if ($matchPlayer) {
    //                         $matchPlayer->points = $playerPoints; // Adjust based on your database schema
    //                         $matchPlayer->save();

    //                         $this->info("Player {$matchPlayer->name} points updated to {$playerPoints} for Match ID: {$match->id}");
    //                     } else {
    //                         $this->info("MatchPlayer not found for player ID: {$externalPlayerId}");
    //                     }
    //                 }
    //             } else {
    //                 $this->error("Failed to fetch points for match ID: {$externalMatchId}");
    //             }
    //         }
    //     }

    //     $this->info('Player points fetching completed.');
    // }

    public function handle()
    {
        // Step 1: Fetch all live events
        // $liveEvents = Event::where('status', 'LIVE')->get();
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $tomorrow = Carbon::tomorrow();

        $liveEvents = Event::where(function ($query) use ($today, $yesterday, $tomorrow) {
            $query->whereDate('go_live_date', $today)
                ->orWhereDate('go_live_date', $yesterday)
                ->orWhereDate('go_live_date', $tomorrow);
        })
            ->get();

        if ($liveEvents->isEmpty()) {
            $this->info('No live events found.');
            return;
        }

        // Step 2: Loop through each event
        foreach ($liveEvents as $event) {
            // Get matches related to the event
            $eventMatches = EventMatch::where('event_id', $event->id)->get();

            if ($eventMatches->isEmpty()) {
                $this->info("No matches found for event ID: {$event->id}");
                continue;
            }

            // Step 3: Fetch match points for each match
            foreach ($eventMatches as $eventMatch) {
                $match = $eventMatch->match; // Assuming a relationship 'match' exists on the EventMatch model
                if (!$match) {
                    $this->info("No match data found for EventMatch ID: {$eventMatch->id}");
                    continue;
                }

                $externalMatchId = $match->external_match_id;
                $apiKey = Helper::getApiKey(); // Assuming the API key is stored in services config

                $response = Http::get("https://api.cricapi.com/v1/match_points", [
                    'apikey' => $apiKey,
                    'id' => $externalMatchId,
                    'ruleset' => 101323 // You can change the ruleset if required
                ]);

                if ($response->successful()) {
                    $matchPointsData = $response->json();
                    // Check if 'totals' key exists and is not empty
                    if (!isset($matchPointsData['data']['totals']) || empty($matchPointsData['data']['totals'])) {
                        $this->info("No totals data found for match ID: {$externalMatchId}");
                        continue; // Skip to the next match
                    }
                    // Step 4: Process and store match points for each player
                    foreach ($matchPointsData['data']['totals'] as $playerData) {
                        $externalPlayerId = $playerData['id'];
                        $playerPoints = $playerData['points']; // Assuming points are stored under 'points'

                        // Update MatchPlayer with points
                        $matchPlayer = MatchPlayer::where('event_id', $event->id)
                            ->where('external_player_id', $externalPlayerId)
                            ->first();

                        if ($matchPlayer) {
                            // Check if the current MatchPlayer is the captain of the team
                            // $team = Team::where('event_id', $event->id)
                            //     ->where('captain_match_player_id', $matchPlayer->id)
                            //     ->first();

                            $matchPlayer->points = $playerPoints;
                            $this->info("Player {$matchPlayer->name} points updated to {$playerPoints} for Match ID: {$match->id}");
                            // if ($team) {
                            //     // If the player is the captain, double their points
                            //     $matchPlayer->points = $playerPoints * 2;
                            //     $this->info("Captain {$matchPlayer->name} points updated to {$matchPlayer->points} for Match ID: {$match->id}");
                            // } else {
                            //     // If not the captain, assign regular points
                            //     $matchPlayer->points = $playerPoints;
                            //     $this->info("Player {$matchPlayer->name} points updated to {$playerPoints} for Match ID: {$match->id}");
                            // }

                            $matchPlayer->save();
                        } else {
                            $this->info("MatchPlayer not found for player ID: {$externalPlayerId}");
                        }
                    }
                } else {
                    $this->error("Failed to fetch points for match ID: {$externalMatchId}");
                }
            }
        }

        $this->info('Player points fetching completed.');
    }
}
