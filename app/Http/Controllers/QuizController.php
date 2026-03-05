<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizAllowedParticipant;
use App\Helpers\CodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
    /**
     * Créer un nouveau quiz
     */
    public function createQuiz(Request $request)
    {
        try {
            $validated = $request->validate([
                'creator_name' => 'required|string|max:255',
                'creator_email' => 'nullable|email|max:255',
                'total_questions' => 'required|integer|in:5,10,15,20',
                'required_correct' => 'required|integer|min:1',
                'final_amount' => 'required|integer|min:0',
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
            ]);

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
                'access_type' => $validated['access_type'],
                'single_participant_phone' => $validated['access_type'] === 'single' ? ($validated['single_participant_phone'] ?? null) : null,
                'opening_message' => $validated['opening_message'] ?? null,
                'status' => 'active'
            ]);

            // Créer les participants autorisés si access_type = multiple
            if ($validated['access_type'] === 'multiple' && !empty($validated['allowed_phones'])) {
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
    public function getQuiz($link)
    {
        $quiz = Quiz::where('unique_link', $link)
            ->where('status', 'active')
            ->with([
                'questions' => function ($query) {
                    $query->orderBy('question_order');
                },
                'questions.answers' => function ($query) {
                    $query->orderBy('answer_order');
                }
            ])
            ->first();

        if (!$quiz) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz non trouvé ou inactif'
            ], 404);
        }

        // Ne pas envoyer les réponses correctes au client
        $quizData = $quiz->toArray();
        foreach ($quizData['questions'] as &$question) {
            // Mélanger les réponses à chaque fois pour éviter que la bonne réponse soit toujours au même endroit
            $answers = collect($question['answers'])->shuffle()->values()->toArray();
            foreach ($answers as &$answer) {
                unset($answer['is_correct']);
            }
            $question['answers'] = $answers;
        }

        return response()->json([
            'success' => true,
            'quiz' => $quizData
        ]);
    }

    /**
     * Récupérer les informations de partage d'un quiz (sans vérifier le statut)
     */
    public function getQuizShareInfo($link)
    {
        $quiz = Quiz::where('unique_link', $link)
            ->select('id', 'unique_link', 'access_code', 'creator_name', 'creator_email', 'amount', 'status', 'created_at')
            ->first();

        if (!$quiz) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz non trouvé'
            ], 404);
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

        $quiz = Quiz::where('unique_link', $link)
            ->where('status', 'active')
            ->first();

        if (!$quiz) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz non trouvé ou inactif'
            ], 404);
        }

        // Vérifier si la personne a déjà répondu
        $existingAttempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('participant_phone', $validated['phone'])
            ->first();

        if ($existingAttempt) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà participé à ce quiz',
                'can_access' => false,
                'already_participated' => true
            ], 403);
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
            ->where('status', 'active')
            ->with('questions')
            ->first();

        if (!$quiz) {
            return response()->json([
                'success' => false,
                'message' => 'Quiz non trouvé ou inactif'
            ], 404);
        }

        // Vérifier l'accès
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

        // Vérifier si la personne a déjà répondu
        $existingAttempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('participant_phone', $validated['participant_phone'])
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

        // Créer la tentative
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'participant_name' => $validated['participant_name'],
            'participant_phone' => $validated['participant_phone'],
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
        $wonAmount = $hasWon ? $quiz->total_amount : 0;

        // Mettre à jour la tentative avec le score
        $attempt->update([
            'correct_answers' => $correctCount,
            'score' => round($score, 2),
            'won_amount' => $wonAmount,
            'has_won' => $hasWon
        ]);

        // Marquer le quiz comme complété selon les règles :
        // - "single" : marquer comme complété après une tentative (succès ou échec)
        // - "multiple" : marquer comme complété dès qu'une personne gagne
        // - "everyone" : marquer comme complété dès qu'une personne gagne
        if ($quiz->access_type === 'single') {
            // Quiz à une personne : marquer comme complété après une tentative (succès ou échec)
            $quiz->update(['status' => 'completed']);
        } elseif (($quiz->access_type === 'multiple' || $quiz->access_type === 'everyone') && $hasWon) {
            // Quiz à plusieurs personnes ou tout le monde : marquer comme complété dès qu'une personne gagne
            $quiz->update(['status' => 'completed']);
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

            if (!$attempt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tentative non trouvée'
                ], 404);
            }

            if (!$attempt->quiz) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quiz associé non trouvé'
                ], 404);
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
        $validated = $request->validate([
            'attemptId' => 'required|integer|exists:quiz_attempts,id',
            'amount' => 'required|integer|min:1',
            'name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phoneNumber' => 'required|string|regex:/^[0-9]{9,15}$/',
            'operator' => 'required|string|in:OM,MOMO',
            'promoCode' => 'nullable|string|max:100',
            'paymentMethod' => 'required|string|in:Orange,MTN',
        ]);

        try {
            $attempt = QuizAttempt::with('quiz')->find($validated['attemptId']);
            
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

            if ($attempt->won_amount != $validated['amount']) {
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
                'receiver_operator' => $validated['operator'],
                'receiver_phone' => $validated['phoneNumber'],
                'receiver_name' => $validated['name'] ?? null,
                'receiver_email' => $validated['email'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gain retiré avec succès',
                'reference' => $uuid,
                'amount' => $validated['amount']
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur lors du retrait du gain de quiz: ' . $e->getMessage(), [
                'attempt_id' => $validated['attemptId'] ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du retrait du gain: ' . $e->getMessage()
            ], 500);
        }
    }
}
