<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Moment;
use App\Models\MomentAttempt;
use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\ChallengeResult;
use App\Models\User;
use App\Http\Controllers\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\PhoneNormalizer;

class UserGamesController extends Controller
{
    /**
     * Récupérer tous les jeux créés par l'utilisateur (quiz, moments, challenges)
     */
    public function getCreatedGames(Request $request)
    {
        try {
            $email = $request->query('email');
            $phone = $request->query('phone');
            $normPhone = $phone ? PhoneNormalizer::normalizeCm($phone) : '';

            if (! $email && $normPhone === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Veuillez fournir une adresse email ou un numéro de téléphone',
                ], 400);
            }

            $games = [];

            // Quiz créés — email et/ou téléphone créateur (hors paiement)
            $quizzes = Quiz::with(['attempts' => function ($q) {
                $q->orderBy('created_at', 'desc');
            }])
                ->when($email && $normPhone !== '', function ($q) use ($email, $normPhone) {
                    $q->where(function ($w) use ($email, $normPhone) {
                        $w->whereRaw('LOWER(creator_email) = ?', [strtolower(trim($email))])
                            ->orWhere('creator_phone', $normPhone);
                    });
                })
                ->when($email && $normPhone === '', fn ($q) => $q->whereRaw('LOWER(creator_email) = ?', [strtolower(trim($email))]))
                ->when(! $email && $normPhone !== '', fn ($q) => $q->where('creator_phone', $normPhone))
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($quizzes as $quiz) {
                $hasWinner = $quiz->attempts->where('has_won', true)->count() > 0;
                $games[] = [
                    'id' => $quiz->id,
                    'type' => 'quiz',
                    'type_label' => 'Quiz',
                    'title' => 'Quiz de ' . $quiz->creator_name,
                    'creator_name' => $quiz->creator_name,
                    'unique_link' => $quiz->unique_link,
                    'amount' => $quiz->total_amount,
                    'status' => $quiz->status,
                    'has_winner' => $hasWinner,
                    'participants_count' => $quiz->attempts->count(),
                    'winner_name' => $quiz->attempts->where('has_won', true)->first()?->participant_name,
                    'can_withdraw' => $quiz->status === 'active' && !$hasWinner,
                    'created_at' => $quiz->created_at,
                ];
            }

            $moments = Moment::with(['attempts' => function ($q) {
                $q->orderBy('created_at', 'desc');
            }])
                ->when($email && $normPhone !== '', function ($q) use ($email, $normPhone) {
                    $q->where(function ($w) use ($email, $normPhone) {
                        $w->whereRaw('LOWER(creator_email) = ?', [strtolower(trim($email))])
                            ->orWhere('creator_phone', $normPhone);
                    });
                })
                ->when($email && $normPhone === '', fn ($q) => $q->whereRaw('LOWER(creator_email) = ?', [strtolower(trim($email))]))
                ->when(! $email && $normPhone !== '', fn ($q) => $q->where('creator_phone', $normPhone))
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($moments as $moment) {
                $hasWinner = $moment->attempts->where('has_won', true)->count() > 0;
                $games[] = [
                    'id' => $moment->id,
                    'type' => 'moment',
                    'type_label' => 'Moment',
                    'title' => 'Moment de ' . $moment->creator_name,
                    'creator_name' => $moment->creator_name,
                    'unique_link' => $moment->unique_link,
                    'amount' => $moment->amount,
                    'status' => $moment->status,
                    'has_winner' => $hasWinner,
                    'participants_count' => $moment->attempts->count(),
                    'winner_name' => $moment->attempts->where('has_won', true)->first()?->participant_name,
                    'can_withdraw' => $moment->status === 'active' && !$hasWinner,
                    'created_at' => $moment->created_at,
                ];
            }

            // Challenges créés
            if ($normPhone !== '') {
                $challenges = Challenge::with(['participants', 'results'])
                    ->whereHas('participants', function ($q) use ($normPhone) {
                        $q->where('phone', $normPhone)->where('role', 'creator');
                    })
                    ->orderBy('created_at', 'desc')
                    ->get();

                foreach ($challenges as $challenge) {
                    $creator = $challenge->participants->where('role', 'creator')->first();
                    $opponent = $challenge->participants->where('role', 'participant')->first();
                    $result = $challenge->results;
                    
                    $games[] = [
                        'id' => $challenge->id,
                        'type' => 'challenge',
                        'type_label' => 'À nous 2',
                        'title' => 'Challenge de ' . ($creator?->name ?? 'Inconnu'),
                        'creator_name' => $creator?->name,
                        'unique_link' => $challenge->unique_link,
                        'amount' => ($creator?->amount ?? 0) + ($opponent?->amount ?? 0),
                        'status' => $challenge->status,
                        'has_winner' => $result !== null,
                        'participants_count' => $challenge->participants->count(),
                        'winner_name' => $result ? ($result->winner_participant_id === $creator?->id ? $creator?->name : $opponent?->name) : null,
                        'can_withdraw' => $challenge->status === 'waiting' || ($challenge->status === 'selecting' && !$opponent),
                        'created_at' => $challenge->created_at,
                    ];
                }
            }

            // Trier par date
            usort($games, function ($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            $totalAmount = array_sum(array_column($games, 'amount'));
            $activeCount = count(array_filter($games, fn($g) => $g['status'] === 'active'));
            $completedCount = count(array_filter($games, fn($g) => $g['status'] === 'completed'));

            return response()->json([
                'success' => true,
                'games' => $games,
                'total' => count($games),
                'total_amount' => $totalAmount,
                'active_count' => $activeCount,
                'completed_count' => $completedCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les jeux auxquels l'utilisateur a participé
     */
    public function getParticipatedGames(Request $request)
    {
        try {
            $phone = $request->query('phone');
            $name = $request->query('name');

            if (!$phone && !$name) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veuillez fournir un numéro de téléphone ou un nom'
                ], 400);
            }

            $participations = [];

            // Participations aux quiz
            $quizAttempts = QuizAttempt::with('quiz')
                ->when($phone, fn($q) => $q->where('participant_phone', $phone))
                ->when($name, fn($q) => $q->orWhere('participant_name', 'like', '%' . $name . '%'))
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($quizAttempts as $attempt) {
                if (!$attempt->quiz) continue;
                
                $participations[] = [
                    'id' => $attempt->id,
                    'game_id' => $attempt->quiz->id,
                    'type' => 'quiz',
                    'type_label' => 'Quiz',
                    'title' => 'Quiz de ' . $attempt->quiz->creator_name,
                    'creator_name' => $attempt->quiz->creator_name,
                    'participant_name' => $attempt->participant_name,
                    'amount' => $attempt->quiz->total_amount,
                    'won_amount' => $attempt->won_amount ?? 0,
                    'has_won' => (bool) $attempt->has_won,
                    'score' => $attempt->score ?? ($attempt->correct_answers . '/' . $attempt->total_questions),
                    'status' => $attempt->status,
                    'withdrawal_code' => $attempt->has_won ? 'WIN-Q-' . $attempt->id : null,
                    'can_withdraw' => $attempt->has_won && $attempt->status !== 'withdrawn',
                    'created_at' => $attempt->created_at,
                ];
            }

            // Participations aux moments
            $momentAttempts = MomentAttempt::with('moment')
                ->when($phone, fn($q) => $q->where('participant_phone', $phone))
                ->when($name, fn($q) => $q->orWhere('participant_name', 'like', '%' . $name . '%'))
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($momentAttempts as $attempt) {
                if (!$attempt->moment) continue;
                
                $participations[] = [
                    'id' => $attempt->id,
                    'game_id' => $attempt->moment->id,
                    'type' => 'moment',
                    'type_label' => 'Moment',
                    'title' => 'Moment de ' . $attempt->moment->creator_name,
                    'creator_name' => $attempt->moment->creator_name,
                    'participant_name' => $attempt->participant_name,
                    'amount' => $attempt->moment->amount,
                    'won_amount' => $attempt->won_amount ?? 0,
                    'has_won' => (bool) $attempt->has_won,
                    'score' => $attempt->has_won ? 'Trouvé !' : 'Raté',
                    'status' => $attempt->status,
                    'withdrawal_code' => $attempt->has_won ? 'WIN-M-' . $attempt->id : null,
                    'can_withdraw' => $attempt->has_won && $attempt->status !== 'withdrawn',
                    'created_at' => $attempt->created_at,
                ];
            }

            // Participations aux challenges
            if ($phone) {
                $challengeParticipations = ChallengeParticipant::with(['challenge.results', 'challenge.participants'])
                    ->where('phone', $phone)
                    ->orderBy('created_at', 'desc')
                    ->get();

                foreach ($challengeParticipations as $participation) {
                    if (!$participation->challenge) continue;
                    
                    $challenge = $participation->challenge;
                    $result = $challenge->results;
                    $totalPot = $challenge->participants->sum('amount');
                    $hasWon = $result && $result->winner_participant_id === $participation->id;
                    
                    $participations[] = [
                        'id' => $participation->id,
                        'game_id' => $challenge->id,
                        'type' => 'challenge',
                        'type_label' => 'À nous 2',
                        'title' => 'Challenge',
                        'creator_name' => $challenge->participants->where('role', 'creator')->first()?->name,
                        'participant_name' => $participation->name,
                        'amount' => $totalPot,
                        'won_amount' => $hasWon ? $result->winner_amount : 0,
                        'has_won' => $hasWon,
                        'score' => $result ? ($hasWon ? 'Gagné !' : 'Perdu') : 'En cours',
                        'status' => $challenge->status,
                        'withdrawal_code' => $hasWon ? 'WIN-C-' . $result->id : null,
                        'can_withdraw' => $hasWon && $result->status !== 'withdrawn',
                        'created_at' => $participation->created_at,
                    ];
                }
            }

            // Trier par date
            usort($participations, function ($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            $totalWon = array_sum(array_column(array_filter($participations, fn($p) => $p['has_won']), 'won_amount'));
            $wonCount = count(array_filter($participations, fn($p) => $p['has_won']));
            $lostCount = count(array_filter($participations, fn($p) => !$p['has_won'] && $p['status'] === 'completed'));

            return response()->json([
                'success' => true,
                'participations' => $participations,
                'total' => count($participations),
                'total_won' => $totalWon,
                'won_count' => $wonCount,
                'lost_count' => $lostCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les détails des participants d'un jeu créé
     */
    public function getGameParticipants(Request $request, $type, $id)
    {
        try {
            $participants = [];

            switch ($type) {
                case 'quiz':
                    $quiz = Quiz::with('attempts')->find($id);
                    if (!$quiz) {
                        return response()->json(['success' => false, 'message' => 'Quiz non trouvé'], 404);
                    }
                    
                    foreach ($quiz->attempts as $attempt) {
                        $participants[] = [
                            'id' => $attempt->id,
                            'name' => $attempt->participant_name,
                            'phone' => $attempt->participant_phone,
                            'score' => $attempt->correct_answers . '/' . $attempt->total_questions,
                            'percentage' => round(($attempt->correct_answers / $attempt->total_questions) * 100),
                            'has_won' => (bool) $attempt->has_won,
                            'won_amount' => $attempt->won_amount ?? 0,
                            'created_at' => $attempt->created_at,
                        ];
                    }
                    break;

                case 'moment':
                    $moment = Moment::with('attempts')->find($id);
                    if (!$moment) {
                        return response()->json(['success' => false, 'message' => 'Moment non trouvé'], 404);
                    }
                    
                    foreach ($moment->attempts as $attempt) {
                        $participants[] = [
                            'id' => $attempt->id,
                            'name' => $attempt->participant_name,
                            'phone' => $attempt->participant_phone,
                            'selected_order' => $attempt->selected_moment_order,
                            'correct_order' => $moment->best_moment_order,
                            'has_won' => (bool) $attempt->has_won,
                            'won_amount' => $attempt->won_amount ?? 0,
                            'created_at' => $attempt->created_at,
                        ];
                    }
                    break;

                case 'challenge':
                    $challenge = Challenge::with(['participants', 'results'])->find($id);
                    if (!$challenge) {
                        return response()->json(['success' => false, 'message' => 'Challenge non trouvé'], 404);
                    }
                    
                    foreach ($challenge->participants as $participant) {
                        $isWinner = $challenge->results && $challenge->results->winner_participant_id === $participant->id;
                        $participants[] = [
                            'id' => $participant->id,
                            'name' => $participant->name,
                            'phone' => $participant->phone,
                            'role' => $participant->role,
                            'amount' => $participant->amount,
                            'has_selected_questions' => (bool) $participant->has_selected_questions,
                            'has_answered' => (bool) $participant->has_answered,
                            'has_won' => $isWinner,
                            'won_amount' => $isWinner ? $challenge->results->winner_amount : 0,
                            'created_at' => $participant->created_at,
                        ];
                    }
                    break;

                default:
                    return response()->json(['success' => false, 'message' => 'Type de jeu invalide'], 400);
            }

            return response()->json([
                'success' => true,
                'participants' => $participants,
                'total' => count($participants),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Annuler un jeu et récupérer l'argent dans le wallet (si personne n'a encore gagné)
     */
    public function cancelGame(Request $request, $type, $id)
    {
        try {
            DB::beginTransaction();

            $user = null;
            $amount = 0;
            $sourceRef = '';

            switch ($type) {
                case 'quiz':
                    $quiz = Quiz::with('attempts')->find($id);
                    if (!$quiz) {
                        return response()->json(['success' => false, 'message' => 'Quiz non trouvé'], 404);
                    }
                    
                    if ($quiz->status === 'cancelled') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Ce quiz a déjà été annulé'
                        ], 400);
                    }
                    
                    if ($quiz->attempts->where('has_won', true)->count() > 0) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Ce quiz a déjà un gagnant et ne peut pas être annulé'
                        ], 400);
                    }
                    
                    // Trouver l'utilisateur par email
                    if ($quiz->creator_email) {
                        $user = User::where('email', strtolower($quiz->creator_email))->first();
                    }
                    
                    if (!$user) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Utilisateur non trouvé. Impossible de rembourser le montant.'
                        ], 404);
                    }
                    
                    $quiz->update(['status' => 'cancelled']);
                    $amount = $quiz->total_amount;
                    $sourceRef = $quiz->unique_link;
                    break;

                case 'moment':
                    $moment = Moment::with('attempts')->find($id);
                    if (!$moment) {
                        return response()->json(['success' => false, 'message' => 'Moment non trouvé'], 404);
                    }
                    
                    if ($moment->status === 'cancelled') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Ce moment a déjà été annulé'
                        ], 400);
                    }
                    
                    if ($moment->attempts->where('has_won', true)->count() > 0) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Ce moment a déjà un gagnant et ne peut pas être annulé'
                        ], 400);
                    }
                    
                    // Trouver l'utilisateur par email
                    if ($moment->creator_email) {
                        $user = User::where('email', strtolower($moment->creator_email))->first();
                    }
                    
                    if (!$user) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Utilisateur non trouvé. Impossible de rembourser le montant.'
                        ], 404);
                    }
                    
                    $moment->update(['status' => 'cancelled']);
                    $amount = $moment->amount;
                    $sourceRef = $moment->unique_link;
                    break;

                case 'challenge':
                    $challenge = Challenge::with(['results', 'participants'])->find($id);
                    if (!$challenge) {
                        return response()->json(['success' => false, 'message' => 'Challenge non trouvé'], 404);
                    }
                    
                    if ($challenge->status === 'cancelled') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Ce challenge a déjà été annulé'
                        ], 400);
                    }
                    
                    if ($challenge->results) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Ce challenge est terminé et ne peut pas être annulé'
                        ], 400);
                    }
                    
                    // Pour les challenges, on rembourse au créateur (premier participant avec role='creator')
                    $creator = $challenge->participants->where('role', 'creator')->first();
                    if ($creator && $creator->phone) {
                        $user = User::where('phone', $creator->phone)->first();
                    }
                    
                    if (!$user) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Utilisateur non trouvé. Impossible de rembourser le montant.'
                        ], 404);
                    }
                    
                    $challenge->update(['status' => 'cancelled']);
                    $amount = $challenge->participants->sum('amount');
                    $sourceRef = $challenge->unique_link;
                    break;

                default:
                    return response()->json(['success' => false, 'message' => 'Type de jeu invalide'], 400);
            }

            // Ajouter l'argent au wallet
            WalletController::addToWallet(
                $user->id,
                $amount,
                $type,
                $id,
                $sourceRef,
                'Remboursement - Annulation de ' . $type . ' ' . $sourceRef
            );

            DB::commit();

            // Récupérer le nouveau solde
            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => ucfirst($type) . ' annulé avec succès. Le montant de ' . number_format($amount) . ' XAF a été ajouté à votre wallet.',
                'amount' => $amount,
                'new_balance' => $user->balance
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marquer un gain comme retiré
     */
    public function markWithdrawn(Request $request, $type, $id)
    {
        try {
            switch ($type) {
                case 'quiz':
                    $attempt = QuizAttempt::find($id);
                    if (!$attempt || !$attempt->has_won) {
                        return response()->json(['success' => false, 'message' => 'Tentative non trouvée ou non gagnante'], 404);
                    }
                    $attempt->update(['status' => 'withdrawn']);
                    $amount = $attempt->won_amount;
                    break;

                case 'moment':
                    $attempt = MomentAttempt::find($id);
                    if (!$attempt || !$attempt->has_won) {
                        return response()->json(['success' => false, 'message' => 'Tentative non trouvée ou non gagnante'], 404);
                    }
                    $attempt->update(['status' => 'withdrawn']);
                    $amount = $attempt->won_amount;
                    break;

                case 'challenge':
                    $result = ChallengeResult::find($id);
                    if (!$result) {
                        return response()->json(['success' => false, 'message' => 'Résultat non trouvé'], 404);
                    }
                    $result->update(['status' => 'withdrawn']);
                    $amount = $result->winner_amount;
                    break;

                default:
                    return response()->json(['success' => false, 'message' => 'Type de jeu invalide'], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Retrait de ' . number_format($amount) . ' XAF effectué avec succès.',
                'amount' => $amount
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
}

