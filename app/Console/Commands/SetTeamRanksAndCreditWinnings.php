<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Team;
use App\Models\Event;
use App\Models\EventPrize;
use App\Models\UserTransaction;
use App\Models\UserWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SetTeamRanksAndCreditWinnings extends Command
{
    protected $signature = 'events:set-ranks-credit-winnings';
    protected $description = 'Credit prizes to users for completed events';
    public function handle()
    {
        // Get the current date to find completed events


        // $completedEvents = Event::where('status', 'COMPLETED')
        //     ->where('is_winning_amount_transacted', false)// Adding the condition for activate_status
        //     ->get();

        $completedEvents = Event::where('status', 'COMPLETED')
            ->where('is_winning_amount_transacted', false)
            // ->whereIn('id', [296])
            ->get();


        foreach ($completedEvents as $event) {
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

            // Fetch event prizes based on rank
            $eventPrizes = EventPrize::where('event_id', $event->id)->get();

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
            $totalLimit = $this->getTotalOtherTypeLimit($event->id);
            // Now loop through each rank and handle prize distribution
            foreach ($rankedTeams as $rank => $teamIds) {
                // Get the prize for this rank
                $prize = $this->getPrize($eventPrizes, $rank);

                if ($prize) {
                    $teamsCount = count($teamIds);
                    $prizePerTeam = null; // Initialize to avoid undefined variable issues

                    if ($prize->type === 'top_rank') {
                        // Split the prize among all teams in the same rank
                        $prizePerTeam = $prize->prize_amount / $teamsCount;
                        foreach ($teamIds as $teamId) {
                            // Find the team to credit the user
                            $team = Team::find($teamId);
                            if ($team) {
                                $existingTransaction = UserTransaction::where('user_id', $team->user_id)
                                    ->where('team_id', $teamId)
                                    ->where('description', 'like', "Prize credited for event name: {$event->name}%")
                                    ->first();

                                if (!$existingTransaction) {
                                    // No previous transaction, create a new one
                                    UserTransaction::create([
                                        'user_id' => $team->user_id,
                                        'team_id' => $teamId,
                                        'amount' => $prizePerTeam, // Prize per team
                                        'status' => 'COMPLETED',
                                        'transaction_type' => 'credit',
                                        'description' => "Prize credited for event name: {$event->name}, Rank: {$rank}",
                                        'transaction_id' => Str::uuid(), // Generate a unique transaction ID
                                    ]);

                                    // Update the user's wallet balance
                                    $userWallet = UserWallet::firstOrCreate(['user_id' => $team->user_id]);
                                    $userWallet->balance += $prizePerTeam;
                                    $userWallet->save();

                                    $this->info("Team ID: {$teamId}, Event ID: {$event->id}, Rank: {$rank}, Prize: {$prizePerTeam} credited to user ID: {$team->user_id}");
                                } else {
                                    $this->info("Prize already credited for Team ID: {$teamId}, Event ID: {$event->id}, Rank: {$rank} for user ID: {$team->user_id}");
                                }
                            }
                        }
                    } elseif ($prize->type === 'other_rank') {

                        foreach ($teamIds as $teamId) {
                            if ($totalLimit <= 0) {
                                break; // Stop processing if limit is 0
                            }

                            $team = Team::find($teamId);
                            if ($team) {
                                $existingTransaction = UserTransaction::where('user_id', $team->user_id)
                                    ->where('team_id', $teamId)
                                    ->where('description', 'like', "Prize credited for event name: {$event->name}%")
                                    ->first();

                                if (!$existingTransaction) {
                                    // No previous transaction, create a new one
                                    UserTransaction::create([
                                        'user_id' => $team->user_id,
                                        'team_id' => $teamId,
                                        'amount' => $prize->prize_amount, // Assign full prize for each team
                                        'status' => 'COMPLETED',
                                        'transaction_type' => 'credit',
                                        'description' => "Prize credited for event name: {$event->name}, Rank: {$rank}",
                                        'transaction_id' => Str::uuid(), // Generate a unique transaction ID
                                    ]);

                                    // Update the user's wallet balance
                                    $userWallet = UserWallet::firstOrCreate(['user_id' => $team->user_id]);
                                    $userWallet->balance += $prize->prize_amount;
                                    $userWallet->save();

                                    $this->info("Team ID: {$teamId}, Event ID: {$event->id}, Rank: {$rank}, Prize: {$prize->prize_amount} credited to user ID: {$team->user_id}");

                                    // Decrease the totalLimit by 1 after successful transaction
                                    $totalLimit--;
                                } else {
                                    $this->info("Prize already credited for Team ID: {$teamId}, Event ID: {$event->id}, Rank: {$rank} for user ID: {$team->user_id}");
                                }
                            }
                        }

                        // Skip further processing for `other_rank` type as it's already handled
                        continue;
                    }
                }
            }


            // Update rank in the Team table
            foreach ($rankedTeams as $rankValue => $teamIds) {
                foreach ($teamIds as $teamId) {
                    Team::where('id', $teamId)->update(['rank' => $rankValue]); // Update rank for each team
                    $this->info("Updated Team ID: {$teamId} with Rank: {$rankValue}");
                }
            }
            // Mark the event as winning amount transacted
            $event->is_winning_amount_transacted = true;
            $event->save();

            $this->info("Winning amounts have been transacted for Event ID: {$event->id}");
        }

        $this->info('All completed event prizes have been credited and ranks updated.');
    }

    private function getPrize($eventPrizes, $rank)
    {
        foreach ($eventPrizes as $prize) {
            if ($rank >= $prize->rank_from && $rank <= $prize->rank_to) {
                return $prize;
            }
        }
        return null; // No prize for this rank
    }
    private function getTotalOtherTypeLimit($eventId)
    {
        $eventPrizes = EventPrize::where('event_id', $eventId)->get();
        $totalLimit = 0;
        foreach ($eventPrizes as $prize) {
            if ($prize->type === 'other_rank') {
                $totalLimit += ($prize->rank_to - $prize->rank_from + 1);
            }
        }
        return $totalLimit;
    }


    // public function handle()
    // {
    //     // Get the current date to find completed events
    //     $completedEvents = Event::where('status', 'COMPLETED')->where('id', 290)->get();

    //     foreach ($completedEvents as $event) {
    //         // Fetch all teams for the completed event
    //         $teams = Team::where('event_id', $event->id)->limit(100)->get();

    //         // Array to hold the teams and their points
    //         $teamPoints = [];

    //         foreach ($teams as $team) {
    //             // Assuming you have a points_scored attribute on the Team model
    //             $teamPoints[$team->id] = $team->points_scored; // Replace 'points_scored' with your actual attribute for points
    //         }

    //         // Sort teams by points in descending order
    //         arsort($teamPoints);

    //         // Fetch event prizes based on rank
    //         $eventPrizes = EventPrize::where('event_id', $event->id)->get();

    //         $rank = 0; // Initialize rank
    //         $lastPoints = null; // To handle ties in points
    //         $rankedTeams = []; // To store team IDs with their ranks

    //         foreach ($teamPoints as $teamId => $points) {
    //             // Increment rank only if we have a new score
    //             if ($points !== $lastPoints) {
    //                 $rank++; // Increment rank for new unique score
    //                 $lastPoints = $points; // Update lastPoints to current
    //             }

    //             // Assign rank to the team
    //             $rankedTeams[$rank][] = $teamId; // Store multiple teams with the same rank
    //         }

    //         // Now loop through each rank and handle prize distribution
    //         foreach ($rankedTeams as $rank => $teamIds) {
    //             // Get the prize for this rank
    //             $prize = $this->getPrize($eventPrizes, $rank);
    //             // $this->info("check point: " .  $prize ." " .$rank);
    //             if ($prize) {
    //                 $teamsCount = count($teamIds);

    //                 if ($prize->type === 'top_rank') {
    //                     // Split the prize among all teams in the same rank
    //                     $prizePerTeam = $prize->prize_amount / $teamsCount;
    //                 } else if ($prize->type === 'other_rank') {
    //                     // Assign the full prize amount to each team
    //                     $prizePerTeam = $prize->prize_amount;
    //                 }
    //                 $totalLimit = $prize->rank_to - $prize->rank_from + 1;
    //                 $this->info("check point: " .   $prizePerTeam . " ".$prize->type ." ". $totalLimit);

    //                 // foreach ($teamIds as $teamId) {
    //                 //     // Find the team to credit the user
    //                 //     $team = Team::find($teamId);
    //                 //     if ($team) {
    //                 //         // Check if a transaction for the same user, team, and event already exists
    //                 //         $existingTransaction = UserTransaction::where('user_id', $team->user_id)
    //                 //             ->where('team_id', $teamId)
    //                 //             ->where('description', 'like', "Prize credited for event name: {$event->name}%")
    //                 //             ->first();

    //                 //         if (!$existingTransaction) {
    //                 //             // No previous transaction, create a new one
    //                 //             UserTransaction::create([
    //                 //                 'user_id' => $team->user_id,
    //                 //                 'team_id' => $teamId,
    //                 //                 'amount' => $prizePerTeam, // Prize per team
    //                 //                 'status' => 'COMPLETED',
    //                 //                 'transaction_type' => 'credit',
    //                 //                 'description' => "Prize credited for event name: {$event->name}, Rank: {$rank}",
    //                 //                 'transaction_id' => Str::uuid(), // Generate a unique transaction ID
    //                 //             ]);

    //                 //             // Update the user's wallet balance
    //                 //             $userWallet = UserWallet::firstOrCreate(['user_id' => $team->user_id]);
    //                 //             $userWallet->balance += $prizePerTeam;
    //                 //             $userWallet->save();

    //                 //             $this->info("Team ID: {$teamId}, Event ID: {$event->id}, Rank: {$rank}, Prize: {$prizePerTeam} credited to user ID: {$team->user_id}");
    //                 //         } else {
    //                 //             $this->info("Prize already credited for Team ID: {$teamId}, Event ID: {$event->id}, Rank: {$rank} for user ID: {$team->user_id}");
    //                 //         }
    //                 //     }
    //                 // }
    //             }
    //         }

    //         // Update rank in the Team table
    //         foreach ($rankedTeams as $rankValue => $teamIds) {
    //             foreach ($teamIds as $teamId) {
    //                 Team::where('id', $teamId)->update(['rank' => $rankValue]); // Update rank for each team
    //                 $this->info("Updated Team ID: {$teamId} with Rank: {$rankValue}");
    //             }
    //         }
    //     }

    //     $this->info('All completed event prizes have been credited and ranks updated.');
    // }

    // private function getPrize($eventPrizes, $rank)
    // {
    //     foreach ($eventPrizes as $prize) {
    //         if ($rank >= $prize->rank_from && $rank <= $prize->rank_to) {
    //             return $prize;
    //         }
    //     }
    //     return null; // No prize for this rank
    // }


    // public function handle()
    // {
    //     // Get the current date to find completed events
    //     $completedEvents = Event::where('status', 'COMPLETED')->where('id',290)->get();

    //     foreach ($completedEvents as $event) {
    //         // Fetch all teams for the completed event
    //         $teams = Team::where('event_id', $event->id)->limit(100)->get();

    //         // Array to hold the teams and their points
    //         $teamPoints = [];

    //         foreach ($teams as $team) {
    //             // Assuming you have a points_scored attribute on the Team model
    //             $teamPoints[$team->id] = $team->points_scored; // Replace 'points_scored' with your actual attribute for points
    //         }

    //         // Sort teams by points in descending order
    //         arsort($teamPoints);

    //         // Fetch event prizes based on rank
    //         $eventPrizes = EventPrize::where('event_id', $event->id)->get();

    //         $rank = 0; // Initialize rank
    //         $lastPoints = null; // To handle ties in points
    //         $rankedTeams = []; // To store team IDs with their ranks

    //         foreach ($teamPoints as $teamId => $points) {
    //             // Increment rank only if we have a new score
    //             if ($points !== $lastPoints) {
    //                 $rank++; // Increment rank for new unique score
    //                 $lastPoints = $points; // Update lastPoints to current
    //             }

    //             // Assign rank to the team
    //             $rankedTeams[$rank][] = $teamId; // Store multiple teams with the same rank
    //         }

    //         // $this->info("check point: {$rankedTeams}");
    //         // $this->info("check point: " . print_r($rankedTeams, true));
    //         foreach ($rankedTeams as $rank => $teamIds) {
    //             $this->info("Rank {$rank}: " . implode(', ', $teamIds));
    //         }



    //         // Now loop through each rank and handle prize distribution
    //         foreach ($rankedTeams as $rank => $teamIds) {
    //             // Get the prize amount for this rank

    //             $prizeAmount = $this->getPrizeAmount($eventPrizes, $rank);
    //             $this->info("check point: " .  $prizeAmount);
    //             // return;
    //             if ($prizeAmount) {
    //                 // return;
    //                 // Calculate prize per team (if there are multiple teams sharing the same rank)
    //                 $teamsCount = count($teamIds);
    //                 $prizePerTeam = $prizeAmount / $teamsCount; // Divide prize equally among all teams with the same rank

    //                 foreach ($teamIds as $teamId) {
    //                     // Find the team to credit the user
    //                     $team = Team::find($teamId);
    //                     if ($team) {
    //                         // Check if a transaction for the same user, team, and event already exists
    //                         $existingTransaction = UserTransaction::where('user_id', $team->user_id)
    //                             ->where('team_id', $teamId)
    //                             ->where('description', 'like', "Prize credited for event name: {$event->name}%")
    //                             ->first();

    //                         if (!$existingTransaction) {
    //                             // No previous transaction, create a new one
    //                             UserTransaction::create([
    //                                 'user_id' => $team->user_id,
    //                                 'team_id' => $teamId,
    //                                 'amount' => $prizePerTeam, // Prize per team
    //                                 'status' => 'COMPLETED',
    //                                 'transaction_type' => 'credit',
    //                                 'description' => "Prize credited for event name: {$event->name}, Rank: {$rank}",
    //                                 'transaction_id' => Str::uuid(), // Generate a unique transaction ID
    //                             ]);

    //                             // Update the user's wallet balance
    //                             $userWallet = UserWallet::firstOrCreate(['user_id' => $team->user_id]);
    //                             $userWallet->balance += $prizePerTeam;
    //                             $userWallet->save();

    //                             $this->info("Team ID: {$teamId}, Event ID: {$event->id}, Rank: {$rank}, Prize: {$prizePerTeam} credited to user ID: {$team->user_id}");
    //                         } else {
    //                             $this->info("Prize already credited for Team ID: {$teamId}, Event ID: {$event->id}, Rank: {$rank} for user ID: {$team->user_id}");
    //                         }
    //                     }
    //                 }
    //             }
    //         }

    //         // Update rank in the Team table
    //         foreach ($rankedTeams as $rankValue => $teamIds) {
    //             foreach ($teamIds as $teamId) {
    //                 Team::where('id', $teamId)->update(['rank' => $rankValue]); // Update rank for each team
    //                 $this->info("Updated Team ID: {$teamId} with Rank: {$rankValue}");
    //             }
    //         }
    //     }

    //     $this->info('All completed event prizes have been credited and ranks updated.');
    // }


    // private function getPrizeAmount($eventPrizes, $rank)
    // {
    //     foreach ($eventPrizes as $prize) {
    //         if ($rank >= $prize->rank_from && $rank <= $prize->rank_to) {
    //             return $prize->prize_amount;
    //         }
    //     }
    //     return null; // No prize for this rank
    // }


}
