<?php

namespace App\Http\Controllers;

use App\Models\MyMindGame;
use App\Models\MyMindAttempt;
use App\Models\MyMindChallengeEntry;
use App\Services\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Http;
use App\Support\LinkUnavailable;

class MyMindController extends Controller
{
    private function normalizeChallengePhone(?string $phone): string
    {
        return preg_replace('/\D/', '', (string) $phone);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE GAME
    // POST /api/mymind/create
    // ─────────────────────────────────────────────────────────────────────────
    public function createGame(Request $request)
    {
        try {
            $validated = $request->validate([
                'creator_name'    => 'required|string|max:255',
                'creator_email'   => 'nullable|email|max:255',
                'category'        => 'required|string|in:aboutme,friends,besties,couples,coworkers,coquine',
                'questions_count' => 'required|integer|min:5|max:30',
                'final_amount'    => 'nullable|integer|min:0',
                'opening_message' => 'nullable|string|max:1000',
                'answers'         => 'required|array|min:5',
                'answers.*.question_id' => 'required|string',
                'answers.*.answer'      => 'required|string|max:500',
                'challenge_mode'        => 'sometimes|boolean',
                'challenge_intro'       => 'nullable|string|max:2000',
                'challenge_creator_entry' => 'nullable|integer|min:0',
                'challenge_min_bet'     => 'nullable|integer|min:0',
            ]);

            $challengeMode = ! empty($validated['challenge_mode']);
            if ($challengeMode) {
                $validated = array_merge($validated, $request->validate([
                    'challenge_intro' => 'nullable|string|max:2000',
                    'challenge_creator_entry' => 'required|integer|min:1',
                    'challenge_min_bet' => 'required|integer|min:1',
                ]));
                $intro = $request->input('challenge_intro');
                $validated['challenge_intro'] = ($intro !== null && trim((string) $intro) !== '') ? trim((string) $intro) : null;
                $validated['final_amount'] = (int) $validated['challenge_creator_entry'];
            } elseif ((int) ($validated['final_amount'] ?? 0) < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le montant à gagner est requis.',
                ], 422);
            }

            $uniqueLink = 'mm-' . Str::random(32);
            do {
                $accessCode = 'MM-' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
            } while (MyMindGame::where('access_code', $accessCode)->exists());

            // Extract ordered question IDs from answers
            $questionIds = array_column($validated['answers'], 'question_id');

            $game = MyMindGame::create([
                'creator_name'    => $validated['creator_name'],
                'creator_email'   => $validated['creator_email'] ?? null,
                'category'        => $validated['category'],
                'questions_count' => count($validated['answers']),
                'final_amount'    => $validated['final_amount'],
                'opening_message' => $validated['opening_message'] ?? null,
                'unique_link'     => $uniqueLink,
                'access_code'     => $accessCode,
                'answers'         => $validated['answers'],
                'question_ids'    => $questionIds,
                'status'          => 'active',
                'challenge_mode'  => $challengeMode,
                'challenge_intro' => $challengeMode ? ($validated['challenge_intro'] ?? null) : null,
                'challenge_creator_entry' => $challengeMode ? (int) $validated['challenge_creator_entry'] : 0,
                'challenge_min_bet' => $challengeMode ? (int) $validated['challenge_min_bet'] : 0,
                'challenge_pot' => $challengeMode ? (int) $validated['challenge_creator_entry'] : 0,
                'challenge_joins_count' => 0,
                'challenge_losers_count' => 0,
                'challenge_closed' => false,
            ]);

            return response()->json([
                'success' => true,
                'game' => [
                    'id'          => $game->id,
                    'unique_link' => $game->unique_link,
                    'access_code' => $game->access_code,
                    'creator_name'=> $game->creator_name,
                    'category'    => $game->category,
                    'questions_count' => $game->questions_count,
                    'final_amount'=> $game->final_amount,
                    'challenge_mode' => $challengeMode,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Données invalides.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('MyMind createGame error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur lors de la création du jeu.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET GAME (for partner) — no answers sent back
    // GET /api/mymind/{link}
    // ─────────────────────────────────────────────────────────────────────────
    public function getGame(Request $request, $link)
    {
        try {
            $game = MyMindGame::where('unique_link', $link)->first();

            if (! $game) {
                return LinkUnavailable::response(LinkUnavailable::DELETED, 404);
            }

            if ($game->challenge_mode) {
                if ($game->challenge_closed) {
                    return LinkUnavailable::response(LinkUnavailable::CLOSED, 410);
                }

                $phone = $this->normalizeChallengePhone($request->query('participant_phone'));
                if ($phone !== '') {
                    $alreadyFinished = MyMindChallengeEntry::where('mymind_game_id', $game->id)
                        ->where('participant_phone', $phone)
                        ->where('status', 'completed')
                        ->exists();
                    if ($alreadyFinished) {
                        return LinkUnavailable::response(LinkUnavailable::ALREADY_PLAYED, 410);
                    }
                }
                $canPlay = false;
                if ($phone !== '') {
                    $canPlay = MyMindChallengeEntry::where('mymind_game_id', $game->id)
                        ->where('participant_phone', $phone)
                        ->where('status', 'paid')
                        ->exists();
                }

                $paidCount = MyMindChallengeEntry::where('mymind_game_id', $game->id)->count();
                $finishedCount = MyMindChallengeEntry::where('mymind_game_id', $game->id)->where('status', 'completed')->count();

                $challengePayload = [
                    'intro' => $game->challenge_intro,
                    'pot' => (int) $game->challenge_pot,
                    'min_bet' => (int) $game->challenge_min_bet,
                    'creator_entry' => (int) $game->challenge_creator_entry,
                    'joins_count' => (int) $game->challenge_joins_count,
                    'losers_count' => (int) $game->challenge_losers_count,
                    'paid_participants' => $paidCount,
                    'finished_players' => $finishedCount,
                    'needs_payment' => ! $canPlay,
                ];

                if (! $canPlay) {
                    return response()->json([
                        'success' => true,
                        'game' => [
                            'id' => $game->id,
                            'creator_name' => $game->creator_name,
                            'category' => $game->category,
                            'questions_count' => $game->questions_count,
                            'final_amount' => $game->final_amount,
                            'opening_message' => $game->opening_message,
                            'question_ids' => [],
                            'challenge_mode' => true,
                        ],
                        'challenge' => $challengePayload,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'game' => [
                        'id' => $game->id,
                        'creator_name' => $game->creator_name,
                        'category' => $game->category,
                        'questions_count' => $game->questions_count,
                        'final_amount' => $game->final_amount,
                        'opening_message' => $game->opening_message,
                        'question_ids' => $game->question_ids,
                        'challenge_mode' => true,
                    ],
                    'challenge' => array_merge($challengePayload, ['needs_payment' => false]),
                ]);
            }

            if ($game->status === 'completed') {
                return LinkUnavailable::response(LinkUnavailable::ALREADY_PLAYED, 410);
            }
            if ($game->status === 'cancelled') {
                return LinkUnavailable::response(LinkUnavailable::CANCELLED, 410);
            }
            if ($game->status === 'expired') {
                return LinkUnavailable::response(LinkUnavailable::EXPIRED, 410);
            }
            if ($game->status !== 'active') {
                return LinkUnavailable::response(LinkUnavailable::CLOSED, 410);
            }

            return response()->json([
                'success' => true,
                'game' => [
                    'id'              => $game->id,
                    'creator_name'    => $game->creator_name,
                    'category'        => $game->category,
                    'questions_count' => $game->questions_count,
                    'final_amount'    => $game->final_amount,
                    'opening_message' => $game->opening_message,
                    'question_ids'    => $game->question_ids,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('MyMind getGame error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur lors du chargement du jeu.'], 500);
        }
    }

    public function joinChallenge(Request $request, $link)
    {
        $validated = $request->validate([
            'participant_phone' => 'required|string',
            'participant_name' => 'nullable|string|max:255',
            'stake_amount' => 'required|integer|min:1',
            'payment_reference' => 'required|string|max:255',
        ]);

        $game = MyMindGame::where('unique_link', $link)->first();
        if (! $game || ! $game->challenge_mode) {
            return LinkUnavailable::response(LinkUnavailable::DELETED, 404);
        }
        if ($game->challenge_closed) {
            return LinkUnavailable::response(LinkUnavailable::CLOSED, 410);
        }

        $phone = $this->normalizeChallengePhone($validated['participant_phone']);
        if ($phone === '') {
            return response()->json(['success' => false, 'message' => 'Numéro invalide.'], 422);
        }

        if ((int) $validated['stake_amount'] < (int) $game->challenge_min_bet) {
            return response()->json([
                'success' => false,
                'message' => 'Le montant est inférieur au minimum requis.',
                'min_bet' => (int) $game->challenge_min_bet,
            ], 422);
        }

        if (MyMindChallengeEntry::where('mymind_game_id', $game->id)->where('participant_phone', $phone)->exists()) {
            return response()->json(['success' => false, 'message' => 'Ce numéro a déjà rejoint le challenge.'], 409);
        }

        DB::transaction(function () use ($game, $phone, $validated) {
            MyMindChallengeEntry::create([
                'mymind_game_id' => $game->id,
                'participant_phone' => $phone,
                'participant_name' => $validated['participant_name'] ?? null,
                'stake_amount' => (int) $validated['stake_amount'],
                'payment_reference' => $validated['payment_reference'],
                'status' => 'paid',
            ]);
            $game->increment('challenge_joins_count');
        });

        $game->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Inscription au challenge confirmée.',
            'challenge' => [
                'pot' => (int) $game->challenge_pot,
                'joins_count' => (int) $game->challenge_joins_count,
            ],
        ]);
    }

    public function withdrawChallengePot(Request $request, $link)
    {
        $validated = $request->validate([
            'access_code' => 'required|string|max:32',
        ]);

        $game = MyMindGame::where('unique_link', $link)->first();
        if (! $game || ! $game->challenge_mode) {
            return response()->json(['success' => false, 'message' => 'Challenge introuvable.'], 404);
        }

        $inputCode = strtoupper(preg_replace('/\s+/', '', trim($validated['access_code'])));
        $storedCode = strtoupper(preg_replace('/\s+/', '', (string) $game->access_code));
        if ($inputCode !== $storedCode) {
            return response()->json(['success' => false, 'message' => 'Code d\'accès invalide.'], 403);
        }

        if ($game->challenge_closed) {
            return response()->json(['success' => false, 'message' => 'Challenge déjà clôturé.'], 400);
        }

        $amount = (int) $game->challenge_pot;
        if ($amount <= 0) {
            return response()->json(['success' => false, 'message' => 'Cagnotte vide.'], 400);
        }

        DB::transaction(function () use ($game) {
            $game->update([
                'challenge_pot' => 0,
                'challenge_closed' => true,
                'status' => 'completed',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Cagnotte retirée. Le jeu est clôturé.',
            'withdrawn_amount' => $amount,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SUBMIT ANSWERS (partner)
    // POST /api/mymind/{link}/submit
    // ─────────────────────────────────────────────────────────────────────────
    public function submitAnswers(Request $request, $link)
    {
        try {
            $game = MyMindGame::where('unique_link', $link)->first();

            if (! $game) {
                return LinkUnavailable::response(LinkUnavailable::DELETED, 404);
            }

            if ($game->challenge_mode && $game->challenge_closed) {
                return LinkUnavailable::response(LinkUnavailable::CLOSED, 410);
            }

            if (! $game->challenge_mode && $game->status === 'completed') {
                return LinkUnavailable::response(LinkUnavailable::ALREADY_PLAYED, 410);
            }
            if ($game->status === 'cancelled') {
                return LinkUnavailable::response(LinkUnavailable::CANCELLED, 410);
            }
            if ($game->status === 'expired') {
                return LinkUnavailable::response(LinkUnavailable::EXPIRED, 410);
            }
            if (! $game->challenge_mode && $game->status !== 'active') {
                return LinkUnavailable::response(LinkUnavailable::CLOSED, 410);
            }

            $validated = $request->validate([
                'answers'           => 'required|array|min:1',
                'answers.*.question_id' => 'required|string',
                'answers.*.answer'      => 'required|string|max:500',
                'partner_phone'     => 'nullable|string',
                'partner_operator'  => 'nullable|string',
            ]);

            $normPhone = PhoneNormalizer::normalizeCm($validated['partner_phone'] ?? '');
            $challengeEntry = null;
            if ($game->challenge_mode) {
                if ($normPhone === '') {
                    return response()->json(['success' => false, 'message' => 'Numéro requis pour le challenge.'], 422);
                }
                $challengeEntry = MyMindChallengeEntry::where('mymind_game_id', $game->id)
                    ->where('status', 'paid')
                    ->get()
                    ->first(fn ($e) => PhoneNormalizer::same($e->participant_phone, $normPhone));
                if (! $challengeEntry) {
                    return response()->json(['success' => false, 'message' => 'Payez votre mise pour jouer.'], 403);
                }
            } elseif ($normPhone === '') {
                return response()->json(['success' => false, 'message' => 'Numéro de téléphone requis pour identifier votre participation.'], 422);
            }

            // Compare answers
            $creatorMap = $game->getAnswerMap();
            $partnerAnswers = $validated['answers'];

            $details = [];
            $score   = 0;

            foreach ($partnerAnswers as $pa) {
                $qid           = $pa['question_id'];
                $partnerAnswer = mb_strtolower(trim($pa['answer']));
                $creatorAnswer = isset($creatorMap[$qid]) ? mb_strtolower(trim($creatorMap[$qid])) : null;

                $correct = ($creatorAnswer !== null && $partnerAnswer === $creatorAnswer);
                if ($correct) {
                    $score++;
                }

                $details[] = [
                    'question_id'    => $qid,
                    'creator_answer' => $creatorMap[$qid] ?? '',
                    'partner_answer' => $pa['answer'],
                    'correct'        => $correct,
                ];
            }

            $total = count($partnerAnswers);
            // Gagner avec au moins 70 % de bonnes réponses (arrondi côté entiers : score*100 >= 70*total)
            $won = ($total > 0 && ($score * 100) >= (70 * $total));

            $attempt = MyMindAttempt::create([
                'mymind_game_id'  => $game->id,
                'partner_phone'   => $normPhone !== '' ? $normPhone : null,
                'partner_operator'=> $validated['partner_operator'] ?? null,
                'answers'         => $validated['answers'],
                'score'           => $score,
                'total_questions' => $total,
                'won'             => $won,
                'won_amount'      => null,
            ]);

            $takeFromPot = 0;
            $payoutWon = 0;
            if ($game->challenge_mode) {
                $game->refresh();
                $potBefore = (int) $game->challenge_pot;
                $takeFromPot = $won ? $potBefore : 0;
                $payoutWon = $takeFromPot;

                DB::transaction(function () use ($game, $won, $challengeEntry, $attempt, $takeFromPot) {
                    $game->refresh();
                    if ($won) {
                        $dec = min($takeFromPot, max(0, (int) $game->challenge_pot));
                        if ($dec > 0) {
                            $game->decrement('challenge_pot', $dec);
                        }
                    } else {
                        $game->increment('challenge_pot', (int) $challengeEntry->stake_amount);
                        $game->increment('challenge_losers_count');
                    }
                    $challengeEntry->update([
                        'status' => 'completed',
                        'mymind_attempt_id' => $attempt->id,
                    ]);
                });
            } else {
                $payoutWon = $won ? (int) $game->final_amount : 0;
                $game->update(['status' => 'completed']);
            }

            $attempt->update(['won_amount' => $payoutWon]);

            return response()->json([
                'success' => true,
                'result' => [
                    'attempt_id'   => $attempt->id,
                    'score'        => $score,
                    'total'        => $total,
                    'won'          => $won,
                    'details'      => $details,
                    'final_amount' => $payoutWon,
                    'won_amount'   => $payoutWon,
                    'creator_name' => $game->creator_name,
                    'challenge_pot_after' => $game->fresh()->challenge_pot,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Données invalides.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('MyMind submitAnswers error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur lors de la soumission.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // WITHDRAW PRIZE
    // POST /api/mymind/withdraw-prize
    // ─────────────────────────────────────────────────────────────────────────
    public function withdrawPrize(Request $request)
    {
        try {
            $validated = $request->validate([
                'attempt_id' => 'required|integer',
                'phone' => 'required|string',
                'operator' => 'required|string|in:orange,mtn',
                'identity_phone' => 'required|string',
            ]);

            $payoutPhone = preg_replace('/[^\d]/', '', $validated['phone']);

            return DB::transaction(function () use ($validated, $payoutPhone) {
                $attempt = MyMindAttempt::with('game')->where('id', $validated['attempt_id'])->lockForUpdate()->first();

                if (! $attempt) {
                    return response()->json(['success' => false, 'message' => 'Tentative introuvable.'], 404);
                }

                if (! PhoneNormalizer::same($validated['identity_phone'], $attempt->partner_phone)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le numéro ne correspond pas à celui utilisé pour jouer.',
                    ], 403);
                }

                if (! $attempt->won) {
                    return response()->json(['success' => false, 'message' => 'Vous n\'avez pas gagné ce jeu.'], 403);
                }

                if ($attempt->prize_withdrawn) {
                    return response()->json(['success' => false, 'message' => 'Le gain a déjà été retiré.'], 400);
                }

                $game = $attempt->game;
                $amount = (int) ($attempt->won_amount ?? $game->final_amount);
                if ($amount < 1) {
                    return response()->json(['success' => false, 'message' => 'Montant de gain invalide.'], 400);
                }

                $disbursement = Http::timeout(60)->post(
                    config('app.payment_gateway_url', 'http://localhost:8000') . '/api/receive/money',
                    [
                        'amount' => $amount,
                        'phone' => $payoutPhone,
                        'operator' => $validated['operator'],
                        'reference' => 'mymind-' . $attempt->id,
                        'reason' => 'Gain MyMind — ' . $game->creator_name,
                    ]
                );

                if ($disbursement->successful() && ($disbursement->json('success') ?? false)) {
                    $attempt->update([
                        'prize_withdrawn' => true,
                        'payment_reference' => $disbursement->json('reference') ?? null,
                        'payout_phone' => $payoutPhone,
                        'payout_operator' => $validated['operator'],
                    ]);

                    return response()->json(['success' => true, 'message' => 'Gain retiré avec succès !']);
                }

                return response()->json(['success' => false, 'message' => 'Échec du retrait. Réessayez.'], 400);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('MyMind withdrawPrize error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur lors du retrait.'], 500);
        }
    }
}
