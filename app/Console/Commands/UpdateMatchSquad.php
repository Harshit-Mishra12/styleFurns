<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Helpers\Helper;
use App\Models\EventMatch;
use App\Models\MatchPlayer;
use App\Models\Matches;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class UpdateMatchSquad extends Command
{
    protected $signature = 'update:match-squad';

    protected $description = 'Fetch squad data for upcoming matches and update match players if squad has not been announced';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Fetch all upcoming events
        $upcomingEvents = Event::where('status', 'UPCOMING')->get();


        Log::info('UpdateMatchSquad job started.');
        $this->info("UpdateMatchSquad job started.");
        $apiKey = Helper::getApiKey(); // Assume you have a helper function to get API key

        foreach ($upcomingEvents as $event) {
            // Get all matches for the event using the event_matches table
            $eventMatches = EventMatch::where('event_id', $event->id)->get();

            foreach ($eventMatches as $eventMatch) {
                $match = Matches::find($eventMatch->match_id);
                // $this->info("UpdateMatchSquad job started1.{$match}");
                if ($match && !$match->is_squad_announced) {
                    $this->info("UpdateMatchSquad job started.{$match}");
                    // Fetch squad data from the API
                    $response = Http::get('https://api.cricapi.com/v1/match_squad', [
                        'apikey' => $apiKey,
                        'id' => $match->external_match_id,
                    ]);
                    // $this->info("response.{  $response->data}");
                    if ($response->successful()) {
                        $responseData = $response->json();
                        if (empty($responseData['data'])) {
                            $this->info("response.{ $response}");
                            // The 'data' array is empty
                            $this->error(" squad data  not found for match ID: {$match->id}");
                        } else {


                            $squadData = $response->json();

                            // if ($squadData && isset($squadData['data'])) {
                                $this->info("check matchsquad harshit " . count($squadData['data']) . ".");

                                if ($squadData && is_array($squadData) && count($squadData['data']) === 2 && isset($squadData['data'])) {
                                DB::beginTransaction();
                                try {
                                    // Process and store match player data
                                    foreach ($squadData['data'] as $team) {
                                        foreach ($team['players'] as $player) {
                                            $roleString = strtolower(trim($player['role']));

                                            // Determine the player's role
                                            if (stripos($roleString, 'wk-batsman') !== false) {
                                                $role = 'wicketkeeper';
                                            } elseif (stripos($roleString, 'batting allrounder') !== false || stripos($roleString, 'bowling allrounder') !== false) {
                                                $role = 'allrounder';
                                            } elseif (stripos($roleString, 'batsman') !== false && stripos($roleString, 'wk') === false) {
                                                $role = 'batsman';
                                            } elseif (stripos($roleString, 'bowler') !== false) {
                                                $role = 'bowler';
                                            } else {
                                                $role = 'unknown'; // Default if no role match
                                            }

                                            // Create the match players table
                                            MatchPlayer::create([
                                                'match_id' => $match->id,
                                                'event_id' => $event->id,
                                                'external_player_id' => $player['id'],
                                                'name' => $player['name'],
                                                'role' =>   $role,
                                                'country' => $player['country'],
                                                'team' => isset($team['teamName']) ? $team['teamName'] : 'Unknown',
                                                'image_url' => $player['playerImg'],
                                                'status' => "UNANNOUNCED"
                                            ]);
                                        }
                                    }

                                    // Update the match to mark the squad as announced
                                    $match->update([
                                        'is_squad_announced' => true
                                    ]);
                                    Log::info("Squad successfully fetched and stored for Match ID: {$match->id}");
                                    DB::commit();

                                    $this->info("Match squad updated for match ID: {$match->id}");
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                    $this->error("Error updating squad for match ID: {$match->id} - {$e->getMessage()}");
                                }
                            } else {
                                $this->error("No valid squad data found for match ID: {$match->id}");
                                Log::warning("No squad data available for Match ID: {$match->id}");
                            }
                        }
                    } else {
                        $this->error("Failed to fetch squad data for match ID: {$match->id}");
                    }
                }
                else{
                    $this->info("UpdateMatchSquad already announced.");
                }
            }
        }

        return 0;
    }
}
