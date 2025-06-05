<?php

namespace App\Console\Commands;

use App\Helpers\Helper;
use Illuminate\Console\Command;
use App\Models\Event;
use App\Models\EventMatch;
use App\Models\Matches;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UpdateEventStatus extends Command
{
    // Define the command signature and description
    protected $signature = 'events:update-status';
    protected $description = 'Update the status of events based on match statuses';

    public function __construct()
    {
        parent::__construct();
    }
    public function handle()
    {
        Log::info('Update Event Status job started.');

        $currentDateTime = Carbon::now();

        // Fetch only "UPCOMING" and "LIVE" events
        $events = Event::whereIn('status', ['CREATED', 'UPCOMING', 'LIVE'])->get();

        foreach ($events as $event) {
            // Additional check for "UPCOMING" events
            if ($event->status === 'UPCOMING') {
                // Fetch the earliest match's date_time for the event
                $firstMatchDateTime = Matches::whereIn('id', function ($query) use ($event) {
                    $query->select('match_id')
                        ->from('event_matches')
                        ->where('event_id', $event->id);
                })->orderBy('date_time', 'asc')->value('date_time');

                if ($firstMatchDateTime) {
                    $timeDifference = $currentDateTime->diffInHours(Carbon::parse($firstMatchDateTime), false);
                    // Skip event if the first match is not within 5 hours
                    if ($timeDifference > 5 || $timeDifference < 0) {
                        Log::info("Skipping event ID {$event->id}: First match is not within 5 hours.");
                        continue;
                    }
                } else {
                    Log::info("Skipping event ID {$event->id}: No matches found.");
                    continue;
                }
            }

            // Fetch match IDs related to the event
            $matchDateTimes = Matches::whereIn('id', function ($query) use ($event) {
                $query->select('match_id')
                    ->from('event_matches')
                    ->where('event_id', $event->id);
            })->pluck('external_match_id');

            $matchStarted = false;
            $allMatchesEnded = true;

            foreach ($matchDateTimes as $externalMatchId) {
                $status = $this->fetchMatchStatus($externalMatchId);

                if ($status['matchStarted']) {
                    $matchStarted = true;
                }
                if (!$status['matchEnded']) {
                    $allMatchesEnded = false;
                }
            }

            // Determine event status
            $eventStatus = 'UPCOMING'; // Default status
            if ($matchStarted && $allMatchesEnded) {
                $eventStatus = 'COMPLETED';
            } elseif ($matchStarted) {
                $eventStatus = 'LIVE';
            } elseif ($event->go_live_date < $currentDateTime->toDateString() && $allMatchesEnded) {
                $eventStatus = 'COMPLETED';
            }

            // Update event status in the database if it has changed
            if ($event->status !== $eventStatus) {
                $event->status = $eventStatus;
                $event->save();
                Log::info("Event ID {$event->id}: Status updated to {$eventStatus}");
                if ($eventStatus === 'COMPLETED') {
                    $this->getEventMatchesScorecard($event->id);
                }
            }
            // Always update the scorecard for LIVE events
            if ($eventStatus === 'LIVE') {
                Log::info("Updating live scorecard for Event ID {$event->id}.");
                $this->getEventMatchesScorecard($event->id);
            }
        }

        Log::info('Event statuses updated successfully!');
    }
    private function getEventMatchesScorecard($event_id)
    {

        // Log::info("Fetching scorecards for event ID {$event_id}");

        // Fetch match IDs for the event
        $matches = Matches::whereIn('id', function ($query) use ($event_id) {
            $query->select('match_id')
                ->from('event_matches')
                ->where('event_id', $event_id);
        })->get(['id', 'external_match_id']);

        foreach ($matches as $match) {
            $externalMatchId = $match->external_match_id;

            if ($externalMatchId) {
                $apiKey = Helper::getApiKey();
                $apiUrl = "https://api.cricapi.com/v1/match_info?apikey={$apiKey}&id={$externalMatchId}";
                $response = file_get_contents($apiUrl);

                if ($response) {
                    $matchInfo = json_decode($response, true);

                    if (isset($matchInfo['data'])) {


                        $data = $matchInfo['data'];

                        $scores = $data['score'] ?? null;
                        $status = $data['status'] ?? null;
                        // $this->info("Match ID: " . json_encode($matchInfo['data'], JSON_PRETTY_PRINT));

                        // Update the scores and status in the matches table
                        // $match->update([
                        //     'scores' => $scores,
                        //     'status' => $status,
                        // ]);
                        $match = Matches::find($match->id);

                        if ($match) {
                            $match->scores = json_encode($scores); // Convert PHP array to JSON before storing
                            $match->status = $status; // Assuming $status is also being updated
                            $match->save();
                        }

                        Log::info("Match ID {$match->id}: Scores and status updated.");
                    }
                } else {
                    Log::error("Failed to fetch match info for external match ID {$externalMatchId}");
                }
            }
        }
    }


    // public function handle()
    // {
    //     // Get all events
    //     Log::info('update event status job started.');
    //     $events = Event::all();
    //     $currentDateTime = Carbon::now();

    //     foreach ($events as $event) {
    //         // Fetch team counts


    //         // Fetch match IDs related to the event
    //         $matchDateTimes = Matches::whereIn('id', function ($query) use ($event) {
    //             $query->select('match_id')
    //                 ->from('event_matches')
    //                 ->where('event_id', $event->id);
    //         })->pluck('external_match_id');

    //         $matchStarted = false;
    //         $allMatchesEnded = true;

    //         foreach ($matchDateTimes as $externalMatchId) {
    //             $status = $this->fetchMatchStatus($externalMatchId);
    //             if ($status['matchStarted']) {
    //                 $matchStarted = true;
    //             }
    //             if (!$status['matchEnded']) {
    //                 $allMatchesEnded = false;
    //             }
    //         }

    //         // Determine event status
    //         $eventStatus = 'UPCOMING'; // Default status
    //         if ($matchStarted && $allMatchesEnded) {
    //             $eventStatus = 'COMPLETED';
    //         } elseif ($matchStarted) {
    //             $eventStatus = 'LIVE';
    //         } elseif ($event->go_live_date < $currentDateTime->toDateString() && $allMatchesEnded) {
    //             $eventStatus = 'COMPLETED';
    //         }

    //         // Update event status in the database
    //         $event->status = $eventStatus;
    //         $event->save();
    //     }

    //     $this->info('Event statuses updated successfully!');
    // }

    private function fetchMatchStatus($externalMatchId)
    {
        // Use your logic to fetch match status from external API
        $apiKey = Helper::getApiKey();
        $apiUrl = "https://api.cricapi.com/v1/match_info?apikey={$apiKey}&id={$externalMatchId}";
        $response = file_get_contents($apiUrl);

        if ($response === false) {
            return ['matchStarted' => false, 'matchEnded' => true];
        }

        $status = json_decode($response, true);

        if (isset($status['data'])) {
            return [
                'matchStarted' => $status['data']['matchStarted'] ?? false,
                'matchEnded' => $status['data']['matchEnded'] ?? true
            ];
        }

        return ['matchStarted' => false, 'matchEnded' => true];
    }
}
