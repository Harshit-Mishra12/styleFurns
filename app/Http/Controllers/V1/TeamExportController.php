<?php

namespace App\Http\Controllers\V1;


use App\Http\Controllers\Controller;
use App\Models\Team; // Assuming Team model is located here
use Illuminate\Http\Request;


class TeamExportController extends Controller
{
    public function exportToCsv($event_id)
    {
        // Fetch teams for the given event ID
        $teams = Team::where('event_id', $event_id)->get();

        if ($teams->isEmpty()) {
            return response()->json(['status_code' => 2, 'message' => 'No teams found for this event.']);
        }

        // Prepare the CSV file
        $filename = 'teams_export_' . date('Ymd_His') . '.csv';
        $handle = fopen('php://output', 'w');

        // Set the headers for the CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Add the CSV column headings
        fputcsv($handle, ['Team Name', 'User Name', 'Points Scored', 'Player Names']);

        // Loop through each team and gather data for the CSV
        foreach ($teams as $team) {
            // Fetch players for this team from the TeamPlayer table (assuming a relationship is defined)
            $teamPlayers = $team->players;  // Assuming 'teamPlayers' is the relationship in Team model

            // Get all player names for this team
            $playerNames = [];
            foreach ($teamPlayers as $teamPlayer) {
                // Assuming the 'teamPlayer' has a relationship with 'matchPlayer' to get player details
                $playerNames[] = $teamPlayer->matchPlayer->name ?? 'Unknown Player';  // Replace with actual field name
            }

            // Join all player names as a single string
            $playersList = implode(', ', $playerNames);

            // Add team data to the CSV
            fputcsv($handle, [
                $team->name,
                $team->user->name ?? 'N/A',  // Assuming you have a relationship set up to get the user
                $team->points_scored ?? '0',  // Assuming points_scored is a field in the Team model
                $playersList
            ]);
        }

        fclose($handle);
        exit;
    }

    // public function exportToCsv($event_id)
    // {
    //     // Validate the request to ensure an event ID is provided


    //     // Fetch teams for the given event ID
    //     $teams = Team::where('event_id', $event_id)->get();

    //     if ($teams->isEmpty()) {
    //         return response()->json(['status_code' => 2, 'message' => 'No teams found for this event.']);
    //     }

    //     // Prepare the CSV file
    //     $filename = 'teams_export_' . date('Ymd_His') . '.csv';
    //     $handle = fopen('php://output', 'w');

    //     // Set the headers for the CSV download
    //     header('Content-Type: text/csv');
    //     header('Content-Disposition: attachment; filename="' . $filename . '"');
    //     header('Pragma: no-cache');
    //     header('Expires: 0');

    //     // Add the CSV column headings
    //     fputcsv($handle, ['Team Name', 'User Name', 'Points Scored']);

    //     // Add team data to the CSV
    //     foreach ($teams as $team) {
    //         fputcsv($handle, [
    //             $team->name,
    //             $team->user->name ?? 'N/A', // Assuming you have a relationship set up
    //             $team->points_scored, // Assuming points scored is a field in the Team model
    //         ]);
    //     }

    //     fclose($handle);
    //     exit; // Make sure to exit after outputting the CSV
    // }

}
