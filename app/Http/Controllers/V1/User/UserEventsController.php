<?php

namespace App\Http\Controllers\V1\User;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventMatch;
use App\Models\Matches;
use App\Models\MatchPlayer;
use App\Models\UserTeam;
use App\Models\EventPrize;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\UserWallet;
use App\Models\UserTransaction;
use Illuminate\Support\Facades\Http;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;



class UserEventsController extends Controller
{


    public function getEventsListByStatus(Request $request)
    {
        $validated = $request->validate([
            'event_status_type' => 'required|string',
            'per_page' => 'nullable|integer|min:1', // Optional number of items per page
            'page' => 'nullable|integer|min:1', // Optional page number
        ]);

        $eventStatusType = $validated['event_status_type'];
        $perPage = $validated['per_page'] ?? 5; // Default to 10 items per page
        $currentPage = $validated['page'] ?? 1; // Default to the first page
        $query = Event::query();
        // Get paginated events
        $events = $query->where('status', $eventStatusType) // Add this line to filter by status
            ->where('activate_status', 'ACTIVE')
            ->select('*') // Selecting all columns using '*'
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $currentPage);



        $currentDateTime = now();
        $eventsList = $events->getCollection()->map(function ($event) use ($currentDateTime, $eventStatusType) {
            // Team counts and match status logic remain unchanged
            $teamCounts = DB::table('teams')
                ->select(
                    DB::raw('COUNT(DISTINCT CASE WHEN user_id = ' . auth()->id() . ' THEN id END) AS teamCount'),
                    DB::raw('COUNT(id) AS occupancyCount')
                )
                ->where('event_id', $event->id)
                ->first();


            // Access results
            $teamCount = $teamCounts->teamCount;
            $occupancyCount = $teamCounts->occupancyCount;
            // Get event matches for this event
            $eventMatches = EventMatch::where('event_id', $event->id)
                ->with('match') // Ensure the 'match' relationship is properly defined in EventMatch model
                ->get();

            // Format the match details
            // Check if all matches have is_squad_announced as true
            if (!$eventMatches->every(fn($eventMatch) => $eventMatch->match->is_squad_announced)) {
                return null; // Skip this event if not all matches have is_squad_announced as true
            }

            $eventMatchesDetails = $eventMatches->map(function ($eventMatch) {
                return [
                    'match_id' => $eventMatch->match_id,
                    'team1' => $eventMatch->match->team1,
                    'team1_flag' => $eventMatch->match->team1_url,
                    'team2' => $eventMatch->match->team2,
                    'team2_flag' => $eventMatch->match->team2_url,
                    'date_time' => $eventMatch->match->date_time,
                    'venue' => $eventMatch->match->venue,
                    'status' => $eventMatch->match->status,
                    'is_squad_announced' => $eventMatch->match->is_squad_announced
                    // Add other match details if needed
                ];
            });

            $prizeData  = DB::table('event_prizes')
                ->select(
                    DB::raw('SUM((rank_to - rank_from + 1) * prize_amount) AS total_prize_amount'),
                    DB::raw('MAX(CASE WHEN rank_from = 1 THEN prize_amount ELSE NULL END) AS first_prize_amount')
                )
                ->where('event_id', $event->id)
                ->first();

            // Return event details with the list of matches
            return [
                'id' => $event->id,
                'event_name' => $event->name,
                'go_live_date' => $event->go_live_date,
                'team_size' => $event->team_size,
                'batsman_limit' => $event->batsman_limit,
                'bowler_limit' => $event->bowler_limit,
                'wicketkeeper_limit' => $event->wicketkeeper_limit,
                'all_rounder_limit' => $event->all_rounder_limit,
                'team_creation_cost' => $event->team_creation_cost,
                'user_participation_limit' => $event->user_participation_limit,
                'winners_limit' => $event->winners_limit,
                'occupancy' => $occupancyCount ?? 0, // Get the count or default to 0
                'team_limit_per_user' => $event->team_limit_per_user,
                'status' => $event->status,
                'activate_status' => $event->activate_status,
                'participated' => $teamCount > 0 ? true : false,
                'matchesCount' => count($eventMatches),
                'myTeamsCount' => $teamCount ?? 0,
                'total_prize_amount' => $prizeData->total_prize_amount,
                'first_prize_amount' => $prizeData->first_prize_amount,
                'matches' => $eventMatchesDetails, // List of matches for this event
            ];
        })->filter(); // Filter out null values (events that don't match the status type)

        // Prepare the paginated response
        return response()->json([
            'status_code' => 1,
            'message' => 'Events retrieved successfully',
            'data' => [
                'eventsList' => $eventsList->values(), // Return non-null filtered events
                'totalEvents' => $events->total(), // Total count of events
                'currentPage' => $events->currentPage(),
                'lastPage' => $events->lastPage(),
                'perPage' => $events->perPage(),
            ]
        ]);
    }


    public function getMyEventsListByStatus(Request $request)
    {
        // Validate the input to ensure 'event_status_type' is provided
        $validated = $request->validate([
            'event_status_type' => 'required|string',
        ]);

        // Extract the event status type from the request
        $eventStatusType = $validated['event_status_type'];

        // Base query for events
        $query = Event::query();

        // Get all events (no pagination)
        $events = $query->where('status', $eventStatusType)
            ->select('*') // Selecting all columns using '*'
            ->orderBy('created_at', 'desc')
            ->get();
        // Retrieve all events instead of paginated data

        // Extract event IDs
        $eventIds = $events->pluck('id')->toArray();
        // Get the current date and time
        $currentDateTime = now();

        // Format the response data, filtering by event_status_type
        $eventsList = $events->map(function ($event) use ($currentDateTime, $eventStatusType) {
            // Determine the event status based on matches
            $teamCount = Team::where('user_id', auth()->id())
                ->where('event_id',  $event->id)
                ->distinct()
                ->count('id');
            $occupancyCount = Team::where('event_id', $event->id)
                ->count('id');

            $participated = Team::where('user_id', auth()->id())
                ->where('event_id', $event->id)
                ->exists();
            if (!$participated) {
                return null; // Skip events where user has not participated
            }
            // Get event matches for this event
            $eventMatches = EventMatch::where('event_id', $event->id)
                ->with('match') // Ensure the 'match' relationship is properly defined in EventMatch model
                ->get();

            // Format the match details
            $eventMatchesDetails = $eventMatches->map(function ($eventMatch) {
                return [
                    'match_id' => $eventMatch->match_id,
                    'team1' => $eventMatch->match->team1,
                    'team1_flag' => $eventMatch->match->team1_url,
                    'team2' => $eventMatch->match->team2,
                    'team2_flag' => $eventMatch->match->team2_url,
                    'date_time' => $eventMatch->match->date_time,
                    'venue' => $eventMatch->match->venue,
                    'status' => $eventMatch->match->status,
                    // Add other match details if needed
                ];
            });
            $prizeData  = DB::table('event_prizes')
                ->select(
                    DB::raw('SUM((rank_to - rank_from + 1) * prize_amount) AS total_prize_amount'),
                    DB::raw('MAX(CASE WHEN rank_from = 1 THEN prize_amount ELSE NULL END) AS first_prize_amount')
                )
                ->where('event_id', $event->id)
                ->first();
            // Return event details with the list of matches
            return [
                'id' => $event->id,
                'event_name' => $event->name,
                'go_live_date' => $event->go_live_date,
                'team_size' => $event->team_size,
                'batsman_limit' => $event->batsman_limit,
                'bowler_limit' => $event->bowler_limit,
                'wicketkeeper_limit' => $event->wicketkeeper_limit,
                'all_rounder_limit' => $event->all_rounder_limit,
                'team_creation_cost' => $event->team_creation_cost,
                'user_participation_limit' => $event->user_participation_limit,
                'team_limit_per_user' => $event->team_limit_per_user,
                'winners_limit' => $event->winners_limit,
                'occupancy' => $occupancyCount ?? 0, // Get the count or default to 0
                'user_participation_limit' => $event->user_participation_limit,
                'status' => $event->status,
                'activate_status' => $event->activate_status,
                'participated' => $participated,
                'total_prize_amount' => $prizeData->total_prize_amount,
                'first_prize_amount' => $prizeData->first_prize_amount,
                'matches' => $eventMatchesDetails, // List of matches for this event
                'myTeamsCount' => $teamCount ?? 0

            ];
        })->filter(); // Filter out null values (events that don't match the status type)

        // Get the total number of events (without pagination)
        $totalEvents = Event::count();

        return response()->json([
            'status_code' => 1,
            'message' => 'Events retrieved successfully',
            'data' => [
                'eventsList' => $eventsList->values(), // Return non-null filtered events
                'totalEvents' => $totalEvents
            ]
        ]);
    }

    public function getEventDetailsById($event_id)
    {
        // Fetch the event using the provided event_id
        $event = Event::find($event_id);

        // Check if the event exists
        if (!$event) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Event not found.',
            ]);
        }
        $teamCount = Team::where('user_id', auth()->id())
            ->where('event_id',  $event_id)
            ->distinct()
            ->count('id');

        $prizeData  = DB::table('event_prizes')
            ->select(
                DB::raw('SUM((rank_to - rank_from + 1) * prize_amount) AS total_prize_amount'),
                DB::raw('MAX(CASE WHEN rank_from = 1 THEN prize_amount ELSE NULL END) AS first_prize_amount')
            )
            ->where('event_id', $event->id)
            ->first();

        // Get the match details related to this event
        $eventMatches = EventMatch::where('event_id', $event_id)
            ->with('match') // Ensure the 'match' relationship is properly defined in the EventMatch model
            ->get();

        $occupancyCount = Team::where('event_id', $event->id)
            ->count('id');
        // Format the match details
        $eventMatchesDetails = $eventMatches->map(function ($eventMatch) {
            return [
                'match_id' => $eventMatch->match_id,
                'team1' => $eventMatch->match->team1,
                'team2' => $eventMatch->match->team2,
                'date_time' => $eventMatch->match->date_time,
                'venue' => $eventMatch->match->venue,
                'status' => $eventMatch->match->status,
                // Add other match details if needed
            ];
        });
        // Format the response with event details
        $eventDetails = [
            'id' => $event->id,
            'event_name' => $event->name,
            'go_live_date' => $event->go_live_date,
            'team_size' => $event->team_size,
            'batsman_limit' => $event->batsman_limit,
            'bowler_limit' => $event->bowler_limit,
            'wicketkeeper_limit' => $event->wicketkeeper_limit,
            'all_rounder_limit' => $event->all_rounder_limit,
            'team_creation_cost' => $event->team_creation_cost,
            'user_participation_limit' => $event->user_participation_limit,
            'team_limit_per_user' => $event->team_limit_per_user,
            'winners_limit' => $event->winners_limit,
            'occupancy' => $occupancyCount ?? 0, // Get the count or default to 0
            'status' => $event->status,
            'activate_status' => $event->activate_status,
            'total_prize_amount' => $prizeData->total_prize_amount,
            'first_prize_amount' => $prizeData->first_prize_amount,
            'matches' => $eventMatchesDetails, // List of matches for this event
            'myTeamsCount' => $teamCount ?? 0
        ];

        return response()->json([
            'status_code' => 1,
            'message' => 'Event details retrieved successfully',
            'data' => $eventDetails,
        ]);
    }

    public function fetchMatchesListByEventId(Request $request)
    {
        // Validate the input to ensure 'event_id' is provided
        $validated = $request->validate([
            'event_id' => 'required|integer|exists:events,id',
        ]);

        // Extract the event ID from the request
        $eventId = $validated['event_id'];

        // Retrieve the event by ID
        $event = Event::find($eventId);

        if (!$event) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Event not found',
            ]);
        }

        // Retrieve event matches for the given event ID
        $eventMatches = EventMatch::where('event_id', $eventId)
            ->with('match') // Ensure the 'match' relationship is defined in the EventMatch model
            ->get();

        // Check if there are no matches for the event
        if ($eventMatches->isEmpty()) {
            return response()->json([
                'status_code' => 2,
                'message' => 'No matches found for the event',
            ]);
        }

        // API Key and base URL for external API
        $apiKey = Helper::getApiKey();
        $apiBaseUrl = 'https://api.cricapi.com/v1/match_info';

        // Map the event matches to return the match details along with external data
        $eventMatchesDetails = $eventMatches->map(function ($eventMatch) use ($apiKey, $apiBaseUrl) {
            // Fetch external match details using the external_match_id
            $externalMatchId = $eventMatch->match->external_match_id;
            $response = Http::get("{$apiBaseUrl}?apikey={$apiKey}&id={$externalMatchId}");
            $externalData = $response->json();

            // Extract external data
            $externalMatchData = $externalData['data'] ?? [];

            return [
                'match_id' => $eventMatch->match_id,
                'team1' => $eventMatch->match->team1,
                'team2' => $eventMatch->match->team2,
                'date_time' => $eventMatch->match->date_time,
                'venue' => $eventMatch->match->venue,
                'status' => $eventMatch->match->status,
                'status' => $externalMatchData['status'] ?? '',
                'team_info' => $externalMatchData['teamInfo'] ?? [],
                'match_started' => $externalMatchData['matchStarted'] ?? false,
                'match_ended' => $externalMatchData['matchEnded'] ?? false,
                // Add other match details if needed
            ];
        });

        // Return the matches list
        return response()->json([
            'status_code' => 1,
            'message' => 'Matches retrieved successfully',
            'data' => [
                'event_id' => $event->id,
                'event_name' => $event->name,
                'matches' => $eventMatchesDetails, // List of matches with external data
            ]
        ]);
    }

    // public function fetchMatchesListByEventId(Request $request)
    // {
    //     // Validate the input to ensure 'event_id' is provided
    //     $validated = $request->validate([
    //         'event_id' => 'required|integer|exists:events,id',
    //     ]);

    //     // Extract the event ID from the request
    //     $eventId = $validated['event_id'];

    //     // Retrieve the event by ID
    //     $event = Event::find($eventId);

    //     if (!$event) {
    //         return response()->json([
    //             'status_code' => 2,
    //             'message' => 'Event not found',
    //         ]);
    //     }

    //     // Retrieve event matches for the given event ID
    //     $eventMatches = EventMatch::where('event_id', $eventId)
    //         ->with('match') // Ensure the 'match' relationship is defined in the EventMatch model
    //         ->get();

    //     // Check if there are no matches for the event
    //     if ($eventMatches->isEmpty()) {
    //         return response()->json([
    //             'status_code' => 2,
    //             'message' => 'No matches found for the event',
    //         ]);
    //     }

    //     // Map the event matches to return the match details
    //     $eventMatchesDetails = $eventMatches->map(function ($eventMatch) {
    //         return [
    //             'match_id' => $eventMatch->match_id,
    //             'team1' => $eventMatch->match->team1,
    //             'team2' => $eventMatch->match->team2,
    //             'date_time' => $eventMatch->match->date_time,
    //             'venue' => $eventMatch->match->venue,
    //             'status' => $eventMatch->match->status,
    //             // Add other match details if needed
    //         ];
    //     });

    //     // Return the matches list
    //     return response()->json([
    //         'status_code' => 1,
    //         'message' => 'Matches retrieved successfully',
    //         'data' => [
    //             'event_id' => $event->id,
    //             'event_name' => $event->name,
    //             'matches' => $eventMatchesDetails, // List of matches for this event
    //         ]
    //     ]);
    // }


    private function fetchMatchStatus($externalMatchId)
    {
        // Use the helper function to get the API key
        $apiKey = Helper::getApiKey();

        // Fetch match status from the external API
        $apiUrl = "https://api.cricapi.com/v1/match_info?apikey={$apiKey}&id={$externalMatchId}";
        $response = file_get_contents($apiUrl);

        if ($response === false) {
            // If the API call fails, return default values
            return ['matchStarted' => false, 'matchEnded' => true];
        }

        $status = json_decode($response, true);

        // Check if the response data and required fields are set correctly
        if (isset($status['data'])) {
            return [
                'matchStarted' => $status['data']['matchStarted'] ?? false,
                'matchEnded' => $status['data']['matchEnded'] ?? true
            ];
        }

        // Return default values if the response data is not structured as expected
        return ['matchStarted' => false, 'matchEnded' => true];
    }
    public function getEventDetails($eventId)
    {
        DB::beginTransaction();
        try {
            // Fetch event details
            $event = Event::find($eventId);

            if (!$event) {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Event not found'
                ]);
            }

            // Fetch event matches and their details
            $eventMatches = EventMatch::where('event_id', $eventId)
                ->with('match') // Assuming you have a relation defined in EventMatch model for matches
                ->get();

            // Count the number of matches for this event
            $matchesCount = $eventMatches->count();

            // Determine status based on go_live_date
            $today = now()->format('Y-m-d');
            if ($event->go_live_date === $today) {
                $status = 'LIVE';
            } elseif ($event->go_live_date < $today) {
                $status = 'COMPLETED';
            } else {
                $status = $event->status;
            }

            // Fetch event prizes
            $prizes = EventPrize::where('event_id', $eventId)
                ->orderBy('rank_from')
                ->get();

            // Fetch event matches and associated match data
            $eventMatches = EventMatch::where('event_id', $eventId)
                ->with('match')
                ->get()
                ->map(function ($eventMatch) {
                    return [
                        'match_id' => $eventMatch->match_id,
                        'team1' => $eventMatch->match->team1,
                        'team2' => $eventMatch->match->team2,
                        'date_time' => $eventMatch->match->date_time,
                        'venue' => $eventMatch->match->venue,
                        'status' => $eventMatch->match->status,
                        // Add other match details if needed
                    ];
                });

            // Count the number of users participating in this event
            $occupancyCount = UserTeam::where('event_id', $eventId)->count();

            DB::commit();

            return response()->json([
                'status_code' => 1,
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->name,
                        'go_live_date' => $event->go_live_date,
                        'team_size' => $event->team_size,
                        'batsman_limit' => $event->batsman_limit,
                        'bowler_limit' => $event->bowler_limit,
                        'wicketkeeper_limit' => $event->wicketkeeper_limit,
                        'all_rounder_limit' => $event->all_rounder_limit,
                        'team_creation_cost' => $event->team_creation_cost,
                        'user_participation_limit' => $event->user_participation_limit,
                        'winners_limit' => $event->winners_limit,
                        'matches_count' => $matchesCount,
                        'status' => $status
                    ],
                    'occupancy' => $occupancyCount,
                    'prizes' => $prizes,
                    'matches' => $eventMatches,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status_code' => 2,
                'message' => 'Error fetching event details',
                'error' => $e->getMessage()
            ]);
        }
    }


    public function deleteEvent($eventId)
    {
        DB::beginTransaction();

        try {
            // Find the event
            $event = Event::findOrFail($eventId);

            // Update the event status to "CANCELLED"
            $event->status = 'CANCELLED';
            $event->save();

            // Fetch event matches and their details
            $eventMatches = EventMatch::where('event_id', $eventId)
                ->with('match')
                ->get();

            // Count the number of matches for this event
            $matchesCount = $eventMatches->count();

            // Fetch event prizes
            $prizes = EventPrize::where('event_id', $eventId)
                ->orderBy('rank_from')
                ->get();

            // Fetch event matches and associated match data
            $eventMatches = EventMatch::where('event_id', $eventId)
                ->with('match')
                ->get()
                ->map(function ($eventMatch) {
                    return [
                        'match_id' => $eventMatch->match_id,
                        'team1' => $eventMatch->match->team1,
                        'team2' => $eventMatch->match->team2,
                        'date_time' => $eventMatch->match->date_time,
                        'venue' => $eventMatch->match->venue,
                        'status' => $eventMatch->match->status,
                        // Add other match details if needed
                    ];
                });

            // Count the number of users participating in this event
            $occupancyCount = UserTeam::where('event_id', $eventId)->count();

            DB::commit();

            return response()->json([
                'status_code' => 1,
                'message' => 'Event marked as cancelled successfully',
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->name,
                        'go_live_date' => $event->go_live_date,
                        'team_size' => $event->team_size,
                        'batsman_limit' => $event->batsman_limit,
                        'bowler_limit' => $event->bowler_limit,
                        'wicketkeeper_limit' => $event->wicketkeeper_limit,
                        'all_rounder_limit' => $event->all_rounder_limit,
                        'team_creation_cost' => $event->team_creation_cost,
                        'user_participation_limit' => $event->user_participation_limit,
                        'winners_limit' => $event->winners_limit,
                        'matches_count' => $matchesCount,
                        'status' => $event->status,  // Now 'CANCELLED'
                    ],
                    'occupancy' => $occupancyCount,
                    'prizes' => $prizes,
                    'matches' => $eventMatches,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status_code' => 2,
                'message' => 'Error cancelling event',
                'error' => $e->getMessage()
            ]);
        }
    }
    public function fetchEventPrizes($event_id)
    {
        // Validate that the event_id exists in the events table
        $eventExists = Event::where('id', $event_id)->exists();

        if (!$eventExists) {
            // If the event does not exist, return a 404 error response
            return response()->json([
                'status_code' => 2,
                'message' => 'Event not found.',
            ]);
        }

        // Fetch the event prizes sorted by rank_from if the event exists
        $prizes = EventPrize::where('event_id', $event_id)
            ->orderBy('rank_from')
            ->get();

        // Return the prizes in a JSON response
        return response()->json([
            'status_code' => 1,
            'message' => 'Event prizes fetched successfully.',
            'data' => ['prizes' => $prizes]
        ], 200);
    }
    public function createTeam(Request $request)
    {
        // Validate the inputE
        $validatedData = $request->validate([
            'captain_match_player_id' => 'required',

            'event_id' => 'required|exists:events,id', // Ensure event exists
            'match_player_ids' => 'required|array|min:1',
            'match_player_ids.*' => 'exists:match_players,id', // Ensure all IDs exist
        ]);
        $userId = auth()->user()->id;
        $event = Event::find($validatedData['event_id']);
        $teamLimitPerUser = $event->team_limit_per_user;
        $userParticipationLimit = $event->user_participation_limit;
        $userTeamCount = Team::where('user_id', $userId)
            ->where('event_id', $validatedData['event_id'])
            ->count();
        $teamCount = Team::where('event_id', $validatedData['event_id'])
            ->count();
        if ($teamCount >= $userParticipationLimit) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Event has reached the maximum participation limit for this event.',
            ]);
        }
        // If the user has reached or exceeded the team creation limit, prevent further creation
        if ($userTeamCount >= $teamLimitPerUser) {
            return response()->json([
                'status_code' => 2,
                'message' => 'You have reached the maximum team creation limit for this event.',
            ]); // Returning a 403 Forbidden status code
        }


        // Retrieve the event to get the team creation cost

        $teamCreationCost = $event->team_creation_cost;

        // Check if the user has sufficient funds
        $userWallet = UserWallet::where('user_id', $userId)->first();
        if (!$userWallet || $userWallet->balance < $teamCreationCost) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Insufficient funds to create a team.',
            ]);
        }

        DB::beginTransaction();
        $teamCountForEvent = Team::where('event_id', $validatedData['event_id'])->count();
        $team_no = $teamCountForEvent + 1; // Increment the count by 1 for the new team
        try {
            // Create the team
            $team = Team::create([
                'user_id' => $userId,
                'event_id' => $validatedData['event_id'],
                'captain_match_player_id' => $validatedData['captain_match_player_id'],
                'status' => 'active', // Default status
                'name' => 'Team ' . $team_no,
                'points_scored' => 0, // Default points
            ]);

            // Attach players to the team
            foreach ($validatedData['match_player_ids'] as $matchPlayerId) {
                TeamPlayer::create([
                    'team_id' => $team->id,
                    'match_player_id' => $matchPlayerId,
                ]);
            }

            // Deduct the team creation cost from the wallet
            $userWallet->balance -= $teamCreationCost;
            $userWallet->save();

            // Record the transaction for team creation
            UserTransaction::create([
                'user_id' => $userId,
                'team_id' => $team->id,
                'amount' => -$teamCreationCost,
                'transaction_type' => 'debit',
                'description' => 'Team Creation Fee',
                'status' => 'COMPLETED'
            ]);

            DB::commit();

            return response()->json([
                'status_code' => 1,
                'message' => 'Team and players successfully created',
                'team' => $team,
                'team_players' => $team->players,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status_code' => 2,
                'message' => 'Failed to create team or players',
                'error' => $e->getMessage(),
            ]);
        }
    }


    // public function createTeam(Request $request)
    // {
    //     // Validate the input
    //     $validatedData = $request->validate([
    //         'captain_match_player_id' => 'required',
    //         'event_id' => 'required',
    //         'match_player_ids' => 'required|array|min:1',
    //         'match_player_ids.*' => 'exists:match_players,id', // Ensure all IDs exist
    //     ]);

    //     try {
    //         // Create the team
    //         $team = Team::create([
    //             'user_id' => auth()->user()->id,
    //             'event_id' =>  $request->input('event_id'),
    //             'captain_match_player_id' => $request->input('captain_match_player_id'),
    //             'status' => 'active', // Default status, adjust as needed
    //             'name' => 'Team ' . Str::random(5),
    //             'points_scored' => 0, // Default points
    //         ]);

    //         // Attach players to the team
    //         foreach ($validatedData['match_player_ids'] as $matchPlayerId) {
    //             TeamPlayer::create([
    //                 'team_id' => $team->id,
    //                 'match_player_id' => $matchPlayerId,

    //             ]);
    //         }

    //         return response()->json([
    //             'status_code' => 1,
    //             'message' => 'Team and players successfully created',
    //             'team' => $team,
    //             'team_players' => $team->players,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status_code' => 2,
    //             'message' => 'Failed to create team or players',
    //             'error' => $e->getMessage(),
    //         ]);
    //     }
    // }
    public function updateTeam(Request $request)
    {
        // Validate the input
        $validatedData = $request->validate([
            'team_id' => 'required|exists:teams,id', // Ensure team exists
            'captain_match_player_id' => 'required',
            'match_player_ids' => 'required|array|min:1',
            'match_player_ids.*' => 'exists:match_players,id', // Ensure all IDs exist
        ]);

        try {
            // Find the team by ID
            $team = Team::find($validatedData['team_id']);

            // Update team details
            $team->update([
                'captain_match_player_id' => $request->input('captain_match_player_id'),
            ]);

            // Remove existing players from TeamPlayer associated with the team
            TeamPlayer::where('team_id', $team->id)->delete();

            // Attach new players to the team
            foreach ($validatedData['match_player_ids'] as $matchPlayerId) {
                TeamPlayer::create([
                    'team_id' => $team->id,
                    'match_player_id' => $matchPlayerId,
                ]);
            }

            return response()->json([
                'status_code' => 1,
                'message' => 'Team updated successfully',
                'team' => $team,
                'team_players' => $team->players, // Assuming the players relationship exists on Team
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Failed to update team or players',
                'error' => $e->getMessage(),
            ]);
        }
    }



    public function getTeamsByEvent($event_id)
    {
        // Fetch teams for the given event_id
        $user_id = auth()->user()->id;

        // Fetch teams for the given event_id that also belong to the authenticated user
        $teams = Team::where('event_id', $event_id)
            ->where('user_id', $user_id) // Filter teams by user_id
            ->get();

        // Check if any teams are found
        if ($teams->isEmpty()) {
            return response()->json([
                'status_code' => 2,
                'message' => 'No teams found for this event.',
            ]);
        }

        // Fetch players for each team using the getPlayersByTeam logic
        $teamsWithPlayers = $teams->map(function ($team) {
            // Fetch players for the team using team_id
            return [
                'team' => $team,
                'players' => $this->getPlayersByTeamData($team->id), // Reuse player fetching logic
            ];
        });

        // Return the list of teams with their players
        return response()->json([
            'status_code' => 1,
            'message' => 'Teams and players retrieved successfully.',
            'teams' => $teamsWithPlayers,
        ]);
    }
    public function getAllTeamsByEvent($event_id)
    {
        // Get the authenticated user's ID
        $user_id = auth()->user()->id;

        // Fetch teams for the given event_id and sort by points_scored in descending order
        // Also join the users table to get the user name for each team
        $teams = Team::where('event_id', $event_id)
            ->leftJoin('users', 'teams.user_id', '=', 'users.id') // Join with users table to get user name
            ->select('teams.*', 'users.name as user_name') // Select all fields from teams and the name field from users
            ->orderBy('points_scored', 'desc') // Sort teams by points_scored
            ->get();

        // Check if any teams are found
        if ($teams->isEmpty()) {
            return response()->json([
                'status_code' => 2,
                'message' => 'No teams found for this event.',
            ]);
        }

        // Fetch players for each team using the getPlayersByTeam logic and add isMy flag, rank, and user_name
        $teamsWithPlayers = $teams->map(function ($team, $index) use ($user_id) {
            // Check if the current team belongs to the authenticated user
            $isMyTeam = ($team->user_id === $user_id);

            // Add isMy and user_name to the response
            return [
                'team' => $team,
                'isMy' => $isMyTeam, // Indicate if the team belongs to the authenticated user
            ];
        });

        // Return the list of teams with their players, isMy flag, and rank inside team
        return response()->json([
            'status_code' => 1,
            'message' => 'Teams and players retrieved successfully.',
            'teams' => $teamsWithPlayers,
        ]);
    }


    public function getAllTeamsByUser($user_id)
    {
        // Validate the request to ensure user_id is provided

        // Get the user ID from the request


        // Fetch teams for the given user_id along with user name and rank
        $teams = Team::where('user_id', $user_id)
            ->leftJoin('events', 'teams.event_id', '=', 'events.id') // Join with events table
            ->leftJoin('users', 'teams.user_id', '=', 'users.id') // Join with users table to get user name
            ->select('teams.*', 'events.name as event_name', 'users.name as user_name') // Select necessary fields
            ->orderBy('created_at', 'desc') // Sort teams by creation date
            ->get();

        // Check if any teams are found for the user
        if ($teams->isEmpty()) {
            return response()->json([
                'status_code' => 2,
                'message' => 'No teams found for the specified user.',
            ]);
        }

        // Add rank and restructure the response
        $teamsWithDetails = $teams->map(function ($team, $index) {
            return [
                'team' => [
                    'id' => $team->id,
                    'event_id' => $team->event_id,
                    'user_id' => $team->user_id,
                    'name' => $team->name,
                    'points_scored' => $team->points_scored,
                    'created_at' => $team->created_at,
                    'updated_at' => $team->updated_at,
                    'user_name' => $team->user_name, // User name
                    'rank' => $index + 1 // Assign rank based on the position in the collection
                ],
                'event_name' => $team->event_name, // Add the event name
            ];
        });

        // Return the list of teams for the specified user
        return response()->json([
            'status_code' => 1,
            'message' => 'Teams retrieved successfully.',
            'teams' => $teamsWithDetails,
        ]);
    }


    private function getPlayersByTeamData($team_id)
    {
        // Validate that the provided team_id exists in the team_players table
        $teamExists = TeamPlayer::where('team_id', $team_id)->exists();

        if (!$teamExists) {
            return [];
        }

        // Fetch players for the given team_id
        $teamPlayers = TeamPlayer::where('team_id', $team_id)
            ->join('match_players', 'team_players.match_player_id', '=', 'match_players.id')
            ->select('match_players.*') // Select all columns from the match_players table
            ->get();

        return $teamPlayers;
    }

    public function getPlayersByTeam($team_id)
    {
        // Validate that the provided team_id exists in the team_players table
        $teamExists = TeamPlayer::where('team_id', $team_id)->exists();

        if (!$teamExists) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Team not found or no players associated with this team.',
            ]);
        }

        // Fetch players for the given team_id
        $teamPlayers = TeamPlayer::where('team_id', $team_id)
            ->join('match_players', 'team_players.match_player_id', '=', 'match_players.id')
            ->select('match_players.*') // Select all columns from the match_players table
            ->get();

        // Define your API key
        // $apiKey = Helper::getApiKey();

        // // Fetch external player info for each player
        // $playersWithInfo = $teamPlayers->map(function ($player) use ($apiKey) {
        //     // Make the external API call to fetch player info using external_player_id
        //     $response = Http::get('https://api.cricapi.com/v1/players_info', [
        //         'apikey' => $apiKey,
        //         'id' => $player->external_player_id,
        //     ]);

        //     // Decode the API response
        //     $externalPlayerInfo = $response->json();

        //     // Add external player info to the player data
        //     $player->player_info = $externalPlayerInfo["data"];

        //     return $player;
        // });

        // Return the list of players with external info
        return response()->json([
            'status_code' => 1,
            'message' => 'Players retrieved successfully with external info.',
            'players' =>  $teamPlayers,
        ]);
    }

    public static function getEventsMatchesPlayersPoints($event_id)
    {
        // Fetch players from the match_players table for the given event_id
        $teamPlayers = MatchPlayer::where('event_id', $event_id)
            ->get([
                'id',
                'external_player_id',
                'name',
                'points', // Ensure this column exists in your database
                'role',
                'country',
                'team',
                'image_url',
            ]);

        // Return the data as a JSON response
        return response()->json([
            'status_code' => 1,
            'message' => 'Players retrieved successfully with external info.',
            'players' => $teamPlayers,
        ]);
    }


    // public function getEventMatchesScorecard($event_id)
    // {
    //     try {
    //         // Fetch all match IDs related to the given event ID from the EventMatch table
    //         $matchIds = EventMatch::where('event_id', $event_id)
    //             ->pluck('match_id')
    //             ->toArray();

    //         // If no matches are found for the event, return an empty response
    //         if (empty($matchIds)) {
    //             return response()->json([
    //                 'status_code' => 0,
    //                 'message' => 'No matches found for the event.',
    //                 'matches' => [],
    //             ]);
    //         }

    //         // Fetch match details (scores and status) from the Matches table
    //         $matches = Matches::whereIn('id', $matchIds)
    //             ->get(['id', 'external_match_id', 'scores', 'status']);

    //         // If no matches are found, return an empty response
    //         if ($matches->isEmpty()) {
    //             return response()->json([
    //                 'status_code' => 0,
    //                 'message' => 'No match data found for the given matches.',
    //                 'matches' => [],
    //             ]);
    //         }

    //         // Iterate over each match to ensure that 'scores' is in the correct format
    //         $matches = $matches->map(function ($match) {
    //             // Decode the scores from JSON to an array (if stored as JSON)
    //             $match->scores = json_decode($match->scores, true); // Decode to array

    //             // Ensure that the 'scores' key is an array, in case it's empty or malformed
    //             if (!is_array($match->scores)) {
    //                 $match->scores = [];
    //             }

    //             return $match;
    //         });

    //         // Return the match data with scores and status
    //         return response()->json([
    //             'status_code' => 1,
    //             'message' => 'Match scorecards retrieved successfully.',
    //             'matches' => $matches,
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Error fetching event match scorecards: ' . $e->getMessage());

    //         return response()->json([
    //             'status_code' => 0,
    //             'message' => 'Error fetching match scorecards.',
    //         ]);
    //     }
    // }
    public function getEventMatchesScorecard($event_id)
    {
        try {
            // Fetch all match IDs related to the given event ID from the EventMatch table
            $matchIds = EventMatch::where('event_id', $event_id)
                ->pluck('match_id')
                ->toArray();

            // If no matches are found for the event, return an empty response
            if (empty($matchIds)) {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'No matches found for the event.',
                    'matches' => [],
                ]);
            }

            // Fetch match details (scores and status) from the Matches table
            $matches = Matches::whereIn('id', $matchIds)
                ->get(['id', 'external_match_id', 'scores', 'status']);

            // If no matches are found, return an empty response
            if ($matches->isEmpty()) {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'No match data found for the given matches.',
                    'matches' => [],
                ]);
            }

            // Iterate over each match to ensure 'scores' is properly formatted and valid
            $matches = $matches->map(function ($match) {
                // Decode the scores from JSON to an array (if stored as JSON)
                $match->scores = json_decode($match->scores, true);

                // Ensure that the 'scores' key is an array, in case it's empty or malformed
                if (!is_array($match->scores)) {
                    $match->scores = null; // Set to null if malformed
                }

                return $match;
            });

            // Check if any match has scores set to null
            $hasNullScores = $matches->contains(function ($match) {
                return $match->scores === null || empty($match->scores);
            });

            if ($hasNullScores) {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Match scores are missing or unavailable.',
                ]);
            }

            // Return the match data with scores and status
            return response()->json([
                'status_code' => 1,
                'message' => 'Match scorecards retrieved successfully.',
                'matches' => $matches,
            ]);
        } catch (\Exception $e) {
            // Log::error('Error fetching event match scorecards: ' . $e->getMessage());

            return response()->json([
                'status_code' => 2,
                'message' => 'Error fetching match scorecards.',
            ]);
        }
    }

    public function getTeamsTransactionDetails($event_id)
    {
        $eventId = $event_id;
        $teams = Team::where('event_id', $eventId)
            ->whereHas('userTransaction') // Ensure the team has associated transactions
            ->orderByDesc('points_scored') // Sort by points_scored in descending order
            ->get()
            ->map(function ($team) {
                return [
                    'team_id' => $team->id,
                    'team_name' => $team->name,
                    'points_scored' => $team->points_scored,
                    'rank' => $team->rank, // Use rank from the table
                    'status' => $team->status,
                    'isMy' => $team->user_id === auth()->id(),
                ];
            });

        // Get team IDs for the transactions
        $teamIds = $teams->pluck('team_id');

        // Fetch user transactions related to the filtered teams
        $transactions = UserTransaction::whereIn('team_id', $teamIds)
            ->where('transaction_type', 'credit')
            ->get()
            ->groupBy('team_id') // Group transactions by team_id
            ->map(function ($transactionGroup) {
                return $transactionGroup->map(function ($transaction) {
                    return [
                        'transaction_id' => $transaction->id,
                        'user_id' => $transaction->user_id,
                        'amount' => $transaction->amount,
                        'transaction_type' => $transaction->transaction_type,
                        'description' => $transaction->description,
                    ];
                });
            });

        // Combine teams and transactions into the final response
        $response = $teams->map(function ($team) use ($transactions) {
            return [
                'team_id' => $team['team_id'],
                'team_name' => $team['team_name'],
                'points_scored' => $team['points_scored'],
                'rank' => $team['rank'],
                'isMy' => $team['isMy'],
                'status' => $team['status'],
                'transactions' => $transactions->get($team['team_id'], []), // Add transactions for the team
            ];
        });

        return response()->json([
            'status_code' => 1,
            'message' => 'Team and transaction details fetched successfully.',
            'data' => $response,
        ]);
    }
}
