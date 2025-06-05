<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use App\Models\EventMatch;
use App\Models\MatchPlayer;
use App\Models\User;
use App\Models\Team;
use App\Models\TeamPlayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CreateRandomTeam extends Command
{
    protected $signature = 'create:random-team';
    protected $description = 'Create random teams for events 5 minutes before the start of the first match';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {

        $events = Event::where('status', 'LIVE')
            // ->where('active', true) // Ensures 'active' is true
            ->where('activate_status', 'ACTIVE') // Ensures 'activate_status' is 'ACTIVE'
            ->get();

        Log::info('CreateRandomTeam job started.');
        $this->info("CreateRandomTeam job started.");
        // Fetch a specific event for testing
        // $this->info("test: {$events}");

        if ($events->isEmpty()) {
            $this->info("No live events found.");
            Log::error('No live events found.');
            return;
        }

        foreach ($events as $event) {
            // Get matches for the event using a manual join with the event_matches table
            $matches = DB::table('matches')
                ->join('event_matches', 'matches.id', '=', 'event_matches.match_id')
                ->where('event_matches.event_id', $event->id)
                ->orderBy('matches.date_time', 'asc')
                ->select('matches.*') // Select all columns from matches
                ->get();

            // Get the first match of the event
            $firstMatch = $matches->first();

            // $this->info("First match details: " . json_encode($firstMatch));
            if (!$firstMatch) {
                $this->info("No matches found for event ID: {$event->id}");
                continue;
            }

            // Fetch users with the role RANDOMUSER
            $randomUsers = User::where('role', 'RANDOMUSER')->get();
            // Get event squad using the existing getEventSquad method
            // $squadData = $this->getEventSquad($event->id);

            $squadResponse = $this->getEventSquad($event->id);

            // Check the status code of the response
            if ($squadResponse['status_code'] === 1) {
                // Success: Display the squad data
                $this->info('Event squad data fetched successfully.');
                $eventSquad = $squadResponse['data']; // Get the squad data

                // $this->info("Event squad data fetched successfully: { $eventSquad }");
                // $this->info("Event squad data fetched successfully: " . json_encode($eventSquad));
                // Assuming you have a way to fetch random users, like:
                // Fetch random users for team creation
                if ($randomUsers->isEmpty() || empty($eventSquad)) {

                    $this->info("No random users or no squad data for event ID: {$event->id}");
                    continue;
                }
                // Loop through each random user and create a team
                foreach ($randomUsers as $user) {
                    $this->createTeamForUser($user, $event, json_encode($eventSquad));
                }
            } else {
                $this->info("status code is not 1");
            }
        }
    }

    private function createTeamForUser($user, $event, $squad)
    {
        // Convert JSON string to object if necessary
        if (is_string($squad)) {
            $squad = json_decode($squad);
        }

        // Validate squad structure
        if (!isset($squad->batsman) || !isset($squad->bowler) || !isset($squad->allrounder) || !isset($squad->wicketkeeper)) {
            throw new \Exception('Invalid squad structure.');
        }

        // Get the team limit and user participation limit
        $teamLimit = $event->team_limit_per_user;
        $participationLimit = $event->user_participation_limit;

        while (true) {
            // Get current team count for the user
            $userTeamCount = Team::where('user_id', $user->id)
                ->where('event_id', $event->id)
                ->count();

            // Skip user if they have already reached the team limit
            if ($userTeamCount >= $teamLimit) {
                $this->info("User {$user->id} has already reached the team limit for event {$event->id}.");
                break; // Exit the loop if the team limit is reached
            }

            // Check the total user participation count for the event
            $currentParticipationCount = Team::where('event_id', $event->id)->count();

            // Skip if the participation limit has been reached
            if ($currentParticipationCount >= $participationLimit) {
                $this->info("Event {$event->id} has reached the maximum user participation limit.");
                break; // Exit the loop if participation limit is reached
            }

            // Start selecting players for the team
            $batsmen = collect($squad->batsman)->take(5); // Up to 5 batsmen
            $bowlers = collect($squad->bowler)->take(3);  // Up to 3 bowlers
            $allrounders = collect($squad->allrounder)->take(2); // Up to 2 allrounders
            $wicketkeepers = collect($squad->wicketkeeper)->take(1); // Up to 1 wicketkeeper

            // $batsmen = collect($squad->batsman)->shuffle()->take(5); // Randomly pick up to 5 batsmen
            // $bowlers = collect($squad->bowler)->shuffle()->take(3); // Randomly pick up to 3 bowlers
            // $allrounders = collect($squad->allrounder)->shuffle()->take(2); // Randomly pick up to 2 allrounders
            // $wicketkeepers = collect($squad->wicketkeeper)->shuffle()->take(1);

            // $batsmen = collect($squad->batsman)->shuffle()->take(min(5, $squad->batsman->count())); // Take up to 5 batsmen or whatever is available
            // $bowlers = collect($squad->bowler)->shuffle()->take(min(3, $squad->bowler->count())); // Take up to 3 bowlers or whatever is available
            // $allrounders = collect($squad->allrounder)->shuffle()->take(min(2, $squad->allrounder->count())); // Take up to 2 allrounders or whatever is available
            // $wicketkeepers = collect($squad->wicketkeeper)->shuffle()->take(min(1, $squad->wicketkeeper->count())); // Take 1 wicketkeeper or whatever is available

            // Combine selected players
            $selectedPlayers = $batsmen->merge($bowlers)->merge($allrounders)->merge($wicketkeepers);

            // If selected players are less than 11, pick additional players directly from the entire squad
            if ($selectedPlayers->count() < 11) {
                $remainingCount = 11 - $selectedPlayers->count();

                // Flatten all players into a single collection
                $allPlayers = collect($squad->batsman)
                    ->merge($squad->bowler)
                    ->merge($squad->allrounder)
                    ->merge($squad->wicketkeeper);

                // Select additional players randomly from the remaining pool
                $additionalPlayers = $allPlayers->shuffle()->take($remainingCount);
                $selectedPlayers = $selectedPlayers->merge($additionalPlayers);
            }

            // Ensure we now have exactly 11 players
            if ($selectedPlayers->count() !== 11) {
                $this->error("Unable to select exactly 11 players for the team.");
                return; // Skip team creation if we cannot form a valid team
            }

            // Select a random player as captain
            $captain = $selectedPlayers->random();

            // Create the team
            $totalTeamCount = Team::where('event_id', $event->id)->count();
            $team_no = $totalTeamCount + 1;
            $team = Team::create([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'captain_match_player_id' => $captain->matchPlayerId, // Captain chosen randomly
                'status' => 'active',
                'name' => 'Team ' . $team_no, // Dynamic team name with incremented number
                'points_scored' => 0,
            ]);

            // Insert selected players into the team_players table
            foreach ($selectedPlayers as $player) {
                DB::table('team_players')->insert([
                    'team_id' => $team->id,
                    'match_player_id' => $player->matchPlayerId, // Match player ID
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->info("Team {$team->name} created successfully for user {$user->id}.");
        }
    }


    // private function createTeamForUser($user, $event, $squad)
    // {
    //     // Convert JSON string to object if necessary
    //     if (is_string($squad)) {
    //         $squad = json_decode($squad);
    //     }

    //     // Validate squad structure
    //     if (!isset($squad->batsman) || !isset($squad->bowler) || !isset($squad->allrounder) || !isset($squad->wicketkeeper)) {
    //         throw new \Exception('Invalid squad structure.');
    //     }

    //     // Get the team limit and user participation limit
    //     $teamLimit = $event->team_limit_per_user;
    //     $participationLimit = $event->user_participation_limit;

    //     // Loop to create teams while the limit allows
    //     while (true) {
    //         // Get current team count for the user
    //         $userTeamCount = Team::where('user_id', $user->id)
    //             ->where('event_id', $event->id)
    //             ->count();

    //         // Skip user if they have already reached the team limit
    //         if ($userTeamCount >= $teamLimit) {
    //             $this->info("User {$user->id} has already reached the team limit for event {$event->id}.");
    //             break; // Exit the loop if the team limit is reached
    //         }

    //         // Check the total user participation count for the event
    //         $currentParticipationCount = Team::where('event_id', $event->id)->count();

    //         // Skip if the participation limit has been reached
    //         if ($currentParticipationCount >= $participationLimit) {
    //             $this->info("Event {$event->id} has reached the maximum user participation limit.");
    //             break; // Exit the loop if participation limit is reached
    //         }

    //         // Select players for the team
    //         $batsmen = collect($squad->batsman)->random(5);
    //         $bowlers = collect($squad->bowler)->random(3);
    //         $allrounders = collect($squad->allrounder)->random(2);
    //         $wicketkeeper = collect($squad->wicketkeeper)->random(1);
    //         $selectedPlayers = $batsmen->merge($bowlers)->merge($allrounders)->merge($wicketkeeper);

    //         // Select a random player as captain
    //         $captain = $selectedPlayers->random();

    //         // Check if JSON decoding was successful
    //         if (json_last_error() !== JSON_ERROR_NONE) {
    //             $this->error("Failed to decode squad JSON: " . json_last_error_msg());
    //             return; // Exit if decoding fails
    //         }

    //         // Create the team
    //         $totalTeamCount = Team::where('event_id', $event->id)->count();
    //         $team_no = $totalTeamCount + 1;
    //         $team = Team::create([
    //             'user_id' => $user->id,
    //             'event_id' => $event->id,
    //             'captain_match_player_id' => $captain->matchPlayerId, // Captain chosen randomly
    //             'status' => 'active',
    //             'name' => 'Team ' . $team_no, // Dynamic team name with incremented number
    //             'points_scored' => 0,
    //         ]);

    //         // Insert selected players into the team_players table
    //         foreach ($selectedPlayers as $player) {
    //             DB::table('team_players')->insert([
    //                 'team_id' => $team->id,
    //                 'match_player_id' => $player->matchPlayerId, // Match player ID
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ]);
    //         }

    //         $this->info("Team {$team->name} created successfully for user {$user->id}.");
    //     }
    // }


    public function getEventSquad($event_id)
    {
        try {
            // Validate the input
            if (!is_numeric($event_id)) {
                return [
                    'status_code' => 2,
                    'message' => 'Invalid event ID provided.',
                    'data' => []
                ];
            }

            // Fetch all matches for the given event ID using EventMatch model
            $eventMatches = EventMatch::where('event_id', $event_id)
                ->with('match') // Ensure the 'match' relationship is defined in the EventMatch model
                ->get();

            // Check if matches are available
            if ($eventMatches->isEmpty()) {
                return [
                    'status_code' => 2,
                    'message' => 'No matches found for the provided event ID.',
                    'data' => []
                ];
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
                        'matchStartDateTime' => $matchStartDateTime,
                        'role' => $player->role
                    ];

                    // Assign to the appropriate role category
                    if (array_key_exists($role, $combinedData)) {
                        $combinedData[$role][] = $playerDetails;
                    } else {
                        $combinedData['unknown'][] = $playerDetails;
                    }
                }
            }

            return [
                'status_code' => 1,
                'message' => 'Event squad data fetched successfully.',
                'data' => $combinedData
            ];
        } catch (\Exception $e) {
            // Handle exception and return a failure response
            return [
                'status_code' => 2,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }
}
