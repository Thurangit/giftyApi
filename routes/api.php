<?php

use App\Http\Controllers\PayController;
use App\Http\Controllers\ReceivePayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/send/money/', [PayController::class, 'sendMoney'])->name('send_money');
Route::get('/infos/gift/{ref}', [ReceivePayController::class, 'infoGift'])->name('info_gift');

Route::post('/receive/money', [ReceivePayController::class, 'withdraw'])->name('receive_money');

// Quiz routes
Route::post('/quiz/create', [App\Http\Controllers\QuizController::class, 'createQuiz'])->name('quiz_create');
Route::get('/quiz/{link}', [App\Http\Controllers\QuizController::class, 'getQuiz'])->name('quiz_get');
Route::post('/quiz/{link}/check-access', [App\Http\Controllers\QuizController::class, 'checkAccess'])->name('quiz_check_access');
Route::post('/quiz/{link}/submit', [App\Http\Controllers\QuizController::class, 'submitQuiz'])->name('quiz_submit');
Route::get('/quiz/result/{attemptId}', [App\Http\Controllers\QuizController::class, 'getQuizResult'])->name('quiz_result');

// Moment routes
Route::post('/moment/create', [App\Http\Controllers\MomentController::class, 'createMoment'])->name('moment_create');
Route::get('/moment/{link}', [App\Http\Controllers\MomentController::class, 'getMoment'])->name('moment_get');
Route::post('/moment/{link}/check-access', [App\Http\Controllers\MomentController::class, 'checkAccess'])->name('moment_check_access');
Route::post('/moment/{link}/submit', [App\Http\Controllers\MomentController::class, 'submitMoment'])->name('moment_submit');
Route::get('/moment/result/{attemptId}', [App\Http\Controllers\MomentController::class, 'getMomentResult'])->name('moment_result');

// Challenge routes (À nous 2)
Route::post('/challenge/create', [App\Http\Controllers\ChallengeController::class, 'createChallenge'])->name('challenge_create');
Route::get('/challenge/{link}', [App\Http\Controllers\ChallengeController::class, 'getChallenge'])->name('challenge_get');
Route::post('/challenge/{link}/join', [App\Http\Controllers\ChallengeController::class, 'joinChallenge'])->name('challenge_join');
Route::get('/challenge/system-questions', [App\Http\Controllers\ChallengeController::class, 'getSystemQuestions'])->name('challenge_system_questions');
Route::post('/challenge/{link}/select-questions', [App\Http\Controllers\ChallengeController::class, 'selectQuestions'])->name('challenge_select_questions');
Route::post('/challenge/{link}/questions', [App\Http\Controllers\ChallengeController::class, 'getQuestionsToAnswer'])->name('challenge_get_questions');
Route::post('/challenge/{link}/submit-answers', [App\Http\Controllers\ChallengeController::class, 'submitAnswers'])->name('challenge_submit_answers');
Route::get('/challenge/result/{resultId}', [App\Http\Controllers\ChallengeController::class, 'getChallengeResult'])->name('challenge_result');


