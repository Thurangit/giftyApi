<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\ChallengeSelectedQuestion;
use App\Models\ChallengeAnswer;
use App\Models\ChallengeResult;
use App\Models\SystemQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ChallengeController extends Controller
{
    /**
     * Créer un nouveau challenge
     */
    public function createChallenge(Request $request)
    {
        try {
            $validated = $request->validate([
                'creator_name' => 'required|string|max:255',
                'creator_phone' => ['required', 'string', 'max:20', 'regex:/^[0-9]{12}$/'],
                'creator_amount' => 'required|integer|min:1',
                'amount_rule' => 'required|string|in:equal_or_more,less',
                'total_questions' => 'required|integer|in:10,15,20,25,30',
            ], [
                'creator_name.required' => 'Le nom est requis',
                'creator_phone.required' => 'Le numéro de téléphone est requis',
                'creator_phone.regex' => 'Le numéro de téléphone doit contenir exactement 12 chiffres (ex: 237612345678)',
                'creator_amount.required' => 'Le montant est requis',
                'creator_amount.min' => 'Le montant doit être supérieur à 0',
                'amount_rule.required' => 'La règle de montant est requise',
                'total_questions.required' => 'Le nombre de questions est requis',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errorMessages = [];
            foreach ($e->errors() as $field => $messages) {
                $errorMessages = array_merge($errorMessages, $messages);
            }
            return response()->json([
                'success' => false,
                'message' => 'Données invalides: ' . implode(', ', $errorMessages),
                'errors' => $e->errors()
            ], 422);
        }

        // Générer un lien unique
        $uniqueLink = 'challenge-' . Str::random(32);

        // Créer le challenge
        $challenge = Challenge::create([
            'unique_link' => $uniqueLink,
            'creator_name' => $validated['creator_name'],
            'creator_phone' => $validated['creator_phone'],
            'creator_amount' => $validated['creator_amount'],
            'amount_rule' => $validated['amount_rule'],
            'total_questions' => $validated['total_questions'],
            'status' => 'waiting_participant'
        ]);

        // Créer le participant créateur
        ChallengeParticipant::create([
            'challenge_id' => $challenge->id,
            'name' => $validated['creator_name'],
            'phone' => $validated['creator_phone'],
            'amount' => $validated['creator_amount'],
            'role' => 'creator'
        ]);

        // Utiliser l'URL du frontend depuis la requête ou une valeur par défaut
        $frontendUrl = $request->header('Origin') ?: 'http://localhost:3000';
        $shareLink = rtrim($frontendUrl, '/') . '/challenge/' . $uniqueLink;

        return response()->json([
            'success' => true,
            'challenge' => $challenge,
            'share_link' => $shareLink
        ], 201);
    }

    /**
     * Rejoindre un challenge
     */
    public function joinChallenge(Request $request, $link)
    {
        $validated = $request->validate([
            'participant_name' => 'required|string|max:255',
            'participant_phone' => 'nullable|string|max:20|regex:/^[0-9]{12}$/',
            'participant_amount' => 'required|integer|min:1',
        ]);

        $challenge = Challenge::where('unique_link', $link)
            ->where('status', 'waiting_participant')
            ->first();

        if (!$challenge) {
            return response()->json([
                'success' => false,
                'message' => 'Challenge non trouvé ou déjà complété'
            ], 404);
        }

        // Vérifier le montant selon la règle
        $minAmount = (int)($challenge->creator_amount * 0.1); // 10% minimum
        if ($challenge->amount_rule === 'equal_or_more') {
            if ($validated['participant_amount'] < $challenge->creator_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le montant doit être égal ou supérieur à ' . $challenge->creator_amount . ' XAF'
                ], 400);
            }
        } else { // less
            if ($validated['participant_amount'] >= $challenge->creator_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le montant doit être inférieur à ' . $challenge->creator_amount . ' XAF'
                ], 400);
            }
            if ($validated['participant_amount'] < $minAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le montant doit être au minimum ' . $minAmount . ' XAF (10% du montant du créateur)'
                ], 400);
            }
        }

        // Vérifier si le numéro de téléphone est déjà utilisé dans ce challenge
        $existingParticipant = ChallengeParticipant::where('challenge_id', $challenge->id)
            ->where('phone', $validated['participant_phone'])
            ->first();

        if ($existingParticipant) {
            return response()->json([
                'success' => false,
                'message' => 'Ce numéro de téléphone participe déjà à ce challenge'
            ], 400);
        }

        // Créer le participant
        $participant = ChallengeParticipant::create([
            'challenge_id' => $challenge->id,
            'name' => $validated['participant_name'],
            'phone' => $validated['participant_phone'],
            'amount' => $validated['participant_amount'],
            'role' => 'participant'
        ]);

        // Mettre à jour le statut du challenge
        $challenge->update(['status' => 'waiting_questions']);

        return response()->json([
            'success' => true,
            'challenge' => $challenge->load(['participants']),
            'participant' => $participant
        ]);
    }

    /**
     * Récupérer un challenge
     */
    public function getChallenge($link)
    {
        $challenge = Challenge::where('unique_link', $link)
            ->with(['participants'])
            ->first();

        if (!$challenge) {
            return response()->json([
                'success' => false,
                'message' => 'Challenge non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'challenge' => $challenge
        ]);
    }

    /**
     * Récupérer les questions du système par catégorie
     */
    public function getSystemQuestions(Request $request)
    {
        $category = $request->query('category');
        
        $query = SystemQuestion::query();
        
        if ($category) {
            $query->where('category', $category);
        }

        $questions = $query->inRandomOrder()->limit(100)->get();

        // Grouper par catégorie
        $grouped = $questions->groupBy('category');

        return response()->json([
            'success' => true,
            'questions' => $grouped,
            'categories' => SystemQuestion::distinct()->pluck('category')
        ]);
    }

    /**
     * Sélectionner les questions pour un participant
     */
    public function selectQuestions(Request $request, $link)
    {
        $validated = $request->validate([
            'participant_phone' => 'nullable|string',
            'questions' => 'required|array|min:10|max:30',
            'questions.*.system_question_id' => 'required|integer|exists:system_questions,id',
            'questions.*.question_order' => 'required|integer|min:1',
        ]);

        $challenge = Challenge::where('unique_link', $link)
            ->where('status', 'waiting_questions')
            ->first();

        if (!$challenge) {
            return response()->json([
                'success' => false,
                'message' => 'Challenge non trouvé ou questions déjà sélectionnées'
            ], 404);
        }

        // Vérifier le nombre de questions
        if (count($validated['questions']) !== $challenge->total_questions) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez sélectionner exactement ' . $challenge->total_questions . ' questions'
            ], 400);
        }

        // Trouver le participant
        $participant = ChallengeParticipant::where('challenge_id', $challenge->id)
            ->where('phone', $validated['participant_phone'])
            ->first();

        if (!$participant) {
            return response()->json([
                'success' => false,
                'message' => 'Participant non trouvé'
            ], 404);
        }

        if ($participant->has_selected_questions) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà sélectionné vos questions'
            ], 400);
        }

        // Supprimer les anciennes sélections si elles existent
        ChallengeSelectedQuestion::where('challenge_id', $challenge->id)
            ->where('challenge_participant_id', $participant->id)
            ->delete();

        // Créer les nouvelles sélections
        foreach ($validated['questions'] as $questionData) {
            ChallengeSelectedQuestion::create([
                'challenge_id' => $challenge->id,
                'challenge_participant_id' => $participant->id,
                'system_question_id' => $questionData['system_question_id'],
                'question_order' => $questionData['question_order']
            ]);
        }

        // Marquer que le participant a sélectionné ses questions
        $participant->update(['has_selected_questions' => true]);

        // Vérifier si les deux participants ont sélectionné leurs questions
        $allSelected = ChallengeParticipant::where('challenge_id', $challenge->id)
            ->where('has_selected_questions', true)
            ->count() === 2;

        if ($allSelected) {
            $challenge->update(['status' => 'active']);
        }

        return response()->json([
            'success' => true,
            'all_ready' => $allSelected,
            'message' => $allSelected 
                ? 'Les deux participants ont sélectionné leurs questions. Le challenge peut commencer !'
                : 'Questions sélectionnées. En attente de l\'autre participant...'
        ]);
    }

    /**
     * Récupérer les questions à répondre pour un participant
     */
    public function getQuestionsToAnswer(Request $request, $link)
    {
        $validated = $request->validate([
            'participant_phone' => 'nullable|string',
        ]);

        $challenge = Challenge::where('unique_link', $link)
            ->where('status', 'active')
            ->first();

        if (!$challenge) {
            return response()->json([
                'success' => false,
                'message' => 'Challenge non trouvé ou pas encore actif'
            ], 404);
        }

        // Trouver le participant
        $participant = null;
        if ($validated['participant_phone']) {
            $participant = ChallengeParticipant::where('challenge_id', $challenge->id)
                ->where('phone', $validated['participant_phone'])
                ->first();
        } else {
            // Si pas de numéro, prendre le participant (non créateur)
            $participant = ChallengeParticipant::where('challenge_id', $challenge->id)
                ->where('role', 'participant')
                ->first();
        }

        if (!$participant) {
            return response()->json([
                'success' => false,
                'message' => 'Participant non trouvé'
            ], 404);
        }

        // Trouver l'autre participant
        $otherParticipant = ChallengeParticipant::where('challenge_id', $challenge->id)
            ->where('id', '!=', $participant->id)
            ->first();

        if (!$otherParticipant) {
            return response()->json([
                'success' => false,
                'message' => 'Autre participant non trouvé'
            ], 404);
        }

        // Récupérer les questions sélectionnées par l'autre participant
        $questions = ChallengeSelectedQuestion::where('challenge_id', $challenge->id)
            ->where('challenge_participant_id', $otherParticipant->id)
            ->with(['systemQuestion'])
            ->orderBy('question_order')
            ->get();

        // Préparer les questions avec les réponses mélangées
        $formattedQuestions = $questions->map(function ($selectedQuestion) {
            $systemQuestion = $selectedQuestion->systemQuestion;
            $answers = array_merge(
                [$systemQuestion->correct_answer],
                $systemQuestion->wrong_answers
            );
            shuffle($answers);

            return [
                'id' => $selectedQuestion->id,
                'question' => $systemQuestion->question,
                'category' => $systemQuestion->category,
                'answers' => $answers,
                'correct_answer' => $systemQuestion->correct_answer,
                'question_order' => $selectedQuestion->question_order
            ];
        });

        return response()->json([
            'success' => true,
            'opponent_name' => $otherParticipant->name,
            'questions' => $formattedQuestions
        ]);
    }

    /**
     * Soumettre les réponses
     */
    public function submitAnswers(Request $request, $link)
    {
        $validated = $request->validate([
            'participant_phone' => 'nullable|string',
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer|exists:challenge_selected_questions,id',
            'answers.*.selected_answer' => 'required|string',
        ]);

        $challenge = Challenge::where('unique_link', $link)
            ->where('status', 'active')
            ->first();

        if (!$challenge) {
            return response()->json([
                'success' => false,
                'message' => 'Challenge non trouvé ou pas encore actif'
            ], 404);
        }

        // Trouver le participant
        $participant = null;
        if ($validated['participant_phone']) {
            $participant = ChallengeParticipant::where('challenge_id', $challenge->id)
                ->where('phone', $validated['participant_phone'])
                ->first();
        } else {
            // Si pas de numéro, prendre le participant (non créateur)
            $participant = ChallengeParticipant::where('challenge_id', $challenge->id)
                ->where('role', 'participant')
                ->first();
        }

        if (!$participant) {
            return response()->json([
                'success' => false,
                'message' => 'Participant non trouvé'
            ], 404);
        }

        if ($participant->has_answered) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà répondu aux questions'
            ], 400);
        }

        // Vérifier que toutes les questions ont été répondues
        $otherParticipant = ChallengeParticipant::where('challenge_id', $challenge->id)
            ->where('phone', '!=', $validated['participant_phone'])
            ->first();

        $totalQuestions = ChallengeSelectedQuestion::where('challenge_id', $challenge->id)
            ->where('challenge_participant_id', $otherParticipant->id)
            ->count();

        if (count($validated['answers']) !== $totalQuestions) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez répondre à toutes les questions'
            ], 400);
        }

        $correctCount = 0;

        // Traiter chaque réponse
        foreach ($validated['answers'] as $answerData) {
            $selectedQuestion = ChallengeSelectedQuestion::with('systemQuestion')
                ->find($answerData['question_id']);

            if (!$selectedQuestion || $selectedQuestion->challenge_id !== $challenge->id) {
                continue;
            }

            $isCorrect = $selectedQuestion->systemQuestion->correct_answer === $answerData['selected_answer'];

            if ($isCorrect) {
                $correctCount++;
            }

            ChallengeAnswer::create([
                'challenge_id' => $challenge->id,
                'challenge_participant_id' => $participant->id,
                'challenge_selected_question_id' => $selectedQuestion->id,
                'selected_answer' => $answerData['selected_answer'],
                'is_correct' => $isCorrect
            ]);
        }

        // Marquer que le participant a répondu
        $participant->update(['has_answered' => true]);

        // Vérifier si les deux participants ont répondu
        $allAnswered = ChallengeParticipant::where('challenge_id', $challenge->id)
            ->where('has_answered', true)
            ->count() === 2;

        $resultId = null;

        if ($allAnswered) {
            // Calculer les scores et déterminer le gagnant
            $creator = ChallengeParticipant::where('challenge_id', $challenge->id)
                ->where('role', 'creator')
                ->first();
            $otherParticipant = ChallengeParticipant::where('challenge_id', $challenge->id)
                ->where('role', 'participant')
                ->first();

            $creatorScore = ChallengeAnswer::where('challenge_id', $challenge->id)
                ->where('challenge_participant_id', $creator->id)
                ->where('is_correct', true)
                ->count();

            $participantScore = ChallengeAnswer::where('challenge_id', $challenge->id)
                ->where('challenge_participant_id', $otherParticipant->id)
                ->where('is_correct', true)
                ->count();

            $totalAmount = $creator->amount + $otherParticipant->amount;
            $winnerId = null;
            $wonAmount = 0;
            $status = 'tie';

            if ($creatorScore > $participantScore) {
                $winnerId = $creator->id;
                $wonAmount = $totalAmount;
                $status = 'completed';
            } elseif ($participantScore > $creatorScore) {
                $winnerId = $otherParticipant->id;
                $wonAmount = $totalAmount;
                $status = 'completed';
            }

            // Créer le résultat
            $result = ChallengeResult::create([
                'challenge_id' => $challenge->id,
                'winner_id' => $winnerId,
                'creator_score' => $creatorScore,
                'participant_score' => $participantScore,
                'total_amount' => $totalAmount,
                'won_amount' => $wonAmount,
                'status' => $status
            ]);

            $resultId = $result->id;
            $challenge->update(['status' => 'completed']);
        }

        return response()->json([
            'success' => true,
            'score' => $correctCount,
            'total_questions' => $totalQuestions,
            'all_answered' => $allAnswered,
            'result_id' => $resultId
        ]);
    }

    /**
     * Récupérer le résultat d'un challenge
     */
    public function getChallengeResult($resultId)
    {
        try {
            $result = ChallengeResult::with(['challenge.participants', 'winner'])
                ->find($resultId);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Résultat non trouvé'
                ], 404);
            }

            $challenge = $result->challenge;
            $creator = $challenge->participants->where('role', 'creator')->first();
            $participant = $challenge->participants->where('role', 'participant')->first();

            return response()->json([
                'success' => true,
                'result' => [
                    'creator_name' => $creator->name,
                    'creator_score' => $result->creator_score,
                    'participant_name' => $participant->name,
                    'participant_score' => $result->participant_score,
                    'total_questions' => $challenge->total_questions,
                    'total_amount' => $result->total_amount,
                    'won_amount' => $result->won_amount,
                    'status' => $result->status,
                    'winner_id' => $result->winner_id,
                    'winner_name' => $result->winner ? $result->winner->name : null,
                    'is_tie' => $result->status === 'tie'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans getChallengeResult: ' . $e->getMessage(), [
                'result_id' => $resultId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du résultat: ' . $e->getMessage()
            ], 500);
        }
    }
}

