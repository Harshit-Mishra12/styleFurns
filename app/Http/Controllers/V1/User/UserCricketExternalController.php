<?php

namespace App\Http\Controllers\V1\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\EventMatch;
use App\Models\MatchPlayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


class UserCricketExternalController extends Controller
{
    public function getEventSquad($event_id)
    {
        try {
            // Validate the input
            if (!is_numeric($event_id)) {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Invalid event ID provided.',
                    'data' => []
                ]);
            }

            // Fetch all matches for the given event ID using EventMatch model
            $eventMatches = EventMatch::where('event_id', $event_id)
                ->with('match') // Ensure the 'match' relationship is defined in the EventMatch model
                ->get();


            // Check if matches are available
            if ($eventMatches->isEmpty()) {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'No matches found for the provided event ID.',
                    'data' => []
                ]);
            }

            // Initialize arrays to hold combined data
            $combinedData = [
                'batsman' => [],
                'bowler' => [],
                'allrounder' => [],
                'wicketkeeper' => [],
                'unknown' => []
            ];

            // Process each match and combine the data
            foreach ($eventMatches as $eventMatch) {
                $match = $eventMatch->match; // Assuming 'match' is the relationship defined in EventMatch model

                if (!$match) {
                    continue; // Skip if the related match is not found
                }

                $externalMatchId = $match->external_match_id;

                // Fetch players for the match using MatchPlayer model
                $matchPlayers = MatchPlayer::select('match_players.*', 'matches.date_time')
                ->leftJoin('matches', 'match_players.match_id', '=', 'matches.id') // Join with matches table to get match start time
                ->where('match_players.event_id', $event_id) // Use event ID from the event
                ->where('matches.external_match_id', $externalMatchId) // Filter by external match ID
                ->get();



                // Combine players based on their roles and include additional details
                foreach ($matchPlayers as $player) {
                    $role = strtolower($player->role); // Normalize the role for easier comparison

                    // Default to 'N/A' if certain values are not present
                    $playingStatus = $player->status ?? 'N/A';
                    $matchPlayerId = $player->id;
                    $matchStartDateTime = $player->date_time;

                    $playerDetails = [
                        'playerId' => $player->external_player_id, // Assuming 'external_player_id' refers to player ID
                        'name' => $player->name,
                        'battingStyle' => $player->batting_style ?? 'N/A', // Assuming 'battingStyle' exists in MatchPlayer
                        'bowlingStyle' => $player->bowling_style ?? 'N/A', // Assuming 'bowlingStyle' exists in MatchPlayer
                        'country' => $player->country,
                        'playerImg' => $player->image_url ?? 'N/A',
                        'playingStatus' => $playingStatus,
                        'matchPlayerId' => $matchPlayerId,
                        'matchStartDateTime' => $matchStartDateTime
                    ];

                    // Assign to the appropriate role category
                    if ($role === 'batsman') {
                        $combinedData['batsman'][] = $playerDetails;
                    } elseif ($role === 'bowler') {
                        $combinedData['bowler'][] = $playerDetails;
                    } elseif ($role === 'allrounder') {
                        $combinedData['allrounder'][] = $playerDetails;
                    } elseif ($role === 'wicketkeeper') {
                        $combinedData['wicketkeeper'][] = $playerDetails;
                    } else {
                        $combinedData['unknown'][] = $playerDetails;
                    }
                }

            }

            // Return the combined data in the response
            return response()->json([
                'status_code' => 1,
                'message' => 'Event squad data fetched successfully.',
                'data' => $combinedData
            ]);
        } catch (\Exception $e) {
            // Handle exception and return a failure response
            return response()->json([
                'status_code' => 2,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ]);
        }
    }


    // public function getMatchSquad($event_id)
    // {
    //     try {
    //         // Validate the input
    //         if (!is_numeric($event_id)) {
    //             return response()->json([
    //                 'status_code' => 2,
    //                 'message' => 'Invalid event ID provided.',
    //                 'data' => []
    //             ]);
    //         }

    //         // Fetch all matches for the given event ID using EventMatch model
    //         $eventMatches = EventMatch::where('event_id', $event_id)
    //             ->with('match') // Ensure the 'match' relationship is defined in the EventMatch model
    //             ->get();



    //         // Check if matches are available
    //         if ($eventMatches->isEmpty()) {
    //             return response()->json([
    //                 'status_code' => 2,
    //                 'message' => 'No matches found for the provided event ID.',
    //                 'data' => []
    //             ]);
    //         }

    //         // Get the API key from a helper function or environment variable
    //         $apiKey = Helper::getApiKey();

    //         // Initialize arrays to hold combined data
    //         $combinedData = [
    //             'batsman' => [],
    //             'bowler' => [],
    //             'allrounder' => [],
    //             'wicketkeeper' => [],
    //             'unknown' => []
    //         ];

    //         // Process each match and combine the data
    //         foreach ($eventMatches as $eventMatch) {
    //             $match = $eventMatch->match; // Assuming 'match' is the relationship defined in EventMatch model

    //             if (!$match) {
    //                 continue; // Skip if the related match is not found
    //             }

    //             $externalMatchId = $match->external_match_id;

    //             // Fetch match squad data from the external API
    //             $response = Http::get("https://api.cricapi.com/v1/match_squad", [
    //                 'apikey' => $apiKey,
    //                 'id' => $externalMatchId
    //             ]);


    //             // Check if the response is successful
    //             if ($response->successful()) {

    //                 $data = $response->json();
    //                 // return $data;
    //                 // Process and transform the data

    //                 $matchData = $this->processData($data, $event_id);

    //                 // Merge data into combined arrays
    //                 foreach ($combinedData as $role => &$players) {
    //                     if (isset($matchData[$role])) {
    //                         $players = array_merge($players, $matchData[$role]);
    //                     }
    //                 }
    //             } else {
    //                 // Handle case where fetching squad data fails
    //                 return response()->json([
    //                     'status_code' => 2,
    //                     'message' => 'Failed to fetch squad data from external API.',
    //                     'data' => []
    //                 ]);
    //             }
    //         }

    //         // Return successful response with combined data
    //         return response()->json([
    //             'status_code' => 1,
    //             'message' => 'Squad data fetched successfully.',
    //             'data' => $combinedData
    //         ]);
    //     } catch (\Exception $e) {
    //         // Handle exceptions
    //         return response()->json([
    //             'status_code' => 2,
    //             'message' => 'Error fetching match squad data: ' . $e->getMessage(),
    //             'data' => []
    //         ]);
    //     }
    // }
    private function processData($data, $event_id)
    {

        $roles = ['batsman', 'bowler', 'allrounder', 'wicketkeeper'];
        $processed = [];

        // Check if 'data' key exists in the response
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $team) {
                foreach ($team['players'] as $player) {
                    // Extract role and normalize it
                    $role = strtolower($player['role'] ?? 'unknown'); // Default to 'unknown' if role is not set

                    // Normalize role to match predefined roles
                    if (strpos($role, 'batsman') !== false && strpos($role, 'wk') === false) {
                        $role = 'batsman';
                    } elseif (strpos($role, 'bowler') !== false) {
                        $role = 'bowler';
                    } elseif (strpos($role, 'allrounder') !== false) {
                        $role = 'allrounder';
                    } elseif (strpos($role, 'wk') !== false) {
                        $role = 'wicketkeeper';
                    } else {
                        $role = 'unknown';
                    }

                    // Fetch the playing status from the MatchPlayer table using the external_player_id and match_id
                    // $matchPlayer = MatchPlayer::where('external_player_id', $player['id'])
                    //     ->where('event_id', $event_id)
                    //     ->first();

                    // // Default to 'N/A' if no matchPlayer entry is found
                    // $playingStatus = $matchPlayer ? $matchPlayer->status : 'N/A';
                    // $matchPlayerId = $matchPlayer->id;
                    // // Initialize the array for the role if not already set
                    // if (!isset($processed[$role])) {
                    //     $processed[$role] = [];
                    // }
                    // $dateTime = DB::table('matches')
                    //     ->where('id', $matchPlayer->match_id) // Use the match_id to find the match in the matches table
                    //     ->value('date_time');
                    // echo  $dateTime;
                    // Add player information to the appropriate role
                    $matchPlayer = MatchPlayer::select('match_players.*', 'matches.date_time')
                        ->leftJoin('matches', 'match_players.match_id', '=', 'matches.id') // Join with the matches table
                        ->where('match_players.external_player_id', $player['id'])
                        ->where('match_players.event_id', $event_id)
                        ->first();

                    // Default to 'N/A' if no matchPlayer entry is found
                    $playingStatus = $matchPlayer ? $matchPlayer->status : 'N/A';
                    $matchPlayerId = $matchPlayer ? $matchPlayer->id : null;
                    $dateTime = $matchPlayer ? $matchPlayer->date_time : null;

                    $processed[$role][] = [
                        'playerId' => $player['id'],
                        'name' => $player['name'],
                        'battingStyle' => $player['battingStyle'],
                        'bowlingStyle' => $player['bowlingStyle'] ?? 'N/A',
                        'country' => $player['country'],
                        'playerImg' => $player['playerImg'],
                        'playingStatus' => $playingStatus,
                        'matchPlayerId' => $matchPlayerId,
                        'matchStartDateTime' =>  $dateTime
                    ];
                }
            }
        }

        // Create a sorted array with 'unknown' at the end
        $sortedProcessed = array_intersect_key($processed, array_flip($roles));
        if (isset($processed['unknown'])) {
            $sortedProcessed['unknown'] = $processed['unknown'];
        }

        return $sortedProcessed;
    }

    // private function processData($data)
    // {
    //     $roles = ['batsman', 'bowler', 'allrounder', 'wicketkeeper'];
    //     $processed = [];

    //     // Check if 'data' key exists in the response
    //     if (isset($data['data']) && is_array($data['data'])) {
    //         foreach ($data['data'] as $team) {
    //             foreach ($team['players'] as $player) {
    //                 // Extract role and normalize it
    //                 $role = strtolower($player['role'] ?? 'unknown'); // Default to 'unknown' if role is not set

    //                 // Normalize role to match predefined roles
    //                 if (strpos($role, 'batsman') !== false && strpos($role, 'wk') === false) {
    //                     $role = 'batsman';
    //                 } elseif (strpos($role, 'bowler') !== false) {
    //                     $role = 'bowler';
    //                 } elseif (strpos($role, 'allrounder') !== false) {
    //                     $role = 'allrounder';
    //                 } elseif (strpos($role, 'wk') !== false) {
    //                     $role = 'wicketkeeper';
    //                 } else {
    //                     $role = 'unknown';
    //                 }

    //                 // Initialize the array for the role if not already set
    //                 if (!isset($processed[$role])) {
    //                     $processed[$role] = [];
    //                 }

    //                 // Add player information to the appropriate role
    //                 $processed[$role][] = [
    //                     'playerId' => $player['id'],
    //                     'name' => $player['name'],
    //                     'battingStyle' => $player['battingStyle'],
    //                     'bowlingStyle' => $player['bowlingStyle'] ?? 'N/A',
    //                     'country' => $player['country'],
    //                     'playerImg' => $player['playerImg']
    //                 ];
    //             }
    //         }
    //     }

    //     // Create a sorted array with 'unknown' at the end
    //     $sortedProcessed = array_intersect_key($processed, array_flip($roles));
    //     if (isset($processed['unknown'])) {
    //         $sortedProcessed['unknown'] = $processed['unknown'];
    //     }

    //     return $sortedProcessed;
    // }
}
