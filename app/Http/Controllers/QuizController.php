<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizAllowedParticipant;
use App\Models\QuizChallengeEntry;
use Illuminate\Support\Facades\DB;
use App\Helpers\CodeGenerator;
use App\Helpers\EyamoUserResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Support\LinkUnavailable;

class QuizController extends Controller
{
    private function normalizeChallengePhone(?string $phone): string
    {
        return preg_replace('/\D/', '', (string) $phone);
    }

    /**
     * Créer un nouveau quiz
     */
    public function createQuiz(Request $request)
    {
        try {
            $validated = $request->validate([
                'creator_name' => 'required|string|max:255',
                'creator_email' => 'nullable|email|max:255',
                'total_questions' => 'required|integer|in:5,10,15,20,50,100',
                'required_correct' => 'required|integer|min:1',
                'final_amount' => 'nullable|integer|min:0',
                'access_type' => 'required|string|in:everyone,single,multiple',
                'single_participant_phone' => 'nullable|string|required_if:access_type,single',
                'allowed_phones' => 'nullable|array|required_if:access_type,multiple',
                'allowed_phones.*' => 'string',
                'opening_message' => 'nullable|string|max:1000',
                'questions' => 'required|array|min:1',
                'questions.*.question' => 'required|string',
                'questions.*.answers' => 'required|array|min:2',
                'questions.*.answers.*.answer' => 'required|string',
                'questions.*.answers.*.is_correct' => 'required|boolean',
                'challenge_mode' => 'sometimes|boolean',
                'challenge_intro' => 'nullable|string|max:2000',
                'challenge_creator_entry' => 'nullable|integer|min:0',
                'challenge_min_bet' => 'nullable|integer|min:0',
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

            // Vérifier que required_correct est inférieur au total_questions
            if ($validated['required_correct'] >= $validated['total_questions']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le nombre de questions à trouver doit être inférieur au nombre total de questions'
                ], 400);
            }

            // Vérifier que le nombre de questions correspond
            if (count($validated['questions']) !== $validated['total_questions']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le nombre de questions fourni ne correspond pas au nombre total de questions'
                ], 400);
            }

            // Générer un lien unique
            $uniqueLink = 'quiz-' . Str::random(32);

            // Créer le quiz
            $quiz = Quiz::create([
                'creator_name' => $validated['creator_name'],
                'creator_email' => $validated['creator_email'] ?? null,
                'unique_link' => $uniqueLink,
                'access_code' => CodeGenerator::generateAccessCode('quiz'),
                'total_questions' => $validated['total_questions'],
                'required_correct' => $validated['required_correct'],
                'total_amount' => $validated['final_amount'],
                'access_type' => $challengeMode ? 'everyone' : $validated['access_type'],
                'single_participant_phone' => $challengeMode ? null : ($validated['access_type'] === 'single' ? ($validated['single_participant_phone'] ?? null) : null),
                'opening_message' => $validated['opening_message'] ?? null,
                'status' => 'active',
                'challenge_mode' => $challengeMode,
                'challenge_intro' => $challengeMode ? ($validated['challenge_intro'] ?? null) : null,
                'challenge_creator_entry' => $challengeMode ? (int) $validated['challenge_creator_entry'] : 0,
                'challenge_min_bet' => $challengeMode ? (int) $validated['challenge_min_bet'] : 0,
                'challenge_pot' => $challengeMode ? (int) $validated['challenge_creator_entry'] : 0,
                'challenge_joins_count' => 0,
                'challenge_losers_count' => 0,
                'challenge_closed' => false,
            ]);

            // Créer les participants autorisés si access_type = multiple (hors mode challenge)
            if (! $challengeMode && $validated['access_type'] === 'multiple' && !empty($validated['allowed_phones'])) {
                foreach ($validated['allowed_phones'] as $phone) {
                    if (!empty($phone)) {
                        QuizAllowedParticipant::create([
                            'quiz_id' => $quiz->id,
                            'phone_number' => $phone
                        ]);
                    }
                }
            }

            // Créer les questions et réponses
            foreach ($validated['questions'] as $index => $questionData) {
                $question = QuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'question' => $questionData['question'],
                    'question_order' => $index + 1
                ]);

                foreach ($questionData['answers'] as $answerIndex => $answerData) {
                    QuizAnswer::create([
                        'quiz_question_id' => $question->id,
                        'answer' => $answerData['answer'],
                        'is_correct' => $answerData['is_correct'],
                        'answer_order' => $answerIndex + 1
                    ]);
                }
            }

            // Utiliser l'URL du frontend depuis la requête ou une valeur par défaut
            $frontendUrl = $request->header('Origin') ?: 'http://localhost:3000';
            $shareLink = rtrim($frontendUrl, '/') . '/quiz/' . $uniqueLink;

            return response()->json([
                'success' => true,
                'quiz' => $quiz->load(['questions.answers']),
                'share_link' => $shareLink
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création du quiz: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du quiz: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer un quiz par son lien unique
     */
    public function getQuiz(Request $request, $link)
    {
        $quiz = Quiz::where('unique_link', $link)
            ->with([
                'questions' => function ($query) {
                    $query->orderBy('question_order');
                },
                'questions.answers' => function ($query) {
                    $query->orderBy('answer_order');
                },
            ])
            ->first();

        if (! $quiz) {
            return LinkUnavailable::response(LinkUnavailable::DELETED, 404);
        }

        if ($quiz->challenge_mode) {
            if ($quiz->challenge_closed) {
                return LinkUnavailable::response(LinkUnavailable::CLOSED, 410);
            }

            $phone = $this->normalizeChallengePhone($request->query('participant_phone'));
            $canPlay = false;
            if ($phone !== '') {
                $canPlay = QuizChallengeEntry::where('quiz_id', $quiz->id)
                    ->where('participant_phone', $phone)
                    ->where('status', 'paid')
                    ->exists();
            }

            $paidCount = QuizChallengeEntry::where('quiz_id', $quiz->id)->count();
            $finishedCount = QuizChallengeEntry::where('quiz_id', $quiz->id)->where('status', 'completed')->count();

            $challengePayload = [
                'intro' => $quiz->challenge_intro,
                'pot' => (int) $quiz->challenge_pot,
                'min_bet' => (int) $quiz->challenge_min_bet,
                'creator_entry' => (int) $quiz->challenge_creator_entry,
                'joins_count' => (int) $quiz->challenge_joins_count,
                'losers_count' => (int) $quiz->challenge_losers_count,
                'paid_participants' => $paidCount,
                'finished_players' => $finishedCount,
                'needs_payment' => ! $canPlay,
            ];

            if (! $canPlay) {
                $quizData = $quiz->toArray();
                $quizData['questions'] = [];

                return response()->json([
                    'success' => true,
                    'quiz' => $quizData,
                    'challenge' => $challengePayload,
                ]);
            }
        } else {
            if ($quiz->status === 'cancelled') {
                return LinkUnavailable::response(LinkUnavailable::CANCELLED, 410);
            }
            if ($quiz->status === 'expired') {
                return LinkUnavailable::response(LinkUnavailable::EXPIRED, 410);
            }
            if ($quiz->status === 'completed') {
                return LinkUnavailable::response(LinkUnavailable::ALREADY_WON, 410);
            }
            if ($quiz->status !== 'active') {
                return LinkUnavailable::response(LinkUnavailable::CLOSED, 410);
            }
        }

        // Ne pas envoyer les réponses correctes au client
        $quizData = $quiz->toArray();
        foreach ($quizData['questions'] as &$question) {
            $answers = collect($question['answers'])->shuffle()->values()->toArray();
            foreach ($answers as &$answer) {
                unset($answer['is_correct']);
            }
            $question['answers'] = $answers;
        }

        $out = [
            'success' => true,
            'quiz' => $quizData,
        ];
        if ($quiz->challenge_mode) {
            $out['challenge'] = [
                'intro' => $quiz->challenge_intro,
                'pot' => (int) $quiz->challenge_pot,
                'min_bet' => (int) $quiz->challenge_min_bet,
                'joins_count' => (int) $quiz->challenge_joins_count,
                'losers_count' => (int) $quiz->challenge_losers_count,
                'needs_payment' => false,
            ];
        }

        return response()->json($out);
    }

    /**
     * Rejoindre un quiz en mode challenge (après paiement côté client)
     */
    public function joinChallenge(Request $request, $link)
    {
        $validated = $request->validate([
            'participant_phone' => 'required|string',
            'participant_name' => 'nullable|string|max:255',
            'stake_amount' => 'required|integer|min:1',
            'payment_reference' => 'required|string|max:255',
        ]);

        $quiz = Quiz::where('unique_link', $link)->first();
        if (! $quiz || ! $quiz->challenge_mode) {
            return LinkUnavailable::response(LinkUnavailable::DELETED, 404);
        }
        if ($quiz->challenge_closed) {
            return LinkUnavailable::response(LinkUnavailable::CLOSED, 410);
        }

        if ($quiz->status !== 'active') {
            return LinkUnavailable::response(LinkUnavailable::CLOSED, 410);
        }

        $phone = $this->normalizeChallengePhone($validated['participant_phone']);
        if ($phone === '') {
            return response()->json(['success' => false, 'message' => 'Numéro invalide.'], 422);
        }

        if ((int) $validated['stake_amount'] < (int) $quiz->challenge_min_bet) {
            return response()->json([
                'success' => false,
                'message' => 'Le montant est inférieur au minimum requis.',
                'min_bet' => (int) $quiz->challenge_min_bet,
            ], 422);
        }

        if (QuizChallengeEntry::where('quiz_id', $quiz->id)->where('participant_phone', $phone)->exists()) {
            return response()->json(['success' => false, 'message' => 'Ce numéro a déjà rejoint le challenge.'], 409);
        }

        DB::transaction(function () use ($quiz, $phone, $validated) {
            QuizChallengeEntry::create([
                'quiz_id' => $quiz->id,
                'participant_phone' => $phone,
                'participant_name' => $validated['participant_name'] ?? null,
                'stake_amount' => (int) $validated['stake_amount'],
                'payment_reference' => $validated['payment_reference'],
                'status' => 'paid',
            ]);
            $quiz->increment('challenge_joins_count');
        });

        $quiz->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Inscription au challenge confirmée.',
            'challenge' => [
                'pot' => (int) $quiz->challenge_pot,
                'joins_count' => (int) $quiz->challenge_joins_count,
            ],
        ]);
    }

    /**
     * Le créateur retire la cagnotte entière et clôture le challenge
     */
    public function withdrawChallengePot(Request $request, $link)
    {
        $validated = $request->validate([
            'access_code' => 'required|string|max:32',
        ]);

        $quiz = Quiz::where('unique_link', $link)->first();
        if (! $quiz || ! $quiz->challenge_mode) {
            return response()->json(['success' => false, 'message' => 'Challenge introuvable.'], 404);
        }

        if (strtoupper(trim($validated['access_code'])) !== strtoupper((string) $quiz->access_code)) {
            return response()->json(['success' => false, 'message' => 'Code d\'accès invalide.'], 403);
        }

        if ($quiz->challenge_closed) {
            return response()->json(['success' => false, 'message' => 'Challenge déjà clôturé.'], 400);
        }

        $amount = (int) $quiz->challenge_pot;
        if ($amount <= 0) {
            return response()->json(['success' => false, 'message' => 'Cagnotte vide.'], 400);
        }

        DB::transaction(function () use ($quiz) {
            $quiz->update([
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

    /**
     * Récupérer les informations de partage d'un quiz (sans vérifier le statut)
     */
    public function getQuizShareInfo($link)
    {
        $quiz = Quiz::where('unique_link', $link)
            ->select('id', 'unique_link', 'access_code', 'creator_name', 'creator_email', 'total_amount', 'total_questions', 'status', 'created_at', 'challenge_mode', 'challenge_pot')
            ->first();

        if (! $quiz) {
            return LinkUnavailable::response(LinkUnavailable::DELETED, 404);
        }

        if ($quiz->status === 'cancelled') {
            return LinkUnavailable::response(LinkUnavailable::CANCELLED, 410);
        }
        if ($quiz->status === 'expired') {
            return LinkUnavailable::response(LinkUnavailable::EXPIRED, 410);
        }
        if ($quiz->challenge_mode && $quiz->challenge_closed) {
            return LinkUnavailable::response(LinkUnavailable::CLOSED, 410);
        }
        if (! $quiz->challenge_mode && $quiz->status === 'completed') {
            return LinkUnavailable::response(LinkUnavailable::ALREADY_WON, 410);
        }

        return response()->json([
            'success' => true,
            'quiz' => $quiz
        ]);
    }

    /**
     * Vérifier si un participant peut accéder au quiz
     */
    public function checkAccess(Request $request, $link)
    {
        $validated = $request->validate([
            'phone' => 'required|string'
        ]);

        $quiz = Quiz::where('unique_link', $link)->first();

        if (! $quiz) {
            return LinkUnavailable::response(LinkUnavailable::DELETED, 404);
        }

        if ($quiz->status === 'cancelled') {
            return LinkUnavailable::response(LinkUnavailable::CANCELLED, 410);
        }
        if ($quiz->status === 'expired') {
            return LinkUnavailable::response(LinkUnavailable::EXPIRED, 410);
        }
        if ($quiz->challenge_mode && $quiz->challenge_closed) {
            return LinkUnavailable::response(LinkUnavailable::CLOSED, 410);
        }
        if (! $quiz->challenge_mode && $quiz->status === 'completed') {
            return LinkUnavailable::response(LinkUnavailable::ALREADY_WON, 410);
        }
        if ($quiz->status !== 'active') {
            return LinkUnavailable::response(LinkUnavailable::CLOSED, 410);
        }

        $normPhone = $this->normalizeChallengePhone($validated['phone']);

        // Vérifier si la personne a déjà répondu
        $existingAttempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where(function ($q) use ($validated, $normPhone) {
                $q->where('participant_phone', $validated['phone']);
                if ($normPhone !== '') {
                    $q->orWhere('participant_phone', $normPhone);
                }
            })
            ->first();

        if ($existingAttempt) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà participé à ce quiz',
                'can_access' => false,
                'already_participated' => true
            ], 403);
        }

        if ($quiz->challenge_mode) {
            $paid = QuizChallengeEntry::where('quiz_id', $quiz->id)
                ->where('participant_phone', $normPhone)
                ->where('status', 'paid')
                ->exists();

            return response()->json([
                'success' => true,
                'can_access' => $paid,
                'message' => $paid ? 'Accès autorisé' : 'Payez votre mise pour rejoindre le challenge.',
            ]);
        }

        // Vérifier l'accès selon le type
        $canAccess = false;
        if ($quiz->access_type === 'everyone') {
            $canAccess = true;
        } elseif ($quiz->access_type === 'single') {
            $canAccess = $quiz->single_participant_phone === $validated['phone'];
        } elseif ($quiz->access_type === 'multiple') {
            $canAccess = QuizAllowedParticipant::where('quiz_id', $quiz->id)
                ->where('phone_number', $validated['phone'])
                ->exists();
        }

        return response()->json([
            'success' => true,
            'can_access' => $canAccess,
            'message' => $canAccess ? 'Accès autorisé' : 'Vous n\'êtes pas autorisé à participer à ce quiz'
        ]);
    }

    /**
     * Soumettre les réponses du quiz
     */
    public function submitQuiz(Request $request, $link)
    {
        $validated = $request->validate([
            'participant_name' => 'required|string|max:255',
            'participant_phone' => 'required|string',
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer|exists:quiz_questions,id',
            'answers.*.answer_id' => 'required|integer|exists:quiz_answers,id'
        ]);

        $quiz = Quiz::where('unique_link', $link)
            ->with('questions')
            ->first();

        if (! $quiz) {
            return LinkUnavailable::response(LinkUnavailable::DELETED, 404);
        }

        if ($quiz->challenge_mode) {
            if ($quiz->challenge_closed) {
                return LinkUnavailable::response(LinkUnavailable::CLOSED, 410);
            }
        } else {
            if ($quiz->status === 'cancelled') {
                return LinkUnavailable::response(LinkUnavailable::CANCELLED, 410);
            }
            if ($quiz->status === 'expired') {
                return LinkUnavailable::response(LinkUnavailable::EXPIRED, 410);
            }
            if ($quiz->status === 'completed') {
                return LinkUnavailable::response(LinkUnavailable::ALREADY_WON, 410);
            }
            if ($quiz->status !== 'active') {
                return LinkUnavailable::response(LinkUnavailable::CLOSED, 410);
            }
        }

        $normPhone = $this->normalizeChallengePhone($validated['participant_phone']);
        $challengeEntry = null;
        if ($quiz->challenge_mode) {
            $challengeEntry = QuizChallengeEntry::where('quiz_id', $quiz->id)
                ->where('participant_phone', $normPhone)
                ->where('status', 'paid')
                ->first();
            if (! $challengeEntry) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payez votre mise pour accéder au challenge.',
                ], 403);
            }
        }

        // Vérifier l'accès (mode classique)
        if (! $quiz->challenge_mode) {
            if ($quiz->access_type === 'single') {
                if ($quiz->single_participant_phone !== $validated['participant_phone']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Vous n\'êtes pas autorisé à participer à ce quiz'
                    ], 403);
                }
            } elseif ($quiz->access_type === 'multiple') {
                $isAllowed = QuizAllowedParticipant::where('quiz_id', $quiz->id)
                    ->where('phone_number', $validated['participant_phone'])
                    ->exists();
                if (!$isAllowed) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Vous n\'êtes pas autorisé à participer à ce quiz'
                    ], 403);
                }
            }
        }

        // Vérifier si la personne a déjà répondu
        $existingAttempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('participant_phone', $normPhone ?: $validated['participant_phone'])
            ->first();

        if ($existingAttempt) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà participé à ce quiz',
                'attempt_id' => $existingAttempt->id
            ], 403);
        }

        // Vérifier que toutes les questions ont été répondues
        if (count($validated['answers']) !== $quiz->total_questions) {
            return response()->json([
                'success' => false,
                'message' => 'Toutes les questions doivent être répondues'
            ], 400);
        }

        $participantPhoneStored = $quiz->challenge_mode && $normPhone !== ''
            ? $normPhone
            : $validated['participant_phone'];

        // Créer la tentative
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'participant_name' => $validated['participant_name'],
            'participant_phone' => $participantPhoneStored,
            'total_questions' => $quiz->total_questions,
            'status' => 'completed'
        ]);

        $correctCount = 0;

        // Traiter chaque réponse
        foreach ($validated['answers'] as $answerData) {
            $question = QuizQuestion::find($answerData['question_id']);
            $selectedAnswer = QuizAnswer::find($answerData['answer_id']);

            if (!$question || !$selectedAnswer || $question->quiz_id !== $quiz->id) {
                continue;
            }

            $isCorrect = $selectedAnswer->is_correct;

            if ($isCorrect) {
                $correctCount++;
            }

            QuizAttemptAnswer::create([
                'quiz_attempt_id' => $attempt->id,
                'quiz_question_id' => $question->id,
                'quiz_answer_id' => $selectedAnswer->id,
                'is_correct' => $isCorrect
            ]);
        }

        // Calculer le score en pourcentage
        $score = ($correctCount / $quiz->total_questions) * 100;
        $hasWon = $correctCount >= $quiz->required_correct;

        if ($quiz->challenge_mode) {
            $quiz->refresh();
            $potBefore = (int) $quiz->challenge_pot;
            $takeFromPot = $hasWon ? $potBefore : 0;
            $wonAmount = $hasWon ? $takeFromPot : 0;

            DB::transaction(function () use ($quiz, $hasWon, $challengeEntry, $attempt, $takeFromPot) {
                $quiz->refresh();
                if ($hasWon) {
                    $dec = min($takeFromPot, max(0, (int) $quiz->challenge_pot));
                    if ($dec > 0) {
                        $quiz->decrement('challenge_pot', $dec);
                    }
                } else {
                    $quiz->increment('challenge_pot', (int) $challengeEntry->stake_amount);
                    $quiz->increment('challenge_losers_count');
                }
                $challengeEntry->update([
                    'status' => 'completed',
                    'quiz_attempt_id' => $attempt->id,
                ]);
            });

            $attempt->update([
                'correct_answers' => $correctCount,
                'score' => round($score, 2),
                'won_amount' => $wonAmount,
                'has_won' => $hasWon
            ]);
        } else {
            $wonAmount = $hasWon ? $quiz->total_amount : 0;

            $attempt->update([
                'correct_answers' => $correctCount,
                'score' => round($score, 2),
                'won_amount' => $wonAmount,
                'has_won' => $hasWon
            ]);

            if ($quiz->access_type === 'single') {
                $quiz->update(['status' => 'completed']);
            } elseif (($quiz->access_type === 'multiple' || $quiz->access_type === 'everyone') && $hasWon) {
                $quiz->update(['status' => 'completed']);
            }
        }

        return response()->json([
            'success' => true,
            'attempt_id' => $attempt->id,
            'result' => [
                'correct_answers' => $correctCount,
                'total_questions' => $quiz->total_questions,
                'score' => round($score, 2),
                'required_correct' => $quiz->required_correct,
                'has_won' => $hasWon,
                'won_amount' => $wonAmount
            ]
        ]);
    }

    /**
     * Récupérer le résultat d'une tentative
     */
    public function getQuizResult($attemptId)
    {
        try {
            $attempt = QuizAttempt::with([
                'quiz',
                'attemptAnswers.question',
                'attemptAnswers.answer',
                'attemptAnswers.question.answers'
            ])->find($attemptId);

            if (! $attempt) {
                return LinkUnavailable::response(LinkUnavailable::DELETED, 404);
            }

            if (! $attempt->quiz) {
                return LinkUnavailable::response(LinkUnavailable::DELETED, 404);
            }

            // Calculer le pourcentage requis en évitant la division par zéro
            $totalQuestions = $attempt->total_questions > 0 ? $attempt->total_questions : 1;
            $requiredPercentage = ($attempt->quiz->required_correct / $totalQuestions) * 100;

            // Préparer les réponses avec gestion des valeurs nulles
            $answers = $attempt->attemptAnswers->map(function ($attemptAnswer) {
                $question = $attemptAnswer->question;
                $selectedAnswer = $attemptAnswer->answer;

                // Récupérer les bonnes réponses pour cette question
                $correctAnswers = [];
                if ($question && $question->answers) {
                    $correctAnswers = $question->answers
                        ->where('is_correct', true)
                        ->pluck('answer')
                        ->toArray();
                }

                return [
                    'question' => $question ? $question->question : 'Question non disponible',
                    'selected_answer' => $selectedAnswer ? $selectedAnswer->answer : 'Aucune réponse',
                    'is_correct' => (bool) $attemptAnswer->is_correct,
                    'correct_answers' => $correctAnswers
                ];
            });

            return response()->json([
                'success' => true,
                'result' => [
                    'participant_name' => $attempt->participant_name ?? 'Participant',
                    'correct_answers' => (int) ($attempt->correct_answers ?? 0),
                    'total_questions' => (int) $totalQuestions,
                    'score' => (float) ($attempt->score ?? 0),
                    'required_correct' => (int) ($attempt->quiz->required_correct ?? 0),
                    'required_percentage' => round($requiredPercentage, 2),
                    'has_won' => (bool) ($attempt->has_won ?? false),
                    'won_amount' => (float) ($attempt->won_amount ?? 0),
                    'answers' => $answers
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans getQuizResult: ' . $e->getMessage(), [
                'attempt_id' => $attemptId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du résultat: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retirer le gain d'un quiz
     */
    public function withdrawQuizPrize(Request $request)
    {
        $request->validate([
            'attemptId' => 'required|integer|exists:quiz_attempts,id',
            'amount' => 'required|integer|min:1',
        ]);

        $isEyamo = $request->input('paymentMethod') === 'Eyamo'
            || strtoupper((string) $request->input('operator')) === 'EYAMO';

        if ($isEyamo) {
            $request->validate([
                'name' => 'nullable|string|max:100',
                'email' => 'nullable|email|max:255',
                'eyamo_identifier' => 'required|string|max:255',
                'promoCode' => 'nullable|string|max:100',
                'paymentMethod' => 'required|string|in:Eyamo',
                'operator' => 'required|string|in:EYAMO',
            ]);
        } else {
            $request->validate([
                'name' => 'nullable|string|max:100',
                'email' => 'nullable|email|max:255',
                'phoneNumber' => 'required|string|regex:/^[0-9]{9,15}$/',
                'operator' => 'required|string|in:OM,MOMO',
                'promoCode' => 'nullable|string|max:100',
                'paymentMethod' => 'required|string|in:Orange,MTN',
            ]);
        }

        try {
            $attemptId = (int) $request->input('attemptId');
            $amount = (int) $request->input('amount');

            if ($isEyamo) {
                $eyamoUser = EyamoUserResolver::resolve($request->input('eyamo_identifier'));
                if (! $eyamoUser) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Identifiant Eyamo introuvable',
                    ], 422);
                }
                $phoneNumber = preg_replace('/\D/', '', (string) $eyamoUser->phone);
                if (strlen($phoneNumber) < 9) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Téléphone du compte Eyamo invalide',
                    ], 422);
                }
                $operator = 'EYAMO';
            } else {
                $phoneNumber = preg_replace('/\D/', '', (string) $request->input('phoneNumber'));
                $operator = $request->input('operator');
            }

            $attempt = QuizAttempt::with('quiz')->find($attemptId);
            
            if (!$attempt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tentative de quiz non trouvée'
                ], 404);
            }

            if (!$attempt->has_won) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas gagné ce quiz'
                ], 400);
            }

            if ($attempt->status === 'withdrawn') {
                return response()->json([
                    'success' => false,
                    'message' => 'Le gain a déjà été retiré'
                ], 400);
            }

            if ($attempt->won_amount != $amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le montant ne correspond pas au gain'
                ], 400);
            }

            // Simuler le retrait (même logique que pour les cadeaux)
            function generateUuid()
            {
                $data = random_bytes(16);
                $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
                $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
                return vsprintf('%08s-%04s-%04x-%04x-%12s', str_split(bin2hex($data), 4));
            }
            $uuid = generateUuid();

            // Simulation de réponse API pour tests
            $responseBody = [
                'status' => 'SUCCESSFUL',
                'reference' => $uuid,
                'message' => 'Transaction réussie (simulation)'
            ];

            // Mettre à jour la tentative avec les informations de retrait
            $attempt->update([
                'status' => 'withdrawn',
                'receiver_operator' => $operator,
                'receiver_phone' => $phoneNumber,
                'receiver_name' => $request->input('name') ? (string) $request->input('name') : null,
                'receiver_email' => $request->input('email') ? (string) $request->input('email') : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gain retiré avec succès',
                'reference' => $uuid,
                'amount' => $amount
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur lors du retrait du gain de quiz: ' . $e->getMessage(), [
                'attempt_id' => $request->input('attemptId'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du retrait du gain: ' . $e->getMessage()
            ], 500);
        }
    }
}
